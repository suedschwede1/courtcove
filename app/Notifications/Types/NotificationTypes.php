<?php

declare(strict_types=1);

namespace App\Notifications\Types;

use App\Notifications\Registry\NotificationRegistry;

/**
 * Keys of the built-in notification types (stored in `notification_log.type` and
 * `notification_subscriptions.type`). Only names for callers; everything else about a type lives in its
 * definition in the {@see NotificationRegistry}.
 */
final class NotificationTypes
{
    public const PLAYER_SEARCH_OPENED = 'player_search.opened';

    public const PLAYER_SEARCH_CLOSED = 'player_search.closed';

    public const ADMIN_TEST = 'whatsapp.test';

    private function __construct() {}
}
