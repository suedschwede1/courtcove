<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the real legacy booking_local schema (source of truth).
 * Display name is `alias`; password hash is `pw`. No roles/permissions columns —
 * authorization is driven by `status` (admin/assist/enabled) + bs_users_meta `allow.*` flags.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_users', function (Blueprint $table) {
            $table->increments('uid');
            $table->string('alias', 128)->index();
            $table->string('status', 64)->default('placeholder');
            $table->string('email', 128)->nullable()->index();
            $table->string('pw', 256)->nullable();
            $table->unsignedTinyInteger('login_attempts')->nullable();
            $table->dateTime('login_detent')->nullable();
            $table->dateTime('last_activity')->nullable();
            $table->string('last_ip', 64)->nullable();
            $table->dateTime('created')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_users');
    }
};
