<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adopts the legacy bs_draw_eligible table: creates the live end state on a
 * fresh database, leaves an existing table untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_draw_eligible')) {
            return;
        }

        Schema::create('bs_draw_eligible', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('deid');
            $table->unsignedInteger('did');
            $table->unsignedInteger('tpid');

            $table->unique(['did', 'tpid']);
        });
    }

    public function down(): void
    {
        // Intentionally empty: adopted live tables are never dropped by Laravel.
    }
};
