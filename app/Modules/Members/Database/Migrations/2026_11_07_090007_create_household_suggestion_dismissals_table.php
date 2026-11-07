<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dismissed "possible family" suggestions: signature = sha256 of the sorted member ids, so a
 * dismissed group is not suggested again. dismissed_by_user_id is a bs_users.uid without a DB
 * foreign key (bs_users.uid is int unsigned; same pattern as mail_sends.sent_by_user_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('household_suggestion_dismissals')) {
            return;
        }

        Schema::create('household_suggestion_dismissals', function (Blueprint $table) {
            $table->id();
            $table->char('signature', 64)->unique();
            $table->unsignedInteger('dismissed_by_user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_suggestion_dismissals');
    }
};
