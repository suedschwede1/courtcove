<?php

declare(strict_types=1);

namespace App\Support\Calendar;

use App\Models\Event;

/**
 * What an event means for the calendar: a club event, or a court closure that
 * the grid draws hatched. The admin form stores the kind as event meta "kind";
 * older events have none, so their name decides (see config calendar.closure_keywords).
 */
enum EventKind: string
{
    case Event = 'event';
    case Closure = 'closure';

    /** Reads the already loaded meta relation, so the calendar causes no extra queries. */
    public static function for(Event $event, ?string $name = null): self
    {
        $stored = $event->meta->firstWhere('key', 'kind')?->value;
        if (is_string($stored) && ($kind = self::tryFrom($stored)) !== null) {
            return $kind;
        }

        $name ??= (string) $event->meta->where('key', 'name')->pluck('value')->first();
        foreach ((array) config('calendar.closure_keywords', []) as $keyword) {
            if (is_string($keyword) && $keyword !== '' && mb_stripos($name, $keyword) !== false) {
                return self::Closure;
            }
        }

        return self::Event;
    }
}
