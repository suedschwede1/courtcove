<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function all_tables_exist_after_migration(): void
    {
        $tables = [
            'bs_users', 'bs_users_meta', 'bs_squares', 'bs_squares_meta',
            'bs_squares_products', 'bs_squares_pricing', 'bs_squares_coupons',
            'bs_bookings', 'bs_bookings_bills', 'bs_bookings_meta',
            'bs_reservations', 'bs_reservations_meta',
            'bs_events', 'bs_events_meta', 'bs_options',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(
                \Schema::hasTable($table),
                "Table {$table} does not exist"
            );
        }
    }

    #[Test]
    public function sessions_table_exists_for_the_database_session_driver(): void
    {
        $this->assertTrue(\Schema::hasTable('sessions'));

        foreach (['id', 'user_id', 'ip_address', 'user_agent', 'payload', 'last_activity'] as $column) {
            $this->assertTrue(\Schema::hasColumn('sessions', $column), "sessions.{$column} missing");
        }
    }

    #[Test]
    public function sessions_migration_is_a_noop_when_the_table_already_exists(): void
    {
        $migration = require database_path('migrations/2026_11_04_000001_create_sessions_table.php');

        $migration->up();

        $this->assertTrue(\Schema::hasTable('sessions'));
    }
}
