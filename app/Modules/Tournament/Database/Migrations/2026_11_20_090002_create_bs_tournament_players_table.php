<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adopts the legacy bs_tournament_players table (created by the old application with raw SQL).
 * On a fresh database this creates the live end state; where the table already
 * exists it is left completely untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_tournament_players')) {
            return;
        }

        Schema::create('bs_tournament_players', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('tpid');
            $table->unsignedInteger('tid');
            $table->unsignedInteger('uid')->nullable();
            $table->string('label', 120);
            $table->enum('gender', ['m', 'w'])->nullable();
            $table->decimal('itn', 3, 1)->nullable();
            $table->decimal('itn_rated', 3, 1);
            $table->unsignedSmallInteger('seed')->nullable();
            $table->char('grp', 1)->nullable();
            $table->unsignedTinyInteger('grp_pos')->nullable();
            $table->tinyInteger('points_adjust')->default(0);
            $table->string('phone', 40)->nullable();
            $table->unsignedInteger('partner_tpid')->nullable()->index();
            $table->string('note', 160)->nullable();

            $table->unique(['tid', 'uid']);
            $table->index(['tid', 'grp']);
        });
    }

    public function down(): void
    {
        // Intentionally empty: adopted live tables are never dropped by Laravel.
    }
};
