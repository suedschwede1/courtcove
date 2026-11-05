<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adopts the legacy bs_pyramid_grants table (created by the old application with raw SQL).
 * On a fresh database this creates the live end state; where the table already
 * exists it is left completely untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_pyramid_grants')) {
            return;
        }

        Schema::create('bs_pyramid_grants', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('id');
            $table->unsignedInteger('ladder_id');
            $table->unsignedInteger('uid');
            $table->enum('role', ['admin'])->default('admin');
            $table->dateTime('created');

            $table->unique(['ladder_id', 'uid']);
            $table->index('ladder_id');
        });
    }

    public function down(): void
    {
        // Intentionally empty: adopted live tables are never dropped by Laravel.
    }
};
