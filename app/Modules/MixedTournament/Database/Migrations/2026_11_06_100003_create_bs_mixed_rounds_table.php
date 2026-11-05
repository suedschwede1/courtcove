<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adopts the legacy bs_mixed_rounds table (created by the old application with raw SQL).
 * On a fresh database this creates the live end state; where the table already
 * exists it is left completely untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_mixed_rounds')) {
            return;
        }

        Schema::create('bs_mixed_rounds', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('id');
            $table->unsignedInteger('event_id');
            $table->unsignedSmallInteger('round_no');
            $table->enum('group_size_mode', ['4_only', '4_and_5'])->default('4_only');
            $table->enum('distribution', ['random', 'itn', 'itn_balanced', 'age', 'ladies_apart'])->default('random');
            $table->enum('status', ['proposed', 'confirmed', 'completed'])->default('proposed');

            $table->unique(['event_id', 'round_no']);
        });
    }

    public function down(): void
    {
        // Intentionally empty: adopted live tables are never dropped by Laravel.
    }
};
