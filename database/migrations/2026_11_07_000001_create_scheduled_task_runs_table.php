<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Run log of the scheduled tasks (admin page "Geplante Aufgaben"). Additive and guarded; utf8mb4 so the
 * output excerpt may contain any character. Rows older than 14 days are pruned daily.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scheduled_task_runs')) {
            return;
        }

        Schema::create('scheduled_task_runs', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('name', 100);
            $table->string('command', 255)->nullable();
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('status', 16);
            $table->integer('exit_code')->nullable();
            $table->text('output_excerpt')->nullable();
            $table->string('triggered_by', 16)->default('scheduler');
            $table->unsignedInteger('user_id')->nullable();

            $table->index(['name', 'started_at']);
            $table->index(['status', 'started_at']);
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_task_runs');
    }
};
