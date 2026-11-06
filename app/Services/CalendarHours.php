<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Option;
use App\Models\Square;

/**
 * The time grid of the booking calendar: one global start and end (Administration > Konfiguration > Verhalten),
 * default 08:00 to 20:00. A court may narrow this with its own opening hours (court form, time_start/time_end).
 */
final class CalendarHours
{
    public const KEY_START = 'calendar.time_start';

    public const KEY_END = 'calendar.time_end';

    public const DEFAULT_START = '08:00';

    public const DEFAULT_END = '20:00';

    /** Global start, HH:MM. */
    public function start(): string
    {
        return $this->read(self::KEY_START, self::DEFAULT_START);
    }

    /** Global end, HH:MM. */
    public function end(): string
    {
        return $this->read(self::KEY_END, self::DEFAULT_END);
    }

    /** Hour of the first calendar row. */
    public function firstHour(): int
    {
        return intdiv($this->minutes($this->start()), 60);
    }

    /** Hour of the last calendar row (a row covers hour to hour+1; an end of 20:30 still shows the 20:00 row). */
    public function lastHour(): int
    {
        return max($this->firstHour(), (int) ceil($this->minutes($this->end()) / 60) - 1);
    }

    /** Whether the whole hour row h to h+1 lies within the court opening hours. */
    public function isOpen(Square $square, int $hour): bool
    {
        $open = $this->minutes(substr((string) $square->time_start, 0, 5));
        $close = $this->minutes(substr((string) $square->time_end, 0, 5));
        if ($close <= $open) {
            return true;
        }

        return $hour * 60 >= $open && ($hour + 1) * 60 <= $close;
    }

    private function read(string $key, string $default): string
    {
        $value = trim((string) Option::getValue($key, ''));

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1 ? $value : $default;
    }

    private function minutes(string $time): int
    {
        if (preg_match('/^(\d{1,2}):(\d{2})/', $time, $m) !== 1) {
            return 0;
        }

        return ((int) $m[1]) * 60 + (int) $m[2];
    }
}
