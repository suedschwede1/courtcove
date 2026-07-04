<?php

declare(strict_types=1);

namespace App\Modules\Members\Console;

use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\Payment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportMembersCommand extends Command
{
    protected $signature = 'members:import-csv
        {file : Pfad zur CSV-Datei (Vereinsverwaltung-Export)}
        {--joined-at= : Beitrittsdatum für alle importierten Mitglieder (Standard: heute)}';

    protected $description = 'Importiert Mitglieder aus einem Vereinsverwaltungs-CSV-Export inkl. Mitgliedsart und bezahlt-Status';

    /** Mitgliedsarten aus der Vereinsverwaltung → Beitragskategorien in dieser App. */
    private const CATEGORY_ALIASES = [
        'Vollmitglied' => 'Vollzahler',
        'Pensionist' => 'Pensionisten',
        'Kind' => 'Kinder, Schüler',
        'Student' => 'Kinder, Schüler',
        'Unterstützend' => 'Unterstützendes Mitglied',
        'Angehöriger' => 'Ehepartner/in, Lebensgefährte/in',
    ];

    public function handle(): int
    {
        $file = (string) $this->argument('file');

        if (! is_readable($file)) {
            $this->error("Datei nicht lesbar: {$file}");

            return self::FAILURE;
        }

        $rows = $this->readCsv($file);

        if ($rows === []) {
            $this->error('CSV enthält keine Datenzeilen.');

            return self::FAILURE;
        }

        $joinedAt = (string) ($this->option('joined-at') ?: now()->toDateString());
        $currentYear = now()->year;

        $created = 0;
        $skipped = 0;
        $duesCreated = 0;

        DB::transaction(function () use ($rows, $joinedAt, $currentYear, &$created, &$skipped, &$duesCreated): void {
            $nextNumber = Member::nextNumber();

            foreach ($rows as $row) {
                $lastname = trim($row['Name'] ?? '');
                $firstname = trim($row['Vorname'] ?? '');

                if ($lastname === '' && $firstname === '') {
                    continue;
                }

                $birthdate = $this->parseGermanDate($row['GebDatum'] ?? '');

                $exists = Member::query()
                    ->where('firstname', $firstname)
                    ->where('lastname', $lastname)
                    ->when(
                        $birthdate,
                        fn ($query) => $query->whereDate('birthdate', $birthdate->toDateString()),
                        fn ($query) => $query->whereNull('birthdate'),
                    )
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                $member = Member::create([
                    'member_number' => $nextNumber++,
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'birthdate' => $birthdate,
                    'email' => $this->nullable($row['Email'] ?? ''),
                    'phone' => $this->nullable($row['Mobil'] ?? '') ?? $this->nullable($row['Privat'] ?? ''),
                    'address' => $this->buildAddress($row),
                    'joined_at' => $joinedAt,
                ]);

                $duesCreated += $this->createDues($member, $row, $currentYear);
                $created++;
            }
        });

        $this->info("Import abgeschlossen: {$created} Mitglieder angelegt, {$skipped} übersprungen (bereits vorhanden), {$duesCreated} Vorschreibungen erstellt.");
        $this->line('Nicht importiert (kein Feld im Datenmodell): Geschlecht, ID-Nr, Alter, Reservierungssystem, Bemerkung.');

        return self::SUCCESS;
    }

    /**
     * Legt Vorschreibungen aus der "MB"-Spalte an ("bezahlt 26" = Jahr 2026 bezahlt).
     * Das zuletzt bezahlte Jahr bekommt eine bezahlte Vorschreibung; liegt es vor dem
     * aktuellen Jahr, wird zusätzlich eine offene Vorschreibung fürs aktuelle Jahr angelegt.
     */
    private function createDues(Member $member, array $row, int $currentYear): int
    {
        $categoryName = trim($row['Mitgliedsart'] ?? '');

        if ($categoryName === '') {
            return 0;
        }

        $category = FeeCategory::firstOrCreate(['name' => self::CATEGORY_ALIASES[$categoryName] ?? $categoryName]);

        $paidYear = null;
        if (preg_match('/bezahlt\s*(\d{2})/i', $row['MB'] ?? '', $matches)) {
            $paidYear = 2000 + (int) $matches[1];
        }

        $years = array_unique(array_filter([$paidYear, $currentYear]));
        $count = 0;

        foreach ($years as $year) {
            $fiscalYear = FiscalYear::firstOrCreate(
                ['name' => (string) $year],
                ['starts_on' => "{$year}-01-01", 'ends_on' => "{$year}-12-31"],
            );

            $amount = FeeCategoryRate::query()
                ->where('fee_category_id', $category->id)
                ->where('fiscal_year_id', $fiscalYear->id)
                ->value('amount') ?? 0;

            $due = MemberDue::create([
                'member_id' => $member->id,
                'fiscal_year_id' => $fiscalYear->id,
                'fee_category_id' => $category->id,
                'amount' => $amount,
            ]);

            if ($year === $paidYear) {
                $payment = Payment::create([
                    'member_id' => $member->id,
                    'amount' => $amount,
                    'paid_at' => "{$year}-01-01",
                    'note' => 'CSV-Import: '.trim($row['MB'] ?? ''),
                ]);
                $due->payments()->attach($payment);
            }

            $count++;
        }

        return $count;
    }

    /** @return list<array<string, string>> Zeilen als Header-Wert-Paare; Header werden getrimmt ("MB " → "MB"). */
    private function readCsv(string $file): array
    {
        $handle = fopen($file, 'r');
        $header = null;
        $rows = [];

        while (($fields = fgetcsv($handle)) !== false) {
            if ($header === null) {
                // UTF-8-BOM am Dateianfang entfernen, sonst heißt die erste Spalte "\u{FEFF}Name".
                $fields[0] = preg_replace('/^\x{FEFF}/u', '', $fields[0]);
                $header = array_map(trim(...), $fields);

                continue;
            }

            if (count($fields) === 1 && trim((string) $fields[0]) === '') {
                continue;
            }

            $rows[] = array_combine($header, array_pad(array_map(strval(...), $fields), count($header), ''));
        }

        fclose($handle);

        return $rows;
    }

    private function parseGermanDate(string $value): ?Carbon
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $date = Carbon::createFromFormat('d.m.Y', $value);

        return $date === false ? null : $date->startOfDay();
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function buildAddress(array $row): ?string
    {
        $street = trim($row['Strasse'] ?? '');
        $place = trim(sprintf('%s %s', trim($row['PLZ'] ?? ''), trim($row['Ort'] ?? '')));

        return $this->nullable(trim("{$street}, {$place}", ' ,'));
    }
}
