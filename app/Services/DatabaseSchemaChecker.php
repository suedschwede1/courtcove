<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Schema;

/**
 * Reports database migration status and validates that the bs_* tables
 * this app relies on actually have the columns the code expects. Exists
 * because the production host (hosting.example.test shared hosting) has no SSH/cron
 * access — this is consumed by an admin page instead of `artisan migrate:status`.
 */
final class DatabaseSchemaChecker
{
    /**
     * Expected columns per table, mirrored from database/migrations/*.
     * Kept here as a flat manifest (not derived from the migrations at
     * runtime) so this works without a second DB connection or extra PHP
     * extensions on shared hosting.
     *
     * @var array<string, array<int, string>>
     */
    private const EXPECTED_COLUMNS = [
        'bs_users' => ['uid', 'alias', 'status', 'email', 'pw', 'login_attempts', 'login_detent', 'last_activity', 'last_ip', 'created'],
        'bs_users_meta' => ['umid', 'uid', 'key', 'value'],
        'bs_squares' => ['sid', 'name', 'status', 'priority', 'capacity', 'capacity_heterogenic', 'allow_notes', 'time_start', 'time_end', 'time_block', 'time_block_bookable', 'time_block_bookable_max', 'min_range_book', 'range_book', 'max_active_bookings', 'range_cancel'],
        'bs_squares_meta' => ['smid', 'sid', 'key', 'value', 'locale'],
        'bs_squares_products' => ['spid', 'sid', 'priority', 'date_start', 'date_end', 'name', 'description', 'options', 'price', 'rate', 'gross', 'locale'],
        'bs_squares_pricing' => ['spid', 'sid', 'priority', 'date_start', 'date_end', 'day_start', 'day_end', 'time_start', 'time_end', 'price', 'rate', 'gross', 'per_time_block', 'per_quantity'],
        'bs_squares_coupons' => ['scid', 'sid', 'code', 'date_start', 'date_end', 'discount_for_booking', 'discount_for_products', 'discount_in_percent'],
        'bs_bookings' => ['bid', 'uid', 'sid', 'status', 'status_billing', 'visibility', 'quantity', 'created'],
        'bs_bookings_bills' => ['bbid', 'bid', 'description', 'quantity', 'time', 'price', 'rate', 'gross'],
        'bs_bookings_meta' => ['bmid', 'bid', 'key', 'value'],
        'bs_reservations' => ['rid', 'bid', 'date', 'time_start', 'time_end'],
        'bs_events' => ['eid', 'sid', 'status', 'datetime_start', 'datetime_end', 'capacity'],
        'bs_events_meta' => ['emid', 'eid', 'key', 'value', 'locale'],
        'bs_options' => ['oid', 'key', 'value', 'locale'],
        'bs_reservations_meta' => ['rmid', 'rid', 'key', 'value'],
    ];

    /**
     * @return array<int, array{name: string, ran: bool}>
     */
    public function migrationStatus(): array
    {
        $migrator = app('migrator');

        $ran = $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : [];
        $files = $migrator->getMigrationFiles([database_path('migrations')]);

        return array_map(
            static fn (string $name): array => ['name' => $name, 'ran' => in_array($name, $ran, true)],
            array_keys($files),
        );
    }

    public function hasPendingMigrations(): bool
    {
        foreach ($this->migrationStatus() as $migration) {
            if (! $migration['ran']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{table: string, exists: bool, missing_columns: array<int, string>}>
     */
    public function checkTables(): array
    {
        $results = [];

        foreach (self::EXPECTED_COLUMNS as $table => $expectedColumns) {
            $exists = Schema::hasTable($table);
            $missing = $exists
                ? array_values(array_diff($expectedColumns, Schema::getColumnListing($table)))
                : $expectedColumns;

            $results[] = [
                'table' => $table,
                'exists' => $exists,
                'missing_columns' => $missing,
            ];
        }

        return $results;
    }
}
