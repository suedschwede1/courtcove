<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Events\PlayerSearchClosed;
use App\Events\PlayerSearchStarted;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Fires the player search events for the WhatsApp channel: only once the surrounding transaction has
 * committed (nothing for a rolled back attempt), in a web request after the response, in the console at
 * once. A failing listener is reported and never reaches the booking, the exchange or its mails.
 */
final class PlayerSearchEvents
{
    public static function started(int $searchId): void
    {
        self::emit(new PlayerSearchStarted($searchId));
    }

    /** @param  string  $reason  filled|ended|lapsed|cancelled */
    public static function closed(int $searchId, string $reason): void
    {
        self::emit(new PlayerSearchClosed($searchId, $reason));
    }

    private static function emit(object $event): void
    {
        DB::afterCommit(static function () use ($event): void {
            $fire = static function () use ($event): void {
                try {
                    event($event);
                } catch (Throwable $e) {
                    report($e);
                }
            };

            if (app()->runningInConsole()) {
                $fire();
            } else {
                dispatch($fire)->afterResponse();
            }
        });
    }
}
