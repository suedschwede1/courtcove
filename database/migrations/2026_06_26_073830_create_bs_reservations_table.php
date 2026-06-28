<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real booking_local schema. date is a DATE column ('Y-m-d');
 * time_start / time_end are TIME columns ('H:i:s').
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_reservations')) {
            return;
        }
        Schema::create('bs_reservations', function (Blueprint $table) {
            $table->increments('rid');
            $table->unsignedInteger('bid')->index();
            $table->date('date')->index();
            $table->time('time_start');
            $table->time('time_end');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_reservations');
    }
};
