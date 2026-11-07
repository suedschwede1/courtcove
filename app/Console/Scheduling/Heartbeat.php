<?php

declare(strict_types=1);

namespace App\Console\Scheduling;

use App\Models\Option;
use Carbon\CarbonImmutable;

/**
 * Time of the last scheduler tick: the every-minute task "scheduler-heartbeat" stores it as one option row
 * (single UPDATE per minute; not written to the run log). The admin page shows a traffic light from its age.
 */
final class Heartbeat
{
    public const OPTION = 'scheduler.heartbeat_at';

    /** Up to this age the scheduler counts as healthy (green). */
    public const GREEN_SECONDS = 90;

    /** Older than this = cron missing or stuck (red, warning with the cron line). */
    public const RED_SECONDS = 180;

    public function beat(): void
    {
        $now = CarbonImmutable::now()->utc()->format('Y-m-d H:i:s');

        $updated = Option::query()->where('key', self::OPTION)->whereNull('locale')->update(['value' => $now]);
        if ($updated === 0 && ! Option::query()->where('key', self::OPTION)->whereNull('locale')->exists()) {
            Option::query()->create(['key' => self::OPTION, 'value' => $now, 'locale' => null]);
        }
    }

    public function lastTick(): ?CarbonImmutable
    {
        $value = Option::getValue(self::OPTION);
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d H:i:s', $value, 'UTC')->setTimezone((string) config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    /** green | yellow | red (red also when there never was a tick). */
    public function status(): string
    {
        $last = $this->lastTick();
        if ($last === null) {
            return 'red';
        }

        $age = $last->diffInSeconds(CarbonImmutable::now(), true);

        return match (true) {
            $age <= self::GREEN_SECONDS => 'green',
            $age <= self::RED_SECONDS => 'yellow',
            default => 'red',
        };
    }
}
