<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite indexes that speed up the calendar date-range query:
 *
 *   bs_bookings   (sid, status) — the most common filter combination
 *   bs_reservations (date, bid) — date range scan joined back to booking
 *
 * Apply to the real booking_local DB with:
 *   php artisan migrate --database=mysql
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasIndex('bs_bookings', 'bs_bookings_sid_status_index')) {
            Schema::table('bs_bookings', function (Blueprint $table): void {
                $table->index(['sid', 'status'], 'bs_bookings_sid_status_index');
            });
        }

        if (!Schema::hasIndex('bs_reservations', 'bs_reservations_date_bid_index')) {
            Schema::table('bs_reservations', function (Blueprint $table): void {
                $table->index(['date', 'bid'], 'bs_reservations_date_bid_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('bs_bookings', function (Blueprint $table): void {
            $table->dropIndex('bs_bookings_sid_status_index');
        });

        Schema::table('bs_reservations', function (Blueprint $table): void {
            $table->dropIndex('bs_reservations_date_bid_index');
        });
    }
};
