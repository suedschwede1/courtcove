<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive: the player exchange (a booking looks for missing players, members sign up).
 * Own tables only; the legacy bs_* tables stay untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('player_exchange_searches')) {
            Schema::create('player_exchange_searches', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('bid')->unique();
                $table->unsignedTinyInteger('needed');
                $table->dateTime('ends_at');
                $table->string('status', 12)->default('open');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('player_exchange_signups')) {
            Schema::create('player_exchange_signups', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('search_id');
                $table->unsignedInteger('uid');
                $table->unsignedTinyInteger('slot');
                $table->timestamp('created_at')->nullable();

                $table->unique(['search_id', 'uid']);
                $table->unique(['search_id', 'slot']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('player_exchange_signups');
        Schema::dropIfExists('player_exchange_searches');
    }
};
