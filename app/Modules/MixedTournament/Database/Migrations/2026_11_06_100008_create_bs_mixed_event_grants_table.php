<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adopts the legacy bs_mixed_event_grants table (created by the old application with raw SQL).
 * On a fresh database this creates the live end state; where the table already
 * exists it is left completely untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_mixed_event_grants')) {
            return;
        }

        Schema::create('bs_mixed_event_grants', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('id');
            $table->unsignedInteger('event_id');
            $table->unsignedInteger('uid');
            $table->enum('role', ['score', 'admin']);
            $table->dateTime('created');

            $table->unique(['event_id', 'uid']);
            $table->index('event_id');
            $table->index('uid');
            // The live foreign key of the adopted end state, not a new one.
            $table->foreign('uid', 'fk_mixed_grant_user')->references('uid')->on('bs_users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Intentionally empty: adopted live tables are never dropped by Laravel.
    }
};
