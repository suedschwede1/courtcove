<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive: consent, subscriptions, outbox log and monthly usage for outgoing notifications.
 * Own tables only; the legacy bs_* tables stay untouched. The log holds IDs and keys only,
 * never message text, names or full phone numbers.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_consents')) {
            Schema::create('notification_consents', function (Blueprint $table): void {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->id();
                $table->unsignedInteger('user_id')->index();
                $table->string('channel', 16);
                $table->string('purpose', 32);
                $table->char('phone_hash', 64)->index();
                $table->char('phone_last2', 2);
                $table->string('locale', 5);
                $table->string('consent_text_version', 32);
                $table->dateTime('consented_at');
                $table->dateTime('revoked_at')->nullable();
                $table->string('revoked_via', 16)->nullable();
                $table->unsignedTinyInteger('active_marker')->nullable();
                $table->timestamps();

                // NULL never collides: only one active (marker = 1) consent per user, channel and purpose.
                $table->unique(['user_id', 'channel', 'purpose', 'active_marker'], 'notification_consents_active_unique');
            });
        }

        if (! Schema::hasTable('notification_subscriptions')) {
            Schema::create('notification_subscriptions', function (Blueprint $table): void {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->id();
                $table->unsignedInteger('user_id');
                $table->string('type', 48);
                $table->string('channel', 16);
                $table->json('filters')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'type', 'channel']);
            });
        }

        if (! Schema::hasTable('notification_log')) {
            Schema::create('notification_log', function (Blueprint $table): void {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->id();
                $table->string('type', 48);
                $table->string('channel', 16);
                $table->unsignedInteger('user_id')->nullable();
                $table->string('subject_type', 32);
                $table->unsignedBigInteger('subject_id');
                $table->string('dedupe_key', 191)->unique();
                $table->string('status', 16);
                $table->string('skip_reason', 32)->nullable();
                $table->unsignedTinyInteger('priority');
                $table->json('subject_payload')->nullable();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->dateTime('not_before');
                $table->string('provider_sid', 64)->nullable()->unique();
                $table->string('error_code', 16)->nullable();
                $table->integer('cost_estimate_micros')->nullable();
                $table->integer('cost_reported_micros')->nullable();
                $table->char('phone_last2', 2)->nullable();
                $table->dateTime('sent_at')->nullable();
                $table->dateTime('delivered_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'not_before', 'priority']);
                $table->index(['status', 'not_before']);
                $table->index(['user_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('notification_usage_monthly')) {
            Schema::create('notification_usage_monthly', function (Blueprint $table): void {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->id();
                $table->char('month', 7);
                $table->string('channel', 16);
                $table->unsignedInteger('messages_sent')->default(0);
                $table->unsignedInteger('messages_reserved')->default(0);
                $table->unsignedBigInteger('estimated_micros')->default(0);
                $table->unsignedBigInteger('reported_micros')->default(0);
                $table->dateTime('warned_80_at')->nullable();
                $table->dateTime('warned_100_at')->nullable();

                $table->unique(['month', 'channel']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_usage_monthly');
        Schema::dropIfExists('notification_log');
        Schema::dropIfExists('notification_subscriptions');
        Schema::dropIfExists('notification_consents');
    }
};
