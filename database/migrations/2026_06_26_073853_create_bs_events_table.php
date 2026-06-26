<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real booking_local schema. sid is nullable (null = blocks all courts).
 * datetime_start / datetime_end are DATETIME columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_events', function (Blueprint $table) {
            $table->increments('eid');
            $table->unsignedInteger('sid')->nullable()->index();
            $table->string('status', 64)->default('enabled');
            $table->dateTime('datetime_start')->index();
            $table->dateTime('datetime_end')->index();
            $table->unsignedInteger('capacity')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_events');
    }
};
