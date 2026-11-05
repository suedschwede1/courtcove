<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adopts the legacy bs_mixed_matches table (created by the old application with raw SQL).
 * On a fresh database this creates the live end state; where the table already
 * exists it is left completely untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_mixed_matches')) {
            return;
        }

        Schema::create('bs_mixed_matches', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('id');
            $table->unsignedInteger('round_id');
            $table->unsignedTinyInteger('group_no');
            $table->unsignedTinyInteger('match_no');
            $table->unsignedInteger('team_a_p1');
            $table->unsignedInteger('team_a_p2');
            $table->unsignedInteger('team_b_p1');
            $table->unsignedInteger('team_b_p2');
            $table->unsignedTinyInteger('games_a')->nullable();
            $table->unsignedTinyInteger('games_b')->nullable();
            $table->boolean('is_extra')->default(0);

            $table->index(['round_id', 'group_no']);
        });
    }

    public function down(): void
    {
        // Intentionally empty: adopted live tables are never dropped by Laravel.
    }
};
