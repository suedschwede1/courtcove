<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MigrationTest extends TestCase
{
    use RefreshDatabase;

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
}
