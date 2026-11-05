<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adopts the legacy bs_pyramid_challenges table (created by the old application with raw SQL).
 * On a fresh database this creates the live end state; where the table already
 * exists it is left completely untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_pyramid_challenges')) {
            return;
        }

        Schema::create('bs_pyramid_challenges', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('id');
            $table->unsignedInteger('ladder_id');
            $table->unsignedInteger('challenger_id');
            $table->unsignedInteger('opponent_id');
            $table->unsignedSmallInteger('challenger_pos')->nullable();
            $table->unsignedSmallInteger('opponent_pos')->nullable();
            $table->enum('status', ['open', 'scheduled', 'played', 'declined', 'withdrawn', 'forfeited', 'expired'])->default('open');
            $table->dateTime('created');
            $table->date('deadline')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->string('score', 40)->nullable();
            $table->unsignedInteger('winner_id')->nullable();
            $table->unsignedInteger('entered_uid')->nullable();
            $table->dateTime('entered')->nullable();
            $table->enum('decline_reason', ['other_match', 'injury'])->nullable();
            $table->string('admin_note', 160)->nullable();
            $table->dateTime('resolved')->nullable();

            $table->index(['ladder_id', 'status']);
            $table->index('challenger_id');
            $table->index('opponent_id');
            $table->index(['ladder_id', 'deadline']);
        });
    }

    public function down(): void
    {
        // Intentionally empty: adopted live tables are never dropped by Laravel.
    }
};
