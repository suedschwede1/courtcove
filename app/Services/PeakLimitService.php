<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Option;
use App\Models\Square;
use Carbon\Carbon;

/**
 * Peak-time limit. Everything is global (Administration > Konfiguration > Verhalten): the switch
 * `peak_limit.enabled`, the two windows `peak_limit.window_{1,2}_{start,end}` and the limit
 * `peak_limit.max`. A court only switches the limit on for itself (square meta `peak_limit_enabled`).
 */
final class PeakLimitService
{
    public function isEnabled(): bool
    {
        return Option::getValue('peak_limit.enabled', '0') === '1';
    }

    /** Whether the peak limit is in effect for bookings on this court (global switch and court switch). */
    public function appliesTo(Square $square): bool
    {
        return $this->isEnabled() && $square->getMeta('peak_limit_enabled') === '1';
    }

    /**
     * Maximum future bookings of one person that start inside a peak window, across all courts.
     * `peak_limit.max` when set above 0; otherwise the global `booking.max_active_bookings` (so data from
     * before the separate option keeps its behaviour); 0 = no peak limit.
     */
    public function limit(): int
    {
        $own = (int) Option::getValue('peak_limit.max', '0');
        if ($own > 0) {
            return $own;
        }

        return max(0, (int) Option::getValue('booking.max_active_bookings', '0'));
    }

    /** @return list<array{start: string, end: string}> */
    public function windows(): array
    {
        return [
            [
                'start' => Option::getValue('peak_limit.window_1_start', config('booking.peak_limit.window_1_start', '08:00')),
                'end' => Option::getValue('peak_limit.window_1_end', config('booking.peak_limit.window_1_end', '12:00')),
            ],
            [
                'start' => Option::getValue('peak_limit.window_2_start', config('booking.peak_limit.window_2_start', '17:00')),
                'end' => Option::getValue('peak_limit.window_2_end', config('booking.peak_limit.window_2_end', '21:00')),
            ],
        ];
    }

    /** A window includes its start and excludes its end (08:00-12:00: 08:00 and 11:00 yes, 12:00 no). */
    public function isPeakTime(Carbon $dateStart): bool
    {
        $hhmm = $dateStart->format('H:i');
        foreach ($this->windows() as $window) {
            if ($hhmm >= $window['start'] && $hhmm < $window['end']) {
                return true;
            }
        }

        return false;
    }
}
