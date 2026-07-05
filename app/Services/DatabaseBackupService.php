<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * PHP-generated SQL dump/restore for hosting.example.test shared hosting, which has no
 * SSH and therefore no mysqldump. Builds dumps via Laravel's own schema
 * introspection (not hardcoded MySQL syntax) so the exact same code path
 * runs — and is tested — against SQLite in the test suite and MySQL in
 * production.
 */
final class DatabaseBackupService
{
    private const DISK = 'local';
    private const DIRECTORY = 'backups';
    private const MAX_BACKUPS = 10;
    private const INSERT_BATCH_SIZE = 500;

    /**
     * @return array<int, array{filename: string, sizeBytes: int, createdAt: Carbon}>
     */
    public function list(): array
    {
        $backups = [];

        foreach (Storage::disk(self::DISK)->files(self::DIRECTORY) as $path) {
            $filename = basename($path);
            $createdAt = $this->parseTimestamp($filename);

            if ($createdAt === null) {
                continue;
            }

            $backups[] = [
                'filename' => $filename,
                'sizeBytes' => Storage::disk(self::DISK)->size($path),
                'createdAt' => $createdAt,
            ];
        }

        usort($backups, static fn (array $a, array $b): int => $b['createdAt']->timestamp <=> $a['createdAt']->timestamp);

        return $backups;
    }

    public function create(?string $suffix = null): string
    {
        $filename = 'backup-'.Carbon::now()->format('Ymd_His').($suffix !== null ? "-{$suffix}" : '').'.sql.gz';
        $path = self::DIRECTORY.'/'.$filename;

        try {
            Storage::disk(self::DISK)->put($path, gzencode($this->buildDump(), 9));
        } catch (Throwable $e) {
            Storage::disk(self::DISK)->delete($path);

            throw $e;
        }

        $this->prune();

        return $filename;
    }

    public function delete(string $filename): void
    {
        Storage::disk(self::DISK)->delete(self::DIRECTORY.'/'.$filename);
    }

    /**
     * Restores every table in the dump. Runs in two passes — all DROP/CREATE
     * statements first, then all INSERT statements — so foreign-key
     * dependency order between tables never matters, regardless of which
     * order Schema::getTableListing() happened to return them in.
     *
     * @return array<int, string> human-readable list of actions performed
     */
    public function restore(string $filename): array
    {
        $safetyBackup = $this->create('prerestore');
        $actions = ["Sicherheits-Backup erstellt: {$safetyBackup}"];

        $sql = gzdecode(Storage::disk(self::DISK)->get(self::DIRECTORY.'/'.$filename));
        $statements = array_values(array_filter(
            array_map('trim', explode("\n", $sql)),
            static fn (string $s): bool => $s !== '',
        ));

        $structureStatements = array_values(array_filter(
            $statements,
            static fn (string $s): bool => ! str_starts_with($s, 'INSERT'),
        ));
        $dataStatements = array_values(array_filter(
            $statements,
            static fn (string $s): bool => str_starts_with($s, 'INSERT'),
        ));

        Schema::disableForeignKeyConstraints();

        try {
            foreach ([...$structureStatements, ...$dataStatements] as $statement) {
                DB::unprepared($statement);
            }
        } catch (Throwable $e) {
            throw new RuntimeException(
                "Restore aus {$filename} fehlgeschlagen: {$e->getMessage()} (Sicherheits-Backup: {$safetyBackup})",
                0,
                $e,
            );
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $actions[] = count($statements).' Statements aus '.$filename.' ausgeführt.';

        return $actions;
    }

    private function buildDump(): string
    {
        $driver = DB::connection()->getDriverName();
        $schema = $driver === 'sqlite' ? null : DB::connection()->getDatabaseName();
        $tables = Schema::getTableListing($schema, false);

        $statements = [];

        foreach ($tables as $table) {
            $statements[] = "DROP TABLE IF EXISTS `{$table}`;";
            $statements[] = $this->createTableStatement($table, $driver);
            array_push($statements, ...$this->insertStatements($table));
        }

        return implode("\n", $statements)."\n";
    }

    private function createTableStatement(string $table, string $driver): string
    {
        $ddl = $driver === 'sqlite'
            ? DB::selectOne("select sql from sqlite_master where type = 'table' and name = ?", [$table])->sql
            : DB::select("SHOW CREATE TABLE `{$table}`")[0]->{'Create Table'};

        return str_replace(["\r\n", "\n"], ' ', $ddl).';';
    }

    /**
     * @return array<int, string>
     */
    private function insertStatements(string $table): array
    {
        $columns = Schema::getColumnListing($table);
        $rows = DB::table($table)->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $pdo = DB::connection()->getPdo();
        $quotedColumns = implode(',', array_map(static fn (string $c): string => "`{$c}`", $columns));

        $statements = [];

        foreach ($rows->chunk(self::INSERT_BATCH_SIZE) as $chunk) {
            $valueGroups = [];

            foreach ($chunk as $row) {
                $values = array_map(
                    static function (string $column) use ($row, $pdo): string {
                        $value = $row->$column;

                        return $value === null ? 'NULL' : $pdo->quote((string) $value);
                    },
                    $columns,
                );
                $valueGroups[] = '('.implode(',', $values).')';
            }

            $statements[] = "INSERT INTO `{$table}` ({$quotedColumns}) VALUES ".implode(',', $valueGroups).';';
        }

        return $statements;
    }

    private function prune(): void
    {
        foreach (array_slice($this->list(), self::MAX_BACKUPS) as $backup) {
            $this->delete($backup['filename']);
        }
    }

    private function parseTimestamp(string $filename): ?Carbon
    {
        if (! preg_match('/^backup-(\d{8})_(\d{6})(?:-[a-z]+)?\.sql\.gz$/', $filename, $matches)) {
            return null;
        }

        return Carbon::createFromFormat('Ymd His', "{$matches[1]} {$matches[2]}");
    }
}
