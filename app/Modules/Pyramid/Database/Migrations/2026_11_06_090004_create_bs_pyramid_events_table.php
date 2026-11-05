<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adopts the legacy bs_pyramid_events table (created by the old application with raw SQL).
 * On a fresh database this creates the live end state; where the table already
 * exists it is left completely untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_pyramid_events')) {
            return;
        }

        Schema::create('bs_pyramid_events', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('id');
            $table->unsignedInteger('ladder_id');
            $table->unsignedInteger('challenge_id')->nullable();
            $table->enum('type', ['challenge', 'schedule', 'result', 'join', 'admin']);
            $table->unsignedInteger('actor_uid')->nullable();
            $table->text('payload');
            $table->dateTime('created');

            $table->index(['ladder_id', 'created']);
        });
    }

    public function down(): void
    {
        // Intentionally empty: adopted live tables are never dropped by Laravel.
    }
};
