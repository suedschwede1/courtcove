<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adopts the legacy bs_draws table (the old application created and extended it
 * with raw SQL). On a fresh database this creates the live end state; where the
 * table already exists it is left completely untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_draws')) {
            return;
        }

        Schema::create('bs_draws', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('did');
            $table->unsignedInteger('tid')->nullable()->index();
            $table->string('slug', 32)->unique();
            $table->string('title', 160);
            $table->enum('status', ['committed', 'picking', 'closed', 'executed'])->default('committed');
            $table->enum('mode', ['direct', 'token'])->default('token');
            $table->mediumText('payload');
            $table->char('commitment', 64);
            $table->string('beacon_source', 255);
            $table->dateTime('pick_deadline')->nullable();
            $table->dateTime('closed')->nullable();
            $table->char('server_seed', 64)->nullable();
            $table->string('beacon_value', 255)->nullable();
            $table->char('final_seed', 64)->nullable();
            $table->mediumText('result')->nullable();
            $table->unsignedInteger('created_uid');
            $table->dateTime('created');
            $table->dateTime('executed')->nullable();
            $table->dateTime('discarded')->nullable();
            $table->string('discarded_reason', 160)->nullable();
            $table->dateTime('applied_at')->nullable();
            $table->unsignedInteger('applied_uid')->nullable();
        });
    }

    public function down(): void
    {
        // Intentionally empty: adopted live tables are never dropped by Laravel.
    }
};
