<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Services;

use App\Modules\Pyramid\Support\Verdict;
use Carbon\CarbonImmutable;

/**
 * Parses user-typed dates like the old controllers did: strtotime() relative to "now".
 *
 * Accepts "14.08.2026 17:00", "2026-08-14T17:00" (datetime-local) and "2026-08-14".
 */
final class ScheduleParser
{
    public function forSchedule(string $raw, ?CarbonImmutable $now = null): int|Verdict
    {
        $now ??= CarbonImmutable::now();
        $base = $now->getTimestamp();
        $when = strtotime(trim($raw), $base);

        if (! $when) {
            return new Verdict('pyramid::check.schedule_invalid');
        }

        if ($when < $base) {
            return new Verdict('pyramid::check.schedule_past');
        }

        // A date far ahead is a typo in the year, not a plan.
        if ($when > strtotime('+1 year', $base)) {
            return new Verdict('pyramid::check.schedule_far');
        }

        return $when;
    }

    /**
     * End of the given day; there is no past check (an admin may correct an old deadline).
     */
    public function forDeadline(string $rawDate, ?CarbonImmutable $now = null): int|Verdict
    {
        $now ??= CarbonImmutable::now();
        $base = $now->getTimestamp();
        $when = strtotime(trim($rawDate).' 23:59', $base);

        if (! $when) {
            return new Verdict('pyramid::check.deadline_invalid');
        }

        if ($when > strtotime('+1 year', $base)) {
            return new Verdict('pyramid::check.deadline_far');
        }

        return $when;
    }
}
