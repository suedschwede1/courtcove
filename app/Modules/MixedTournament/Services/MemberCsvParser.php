<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Services;

use DateTime;

/**
 * Parses the member export (semicolon separated, columns found by header
 * name, UTF-8 or Windows-1252, optional BOM) into member rows. Framework- and
 * database-free; the import itself (replace the member table in one
 * transaction) lives elsewhere.
 */
final class MemberCsvParser
{
    /**
     * @return array{rows: list<array{label: string, gender: ?string, itn: ?float, birth_date: ?string}>, missing_column: ?string}
     */
    public function parse(string $content): array
    {
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        $content = (string) preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $lines = preg_split('/\r\n|\r|\n/', trim($content)) ?: [''];
        $header = array_map(fn ($cell): string => trim((string) $cell), str_getcsv((string) array_shift($lines), ';', '"', '\\'));
        $columns = array_flip($header);

        foreach (['Nachname', 'Vorname'] as $column) {
            if (! isset($columns[$column])) {
                return ['rows' => [], 'missing_column' => $column];
            }
        }

        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $fields = str_getcsv($line, ';', '"', '\\');
            $nachname = trim((string) ($fields[$columns['Nachname']] ?? ''));
            $vorname = trim((string) ($fields[$columns['Vorname']] ?? ''));

            $gender = null;
            if (isset($columns['Geschlecht'], $fields[$columns['Geschlecht']])) {
                $raw = trim($fields[$columns['Geschlecht']]);
                $gender = $raw === 'maennlich' ? 'm' : ($raw === 'weiblich' ? 'f' : null);
            }

            $itn = null;
            if (isset($columns['ITN'], $fields[$columns['ITN']])) {
                $raw = trim($fields[$columns['ITN']]);
                if (preg_match('/ITN\s*([0-9]+,[0-9])/', $raw, $match)) {
                    $itn = (float) str_replace(',', '.', $match[1]);
                }
            }

            $birthDate = null;
            if (isset($columns['GebDatum'], $fields[$columns['GebDatum']])) {
                $raw = trim($fields[$columns['GebDatum']]);
                $parsed = DateTime::createFromFormat('d.m.Y', $raw);
                if ($parsed) {
                    $birthDate = $parsed->format('Y-m-d');
                }
            }

            $rows[] = [
                'label' => trim($nachname.' '.$vorname),
                'gender' => $gender,
                'itn' => $itn,
                'birth_date' => $birthDate,
            ];
        }

        return ['rows' => $rows, 'missing_column' => null];
    }
}
