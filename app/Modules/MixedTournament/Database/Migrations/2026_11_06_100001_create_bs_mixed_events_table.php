<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adopts the legacy bs_mixed_events table (created by the old application with raw SQL).
 * On a fresh database this creates the live end state; where the table already
 * exists it is left completely untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_mixed_events')) {
            return;
        }

        Schema::create('bs_mixed_events', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('id');
            $table->string('title', 160);
            $table->date('event_date');
            $table->enum('status', ['draft', 'running', 'done'])->default('draft');
            $table->boolean('is_default')->default(0);
            $table->boolean('is_test')->default(0);
            $table->unsignedSmallInteger('court_count')->default(4);
            $table->enum('mode', ['americano', 'mexicano'])->default('americano');
            $table->dateTime('created');
        });
    }

    public function down(): void
    {
        // Intentionally empty: adopted live tables are never dropped by Laravel.
    }
};
