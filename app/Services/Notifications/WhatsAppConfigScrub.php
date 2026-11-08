<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Option;
use App\Modules\TournamentBase\Staging\ConfigScrub;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staging scrub for the WhatsApp channel: removes the credential options, forces the log driver and the
 * main switch off, and empties the notification tables (a copy of the live database carries real consents
 * and metadata). Only option and table names leave this class, never values.
 */
final class WhatsAppConfigScrub implements ConfigScrub
{
    private const REMOVE = [
        'whatsapp.account_sid',
        'whatsapp.auth_token',
        'whatsapp.messaging_service_sid',
        'whatsapp.sender',
        'whatsapp.sandbox_sender',
    ];

    /** Child tables first. */
    private const TABLES = [
        'notification_log',
        'notification_usage_monthly',
        'notification_subscriptions',
        'notification_consents',
    ];

    public function name(): string
    {
        return 'whatsapp';
    }

    public function scrub(): array
    {
        $names = [];

        if (Schema::hasTable('bs_options')) {
            foreach (self::REMOVE as $key) {
                Option::query()->where('key', $key)->delete();
                $names[] = $key;
            }

            foreach (['whatsapp.driver' => 'log', 'whatsapp.enabled' => '0'] as $key => $value) {
                Option::query()->where('key', $key)->delete();
                Option::create(['key' => $key, 'value' => $value, 'locale' => null]);
                $names[] = $key;
            }
        }

        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
                $names[] = $table;
            }
        }

        return $names;
    }
}
