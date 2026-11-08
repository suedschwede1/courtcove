<?php

declare(strict_types=1);

namespace App\Events;

/**
 * A player search stopped seeking: filled, ended by the booker, lapsed or cancelled with its booking. Fired
 * once the change has committed; carries IDs only and has no effect on the exchange mails.
 */
final class PlayerSearchClosed
{
    /** @param  string  $reason  filled|ended|lapsed|cancelled */
    public function __construct(
        public readonly int $searchId,
        public readonly string $reason,
    ) {}
}
