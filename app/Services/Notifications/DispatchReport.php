<?php

declare(strict_types=1);

namespace App\Services\Notifications;

/** Counters of one dispatcher run; no numbers, no texts. */
final class DispatchReport
{
    public int $sent = 0;

    public int $failed = 0;

    public int $retried = 0;

    public int $stale = 0;

    /** @var array<string, int> skip reason => count */
    public array $skipped = [];

    /** Why the run stopped early (`paused`, `auth_failed`), null when it ran to its limits. */
    public ?string $stoppedReason = null;

    public function skip(string $reason): void
    {
        $this->skipped[$reason] = ($this->skipped[$reason] ?? 0) + 1;
    }

    public function processed(): int
    {
        return $this->sent + $this->failed + $this->retried + array_sum($this->skipped);
    }
}
