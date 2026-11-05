<?php

declare(strict_types=1);

namespace App\Modules\TournamentBase\Preflight;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only schema and data probes for the preflights (SELECTs only).
 */
class SchemaInspector
{
    public function isMysql(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    public function hasTable(string $table): bool
    {
        return Schema::hasTable($table);
    }

    /**
     * @param  list<string>  $columns
     */
    public function hasColumns(string $table, array $columns): bool
    {
        return $this->hasTable($table) && Schema::hasColumns($table, $columns);
    }

    /**
     * @param  array<string, array{0: string, 1: bool}>  $expected  column => [mysql type, nullable]
     * @return list<PreflightResult>
     */
    public function compareColumns(string $table, array $expected): array
    {
        $actual = [];
        foreach (Schema::getColumns($table) as $column) {
            $actual[strtolower((string) $column['name'])] = $column;
        }

        $results = [];
        $mysql = $this->isMysql();

        foreach ($expected as $name => [$type, $nullable]) {
            if (! isset($actual[$name])) {
                $results[] = PreflightResult::fail("columns:{$table}", "missing column {$name}");

                continue;
            }

            if ((bool) $actual[$name]['nullable'] !== $nullable) {
                $results[] = PreflightResult::fail(
                    "columns:{$table}",
                    "column {$name} is ".($actual[$name]['nullable'] ? 'NULL' : 'NOT NULL').', expected '.($nullable ? 'NULL' : 'NOT NULL')
                );
            }

            if ($mysql && $this->normalizeType((string) $actual[$name]['type']) !== $this->normalizeType($type)) {
                $results[] = PreflightResult::fail(
                    "columns:{$table}",
                    "column {$name} has type {$actual[$name]['type']}, expected {$type}"
                );
            }
        }

        foreach (array_keys($actual) as $name) {
            if (! isset($expected[$name])) {
                $results[] = PreflightResult::warn("columns:{$table}", "unexpected extra column {$name}");
            }
        }

        return $results === [] ? [PreflightResult::pass("columns:{$table}", count($expected).' columns match')] : $results;
    }

    public function collation(string $table): ?string
    {
        if (! $this->isMysql()) {
            return null;
        }

        $row = DB::selectOne(
            'SELECT TABLE_COLLATION AS collation FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );

        return $row === null ? null : (string) $row->collation;
    }

    /**
     * @param  list<string>  $columns
     */
    public function hasUniqueIndex(string $table, array $columns): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['unique'] && array_map('strtolower', $index['columns']) === $columns) {
                return true;
            }
        }

        return false;
    }

    /**
     * Unique-key check shared by the preflights: the columns must exist, carry a unique index and hold no duplicates.
     *
     * @param  list<string>  $columns
     */
    public function checkUnique(string $table, array $columns): PreflightResult
    {
        $check = "unique:{$table}(".implode(',', $columns).')';

        if (! $this->hasColumns($table, $columns)) {
            return PreflightResult::fail($check, $this->hasUniqueIndex($table, $columns) ? 'column missing' : 'unique index missing, column missing');
        }

        $groups = $this->duplicateGroups($table, $columns);

        if (! $this->hasUniqueIndex($table, $columns)) {
            return PreflightResult::fail($check, $groups > 0 ? "unique index missing, {$groups} duplicate groups" : 'unique index missing');
        }

        return $groups > 0
            ? PreflightResult::fail($check, "{$groups} duplicate groups")
            : PreflightResult::pass($check);
    }

    /**
     * Number of value combinations that occur more than once (rows with a NULL in any column are ignored).
     *
     * @param  list<string>  $columns
     */
    public function duplicateGroups(string $table, array $columns): int
    {
        $query = DB::table($table)->select($columns);

        foreach ($columns as $column) {
            $query->whereNotNull($column);
        }

        return DB::query()
            ->fromSub($query->groupBy($columns)->havingRaw('COUNT(*) > 1'), 'dup')
            ->count();
    }

    /**
     * Rows in $child whose non-NULL $childColumn has no match in $parent.$parentColumn.
     */
    public function orphanCount(string $child, string $childColumn, string $parent, string $parentColumn): int
    {
        return DB::table($child)
            ->whereNotNull("{$child}.{$childColumn}")
            ->whereNotExists(function ($query) use ($child, $childColumn, $parent, $parentColumn): void {
                $query->selectRaw('1')
                    ->from($parent)
                    ->whereColumn("{$parent}.{$parentColumn}", "{$child}.{$childColumn}");
            })
            ->count();
    }

    /**
     * The foreign key on $table.$column (MySQL only, null elsewhere or when there is none).
     *
     * @return array{name: string, table: string, column: string, delete_rule: string}|null
     */
    public function foreignKey(string $table, string $column): ?array
    {
        if (! $this->isMysql()) {
            return null;
        }

        $row = DB::selectOne(
            'SELECT k.CONSTRAINT_NAME AS name, k.REFERENCED_TABLE_NAME AS ref_table, k.REFERENCED_COLUMN_NAME AS ref_column, r.DELETE_RULE AS delete_rule '
            .'FROM information_schema.KEY_COLUMN_USAGE k '
            .'JOIN information_schema.REFERENTIAL_CONSTRAINTS r '
            .'ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME '
            .'WHERE k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = ? AND k.COLUMN_NAME = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL '
            .'ORDER BY k.CONSTRAINT_NAME LIMIT 1',
            [$table, $column]
        );

        return $row === null ? null : [
            'name' => (string) $row->name,
            'table' => (string) $row->ref_table,
            'column' => (string) $row->ref_column,
            'delete_rule' => strtoupper((string) $row->delete_rule),
        ];
    }

    private function normalizeType(string $type): string
    {
        return (string) preg_replace('/\b((?:tiny|small|medium|big)?int)\(\d+\)/', '$1', strtolower(trim($type)));
    }
}
