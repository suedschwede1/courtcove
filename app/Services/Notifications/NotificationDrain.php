<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Cache;

/**
 * Lazy drain of the WhatsApp outbox for web requests (the calendar), so messages still go out when the
 * scheduler is late. At most once a minute across all requests, a short run, never breaks the caller.
 * Rows only become due after the debounce period, so without cron sending is delayed (see runbook).
 */
final class NotificationDrain
{
    public const CACHE_KEY = 'notifications.lazy-drain';

    public static function lazy(): void
    {
        if (! Cache::add(self::CACHE_KEY, 1, 60)) {
            return;
        }

        try {
            app(NotificationDispatcher::class)->drain(10, 20);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
