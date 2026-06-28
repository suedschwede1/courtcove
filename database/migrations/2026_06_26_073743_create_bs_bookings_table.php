<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real booking_local schema. status = single|subscription|cancelled (varchar, not enabled/disabled).
 * created is a DATETIME. There is no `updated` column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_bookings')) {
            return;
        }
        Schema::create('bs_bookings', function (Blueprint $table) {
            $table->increments('bid');
            $table->unsignedInteger('uid')->index();
            $table->unsignedInteger('sid')->index();
            $table->string('status', 64);
            $table->string('status_billing', 64);
            $table->string('visibility', 64);
            $table->unsignedInteger('quantity');
            $table->dateTime('created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_bookings');
    }
};
