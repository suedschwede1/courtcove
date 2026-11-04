<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adopts the legacy bs_draw_tokens table: creates the live end state on a fresh
 * database, leaves an existing table untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_draw_tokens')) {
            return;
        }

        Schema::create('bs_draw_tokens', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('dtid');
            $table->unsignedInteger('did');
            $table->unsignedSmallInteger('position');
            $table->string('token', 16);
            $table->string('slot', 8)->nullable();
            $table->unsignedInteger('claimed_tpid')->nullable();
            $table->unsignedInteger('claimed_uid')->nullable();
            $table->dateTime('claimed')->nullable();
            $table->enum('assigned_by', ['pick', 'fallback'])->nullable();

            $table->unique(['did', 'token']);
            $table->unique(['did', 'position']);
            $table->unique(['did', 'claimed_tpid']);
            $table->index(['did', 'claimed_tpid']);
        });
    }

    public function down(): void
    {
        // Intentionally empty: adopted live tables are never dropped by Laravel.
    }
};
