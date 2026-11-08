<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trusted_devices')) {
            return;
        }

        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            // Only the SHA-256 of the random cookie token is stored.
            $table->char('token_hash', 64)->unique();
            $table->string('label', 120);
            $table->string('country_code', 2)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');
    }
};
