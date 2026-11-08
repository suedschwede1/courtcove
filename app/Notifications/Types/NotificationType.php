<?php

declare(strict_types=1);

namespace App\Notifications\Types;

use App\Models\NotificationConsent;

/** Kinds of outgoing WhatsApp notifications. The value is stored in `notification_log.type`. */
enum NotificationType: string
{
    case PlayerSearchOpened = 'player_search.opened';
    case PlayerSearchClosed = 'player_search.closed';
    case AdminTest = 'whatsapp.test';

    /** Key of the template (admin option `whatsapp.template.<key>.<locale>`). */
    public function templateKey(): string
    {
        return match ($this) {
            self::PlayerSearchOpened => 'player_search_opened',
            self::PlayerSearchClosed => 'player_search_closed',
            self::AdminTest => 'test',
        };
    }

    /** Lower goes first: admin test, then new searches, then follow-ups. */
    public function priority(): int
    {
        return match ($this) {
            self::AdminTest => 10,
            self::PlayerSearchOpened => 20,
            self::PlayerSearchClosed => 30,
        };
    }

    /**
     * Consent purpose that must be active at send time; null would mean no consent is needed (no such
     * type yet). The admin test needs the admin's own player search consent, see the admin page.
     */
    public function purpose(): ?string // @phpstan-ignore return.unusedType (contract for later types)
    {
        return NotificationConsent::PURPOSE_PLAYER_SEARCH;
    }

    /** The admin test skips the member rules of the gate and the daily limit. */
    public function isAdminTest(): bool
    {
        return $this === self::AdminTest;
    }
}
