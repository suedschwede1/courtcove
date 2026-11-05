<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adopts the legacy bs_tournaments table (created by the old application with raw SQL).
 * On a fresh database this creates the live end state; where the table already
 * exists it is left completely untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_tournaments')) {
            return;
        }

        Schema::create('bs_tournaments', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('tid');
            $table->string('title', 160);
            $table->unsignedSmallInteger('season');
            $table->boolean('is_test')->default(0);
            $table->unsignedInteger('cloned_from')->nullable();
            $table->enum('status', ['setup', 'drawn', 'running', 'finished'])->default('setup');
            $table->boolean('active')->default(0)->index();
            $table->mediumText('format')->nullable();
            $table->mediumText('lot_orders')->nullable();
            $table->decimal('itn_default', 3, 1)->default(10.0);
            $table->unsignedTinyInteger('points_per_win')->default(2);
            $table->enum('tiebreak_scope', ['tied', 'all'])->default('tied');
            $table->enum('runnerup_mode', ['drop_last', 'points', 'per_match', 'lot'])->default('drop_last');
            $table->dateTime('created');
        });
    }

    public function down(): void
    {
        // Intentionally empty: adopted live tables are never dropped by Laravel.
    }
};
