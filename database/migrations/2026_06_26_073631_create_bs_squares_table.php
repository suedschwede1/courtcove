<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real booking_local schema. No `alias` column — court aliases/labels live in bs_squares_meta.
 * Opening hours are TIME columns; durations/ranges are integer seconds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_squares', function (Blueprint $table) {
            $table->increments('sid');
            $table->string('name', 64);
            $table->string('status', 64)->default('enabled');
            $table->float('priority')->default(1);
            $table->unsignedInteger('capacity');
            $table->boolean('capacity_heterogenic');
            $table->boolean('allow_notes')->default(false);
            $table->time('time_start');
            $table->time('time_end');
            $table->unsignedInteger('time_block');
            $table->unsignedInteger('time_block_bookable');
            $table->unsignedInteger('time_block_bookable_max')->nullable();
            $table->unsignedInteger('min_range_book')->default(0);
            $table->unsignedInteger('range_book')->nullable();
            $table->unsignedInteger('max_active_bookings')->default(0);
            $table->unsignedInteger('range_cancel')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_squares');
    }
};
