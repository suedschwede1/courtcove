<?php

declare(strict_types=1);

namespace App\Events;

use App\Services\Notifications\PlayerSearchEvents;

/**
 * A player search was opened and its booking committed. Fired after the response (in the console at once)
 * by {@see PlayerSearchEvents}; carries the ID only and has no effect on the
 * exchange mail.
 */
final class PlayerSearchStarted
{
    public function __construct(public readonly int $searchId) {}
}
