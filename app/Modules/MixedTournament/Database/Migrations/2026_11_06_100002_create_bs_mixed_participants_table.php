<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adopts the legacy bs_mixed_participants table (created by the old application with raw SQL).
 * On a fresh database this creates the live end state; where the table already
 * exists it is left completely untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_mixed_participants')) {
            return;
        }

        Schema::create('bs_mixed_participants', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('id');
            $table->unsignedInteger('event_id');
            $table->unsignedInteger('uid')->nullable();
            $table->string('label', 120);
            $table->enum('gender', ['m', 'f'])->nullable();
            $table->decimal('itn', 3, 1)->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->enum('status', ['active', 'paused'])->default('active');
            $table->boolean('no_extra_match')->default(0);

            $table->unique(['event_id', 'uid']);
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        // Intentionally empty: adopted live tables are never dropped by Laravel.
    }
};
