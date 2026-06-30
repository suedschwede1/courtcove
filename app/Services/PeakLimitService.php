<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Option;
use Carbon\Carbon;

final class PeakLimitService
{
    public function isEnabled(): bool
    {
        return Option::getValue('peak_limit.enabled', '0') === '1';
    }

    /** @return list<array{start: string, end: string}> */
    public function windows(): array
    {
        return [
            [
                'start' => Option::getValue('peak_limit.window_1_start', config('booking.peak_limit.window_1_start', '08:00')),
                'end'   => Option::getValue('peak_limit.window_1_end',   config('booking.peak_limit.window_1_end',   '12:00')),
            ],
            [
                'start' => Option::getValue('peak_limit.window_2_start', config('booking.peak_limit.window_2_start', '17:00')),
                'end'   => Option::getValue('peak_limit.window_2_end',   config('booking.peak_limit.window_2_end',   '21:00')),
            ],
        ];
    }

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
