<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adopts the legacy bs_pyramid_ladders table (created by the old application with raw SQL).
 * On a fresh database this creates the live end state; where the table already
 * exists it is left completely untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_pyramid_ladders')) {
            return;
        }

        Schema::create('bs_pyramid_ladders', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('id');
            $table->string('title', 160);
            $table->unsignedSmallInteger('season');
            $table->string('category', 40)->nullable();
            $table->enum('status', ['setup', 'running', 'closed'])->default('setup');
            $table->decimal('itn_default', 3, 1)->default(10.0);
            $table->unsignedSmallInteger('days_to_play')->default(7);
            $table->unsignedSmallInteger('winner_protected_days')->default(3);
            $table->unsignedSmallInteger('loser_cooldown_days')->default(7);
            $table->boolean('is_default')->default(0);
            $table->string('rules_version', 40)->nullable();
            $table->dateTime('created');

            $table->index(['season', 'category']);
        });
    }

    public function down(): void
    {
        // Intentionally empty: adopted live tables are never dropped by Laravel.
    }
};
