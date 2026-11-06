<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adopts the legacy bs_tournament_matches table (created by the old application with raw SQL).
 * On a fresh database this creates the live end state; where the table already
 * exists it is left completely untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_tournament_matches')) {
            return;
        }

        Schema::create('bs_tournament_matches', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('tmid');
            $table->unsignedInteger('tid');
            $table->string('phase', 16);
            $table->string('mkey', 12)->nullable();
            $table->char('grp', 1)->nullable();
            $table->unsignedTinyInteger('rnd')->nullable();
            $table->unsignedSmallInteger('slot')->nullable();
            $table->unsignedInteger('tpid_a')->nullable();
            $table->unsignedInteger('tpid_b')->nullable();
            $table->date('deadline')->nullable();
            $table->date('played')->nullable();
            $table->time('played_time')->nullable();
            $table->enum('court', ['1', '2', '3'])->nullable();
            $table->string('score', 40)->nullable();
            $table->unsignedTinyInteger('s1a')->nullable();
            $table->unsignedTinyInteger('s1b')->nullable();
            $table->unsignedTinyInteger('s2a')->nullable();
            $table->unsignedTinyInteger('s2b')->nullable();
            $table->unsignedTinyInteger('mtba')->nullable();
            $table->unsignedTinyInteger('mtbb')->nullable();
            $table->unsignedTinyInteger('sets_a')->nullable();
            $table->unsignedTinyInteger('sets_b')->nullable();
            $table->unsignedSmallInteger('games_a')->nullable();
            $table->unsignedSmallInteger('games_b')->nullable();
            $table->enum('retired', ['none', 'ret_a', 'ret_b', 'wo_a', 'wo_b'])->default('none');
            $table->unsignedInteger('winner')->nullable();
            $table->unsignedInteger('entered_uid')->nullable();
            $table->dateTime('entered')->nullable();
            $table->unsignedInteger('confirmed_uid')->nullable();
            $table->dateTime('confirmed')->nullable();

            $table->unique(['tid', 'mkey']);
            $table->index(['tid', 'phase', 'grp']);
            $table->index(['tid', 'deadline']);
            $table->index(['tid', 'phase', 'rnd', 'slot']);
        });
    }

    public function down(): void
    {
        // Intentionally empty: adopted live tables are never dropped by Laravel.
    }
};
