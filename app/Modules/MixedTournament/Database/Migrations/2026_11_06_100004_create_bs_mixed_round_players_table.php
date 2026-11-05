<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adopts the legacy bs_mixed_round_players table (created by the old application with raw SQL).
 * On a fresh database this creates the live end state; where the table already
 * exists it is left completely untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_mixed_round_players')) {
            return;
        }

        Schema::create('bs_mixed_round_players', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('id');
            $table->unsignedInteger('round_id');
            $table->unsignedInteger('participant_id');
            $table->unsignedTinyInteger('group_no')->nullable();

            // Deliberately NO unique key: an extra-group filler has two rows (one per group)
            // and a Mexicano sitter has one row with group_no NULL.
            $table->index(['round_id', 'participant_id']);
            $table->index(['round_id', 'group_no']);
        });
    }

    public function down(): void
    {
        // Intentionally empty: adopted live tables are never dropped by Laravel.
    }
};
