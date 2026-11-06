<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adopts the legacy bs_tournament_lines table (created by the old application with raw SQL).
 * On a fresh database this creates the live end state; where the table already
 * exists it is left completely untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_tournament_lines')) {
            return;
        }

        Schema::create('bs_tournament_lines', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('tlid');
            $table->unsignedInteger('tid');
            $table->string('phase', 16);
            $table->unsignedSmallInteger('line');
            $table->enum('kind', ['seed', 'bye', 'pool']);
            $table->unsignedTinyInteger('seed_no')->nullable();
            $table->string('pool', 16)->nullable();
            $table->string('placeholder', 16)->nullable();
            $table->unsignedInteger('tpid')->nullable();

            $table->unique(['tid', 'phase', 'line']);
            $table->index(['tid', 'phase', 'tpid']);
        });
    }

    public function down(): void
    {
        // Intentionally empty: adopted live tables are never dropped by Laravel.
    }
};
