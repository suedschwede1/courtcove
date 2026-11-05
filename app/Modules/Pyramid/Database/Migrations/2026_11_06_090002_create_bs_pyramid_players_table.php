<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adopts the legacy bs_pyramid_players table (created by the old application with raw SQL).
 * On a fresh database this creates the live end state; where the table already
 * exists it is left completely untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_pyramid_players')) {
            return;
        }

        Schema::create('bs_pyramid_players', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('id');
            $table->unsignedInteger('ladder_id');
            $table->unsignedInteger('uid')->nullable();
            $table->string('label', 120);
            $table->decimal('itn', 3, 1)->nullable();
            $table->decimal('itn_rated', 3, 1);
            $table->unsignedSmallInteger('position')->nullable();
            $table->enum('status', ['waiting', 'entering', 'active', 'paused', 'removed'])->default('waiting');
            $table->enum('gender', ['m', 'f'])->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 128)->nullable();
            $table->dateTime('joined');
            $table->string('note', 160)->nullable();

            $table->unique(['ladder_id', 'uid']);
            $table->unique(['ladder_id', 'position']);
            $table->index(['ladder_id', 'status']);
        });
    }

    public function down(): void
    {
        // Intentionally empty: adopted live tables are never dropped by Laravel.
    }
};
