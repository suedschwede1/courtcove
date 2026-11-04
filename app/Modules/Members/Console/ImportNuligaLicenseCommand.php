<?php

declare(strict_types=1);

namespace App\Modules\Members\Console;

use App\Modules\Members\Models\Member;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportNuligaLicenseCommand extends Command
{
    protected $signature = 'members:import-nuliga-license
        {file : Pfad zum offiziellen nuLiga-Mitgliederdaten-Export (Semikolon-CSV, Windows-1252)}
        {--dry-run : Zeigt nur an, was sich ändern würde, ohne zu speichern}';

    protected $description = 'Übernimmt die Lizenznummer (ID-Nr) aus einem nuLiga-Mitgliederdaten-Export in bestehende Mitglieder (Match über Name+Geburtsdatum); die nuLiga-Datei gilt als autoritative Quelle und überschreibt abweichende, bereits gesetzte Werte.';

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

        $dryRun = (bool) $this->option('dry-run');

        $updated = 0;
        $unchanged = 0;
        $skippedNoLicense = 0;
        $unmatched = 0;
        $conflicts = 0;

        DB::transaction(function () use ($rows, $dryRun, &$updated, &$unchanged, &$skippedNoLicense, &$unmatched, &$conflicts): void {
            foreach ($rows as $row) {
                $lastname = trim($row['Nachname'] ?? '');
                $firstname = trim($row['Vorname'] ?? '');

                if ($lastname === '' && $firstname === '') {
                    continue;
                }

                $licenseId = $this->parseLicenseId($row['ID-Nr'] ?? '');

                if ($licenseId === null) {
                    $skippedNoLicense++;

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

                if (! $member) {
                    $this->warn("Kein Mitglied gefunden für {$firstname} {$lastname} – übersprungen.");
                    $unmatched++;

                    continue;
                }

                if ($member->nuliga_id === $licenseId) {
                    $unchanged++;

                    continue;
                }

                $conflict = Member::query()
                    ->where('nuliga_id', $licenseId)
                    ->where('id', '!=', $member->id)
                    ->exists();

                if ($conflict) {
                    $this->warn("Lizenznummer {$licenseId} von {$firstname} {$lastname} ist bereits einem anderen Mitglied zugeordnet – übersprungen.");
                    $conflicts++;

                    continue;
                }

                if ($dryRun) {
                    $this->line("Würde {$firstname} {$lastname}: {$member->nuliga_id} → {$licenseId} setzen.");
                } else {
                    $member->update(['nuliga_id' => $licenseId]);
                }
                $updated++;
            }
        });

        $this->info(
            'Lizenzabgleich'.($dryRun ? ' (Dry-Run, keine Änderungen gespeichert)' : '').': '.
            "{$updated} aktualisiert, {$unchanged} bereits korrekt, ".
            "{$skippedNoLicense} ohne Lizenz übersprungen, {$unmatched} nicht gefunden, {$conflicts} Konflikte."
        );

        return self::SUCCESS;
    }

    /** @return list<array<string, string>> Zeilen als Header-Wert-Paare; nuLiga liefert die Datei Windows-1252-kodiert. */
    private function readCsv(string $file): array
    {
        $contents = (string) file_get_contents($file);
        $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        $contents = preg_replace('/^\x{FEFF}/u', '', $contents);

        $header = null;
        $rows = [];

        foreach (preg_split('/\r\n|\r|\n/', (string) $contents) as $line) {
            if (trim($line) === '') {
                continue;
            }

            $fields = str_getcsv($line, ';');

            if ($header === null) {
                $header = array_map(trim(...), $fields);

                continue;
            }

            $rows[] = array_combine($header, array_pad(array_map(strval(...), $fields), count($header), ''));
        }

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

    /** "-", leere Werte und der Platzhalter "000000" (= keine Lizenz in nuLiga) werden als "keine Lizenz" behandelt. */
    private function parseLicenseId(string $value): ?int
    {
        $value = trim($value);

        if ($value === '' || $value === '-' || ltrim($value, '0') === '') {
            return null;
        }

        return ctype_digit($value) ? (int) $value : null;
    }
}
