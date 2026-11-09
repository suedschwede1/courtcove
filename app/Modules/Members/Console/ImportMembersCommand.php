<?php

declare(strict_types=1);

namespace App\Modules\Members\Console;

use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
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

    /** Dues not created because their business year is closed. */
    private int $closedYearSkips = 0;

    /** @var array<string, int> Rows not imported because their fee category is deactivated, per category name. */
    private array $deactivatedCategorySkips = [];

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
        // The command instance can be reused (scheduler, tests): start every run with fresh counters.
        $this->closedYearSkips = 0;
        $this->deactivatedCategorySkips = [];

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

        foreach ($rows as $index => $row) {
            $email = $this->nullable($row['Email'] ?? '');
            if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->error(__('members::admin.mail.invalid_import_email', ['row' => $index + 2, 'email' => $email]));

                return self::FAILURE;
            }
        }

        $joinedAt = (string) ($this->option('joined-at') ?: now()->toDateString());
        $currentYear = now()->year;

        $created = 0;
        $skipped = 0;
        $updated = 0;
        $duesCreated = 0;

        DB::transaction(function () use ($rows, $joinedAt, $currentYear, &$created, &$skipped, &$updated, &$duesCreated): void {
            $nextNumber = Member::nextNumber();
            $usedNuligaIds = [];

            foreach ($rows as $row) {
                $lastname = trim($row['Name'] ?? '');
                $firstname = trim($row['Vorname'] ?? '');

                if ($lastname === '' && $firstname === '') {
                    continue;
                }

                $birthdate = $this->parseGermanDate($row['GebDatum'] ?? '');

                $member = Member::query()
                    ->where('firstname', $firstname)
                    ->where('lastname', $lastname)
                    ->when(
                        $birthdate,
                        fn ($query) => $query->whereDate('birthdate', $birthdate->toDateString()),
                        fn ($query) => $query->whereNull('birthdate'),
                    )
                    ->first();

                $category = $this->resolveCategory($row);

                // A deactivated category cannot be assigned to anyone new: the whole row is skipped
                // (no member, no backfill, no dues) and counted in the summary.
                if ($category !== null && $category->isDeactivated()) {
                    $this->deactivatedCategorySkips[$category->name] = ($this->deactivatedCategorySkips[$category->name] ?? 0) + 1;

                    continue;
                }

                $nuligaId = $this->resolveNuligaId($row, $firstname, $lastname, $member?->id, $usedNuligaIds);

                if ($member) {
                    $skipped++;

                    if ($this->backfillMember($member, $row, $category, $nuligaId)) {
                        $updated++;
                    }
                } else {
                    $member = Member::create([
                        'member_number' => $nextNumber++,
                        'nuliga_id' => $nuligaId,
                        'firstname' => $firstname,
                        'lastname' => $lastname,
                        'birthdate' => $birthdate,
                        'gender' => $this->parseGender($row['Geschlecht'] ?? ''),
                        'email' => $this->nullable($row['Email'] ?? ''),
                        'phone' => $this->nullable($row['Mobil'] ?? '') ?? $this->nullable($row['Privat'] ?? ''),
                        'street' => $this->nullable($row['Strasse'] ?? ''),
                        'zip' => $this->nullable($row['PLZ'] ?? ''),
                        'city' => $this->nullable($row['Ort'] ?? ''),
                        'fee_category_id' => $category?->id,
                        'joined_at' => $joinedAt,
                    ]);
                    $created++;
                }

                if ($nuligaId !== null) {
                    $usedNuligaIds[$nuligaId] = $member->id;
                }

                $duesCreated += $this->createDues($member, $row, $currentYear, $category);
            }
        });

        $this->printSummary($created, $skipped, $updated, $duesCreated);

        return self::SUCCESS;
    }

    /** Summary lines (translated): counts, then skipped dues of closed years and rows of deactivated categories. */
    private function printSummary(int $created, int $skipped, int $updated, int $duesCreated): void
    {
        $this->info(__('members::admin.import.summary', ['created' => $created, 'skipped' => $skipped, 'updated' => $updated, 'dues' => $duesCreated]));
        if ($this->closedYearSkips > 0) {
            $this->line(__('members::admin.import.closed_year_skips', ['count' => $this->closedYearSkips]));
        }
        if ($this->deactivatedCategorySkips !== []) {
            $perCategory = [];
            foreach ($this->deactivatedCategorySkips as $name => $count) {
                $perCategory[] = "{$name}: {$count}";
            }
            $this->warn(__('members::admin.import.deactivated_category_skips', [
                'count' => array_sum($this->deactivatedCategorySkips),
                'categories' => implode(', ', $perCategory),
            ]));
        }
        $this->line(__('members::admin.import.not_imported_fields'));
    }

    /**
     * Legt Vorschreibungen aus der "MB"-Spalte an ("bezahlt 26" = Jahr 2026 bezahlt).
     * Das zuletzt bezahlte Jahr bekommt eine bezahlte Vorschreibung; liegt es vor dem
     * aktuellen Jahr, wird zusätzlich eine offene Vorschreibung fürs aktuelle Jahr angelegt.
     * Idempotent: vorhandene Vorschreibungen bleiben unverändert, bereits bezahlte
     * bekommen keine zweite Zahlung.
     */
    private function createDues(Member $member, array $row, int $currentYear, ?FeeCategory $category): int
    {
        if (! $category) {
            return 0;
        }

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

            // A closed business year is locked: no new dues or payments, not even from the import.
            // Checked with the year row locked (the import runs in one transaction), so a close
            // running meanwhile is seen or waits for the import.
            if (FiscalYear::lockForWrite($fiscalYear->id) !== null) {
                $this->closedYearSkips++;

                continue;
            }

            // A member covered by a household due in that year gets no own due (no double billing).
            if (HouseholdDue::coveringFor($member->id, $fiscalYear->id) !== null) {
                continue;
            }

            $amount = FeeCategoryRate::query()
                ->where('fee_category_id', $category->id)
                ->where('fiscal_year_id', $fiscalYear->id)
                ->value('amount');
            if ($amount === null) {
                $this->warn(__('members::admin.dues.import_missing_rate', ['member' => $member->fullName(), 'year' => $year]));

                continue;
            }

            $due = MemberDue::firstOrCreate(
                ['member_id' => $member->id, 'fiscal_year_id' => $fiscalYear->id],
                ['fee_category_id' => $category->id, 'amount' => $amount, 'faellig_am' => $fiscalYear->faellig_am ?? $fiscalYear->starts_on],
            );

            if ($due->wasRecentlyCreated) {
                $count++;
            }

            if ($year === $paidYear && ! $due->isPaid()) {
                $payment = Payment::create([
                    'member_id' => $member->id,
                    'amount' => $due->amount,
                    'paid_at' => "{$year}-01-01",
                    'note' => 'CSV-Import: '.trim($row['MB'] ?? ''),
                ]);
                $due->payments()->attach($payment);
                $due->update(['status' => 'bezahlt', 'bezahlt_am' => $payment->paid_at]);
            }
        }

        return $count;
    }

    /**
     * Trägt bei einem bereits vorhandenen Mitglied nur fehlende (NULL-)Felder aus der CSV nach.
     * Vorhandene Werte werden nie überschrieben. Gibt zurück, ob sich etwas geändert hat.
     */
    private function backfillMember(Member $member, array $row, ?FeeCategory $category, ?int $nuligaId): bool
    {
        $candidates = [
            'gender' => $this->parseGender($row['Geschlecht'] ?? ''),
            'nuliga_id' => $nuligaId,
            'street' => $this->nullable($row['Strasse'] ?? ''),
            'zip' => $this->nullable($row['PLZ'] ?? ''),
            'city' => $this->nullable($row['Ort'] ?? ''),
            'fee_category_id' => $category?->id,
        ];

        $changes = [];
        foreach ($candidates as $field => $value) {
            if ($member->{$field} === null && $value !== null) {
                $changes[$field] = $value;
            }
        }

        if ($changes === []) {
            return false;
        }

        $member->fill($changes)->save();

        return true;
    }

    /**
     * Parst die Nuliga-ID einer Zeile und stellt sicher, dass sie eindeutig bleibt:
     * unparsbare, nicht-leere Werte und Kollisionen mit bereits vergebenen IDs
     * (in dieser DB oder bereits in dieser CSV verarbeitet) werden verwarnt und als NULL behandelt.
     *
     * @param  array<int, int>  $usedNuligaIds  in diesem Lauf bereits vergebene Nuliga-IDs => Member-ID
     */
    private function resolveNuligaId(array $row, string $firstname, string $lastname, ?int $currentMemberId, array $usedNuligaIds): ?int
    {
        $raw = trim($row['ID-Nr'] ?? '');

        // "0" (auch "00" …) ist in der Vereinsverwaltung ein Platzhalter für "keine ID".
        if (ltrim($raw, '0') === '') {
            return null;
        }

        $nuligaId = $this->parseNuligaId($raw);

        if ($nuligaId === null) {
            $this->warn("Nuliga-ID von {$firstname} {$lastname} nicht lesbar: \"{$raw}\" – wird übersprungen.");

            return null;
        }

        if (isset($usedNuligaIds[$nuligaId]) && $usedNuligaIds[$nuligaId] !== $currentMemberId) {
            $this->warn("Nuliga-ID {$nuligaId} von {$firstname} {$lastname} ist bereits einem anderen Mitglied zugeordnet – wird übersprungen.");

            return null;
        }

        $conflict = Member::query()
            ->where('nuliga_id', $nuligaId)
            ->when($currentMemberId, fn ($query) => $query->where('id', '!=', $currentMemberId))
            ->exists();

        if ($conflict) {
            $this->warn("Nuliga-ID {$nuligaId} von {$firstname} {$lastname} ist bereits einem anderen Mitglied zugeordnet – wird übersprungen.");

            return null;
        }

        return $nuligaId;
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

        return $date === null ? null : $date->startOfDay();
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /** Mitgliedsart → Beitragskategorie; legt unbekannte Kategorien an. */
    private function resolveCategory(array $row): ?FeeCategory
    {
        $name = trim($row['Mitgliedsart'] ?? '');

        return $name === '' ? null : FeeCategory::firstOrCreate(['name' => self::CATEGORY_ALIASES[$name] ?? $name]);
    }

    /** "weiblich"/"maennlich"/"divers" (auch abgekürzt) → w/m/d. */
    private function parseGender(string $value): ?string
    {
        $letter = mb_strtolower(mb_substr(trim($value), 0, 1));

        return in_array($letter, ['m', 'w', 'd'], true) ? $letter : null;
    }

    private function parseNuligaId(string $value): ?int
    {
        $value = trim($value);

        return ctype_digit($value) ? (int) $value : null;
    }
}
