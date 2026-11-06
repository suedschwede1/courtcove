<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Draw\Contracts\BeaconService;
use App\Modules\Draw\Services\RoundNotFutureException;
use RuntimeException;

/**
 * In-memory beacon for tests: same round maths as the drand client, the clock is now()
 * (Date::setTestNow), rounds are published by hand. `unreachable` makes every call that
 * needs the chain throw like a dead network.
 */
final class FakeBeaconService implements BeaconService
{
    public bool $unreachable = false;

    /**
     * @param  array<int, string>  $published  round => randomness
     */
    public function __construct(
        private readonly int $genesis = 1700000000,
        private readonly int $period = 30,
        private array $published = [],
    ) {}

    public function publish(int $round, string $randomness): void
    {
        $this->published[$round] = $randomness;
    }

    public function info(): array
    {
        if ($this->unreachable) {
            throw new RuntimeException('no drand endpoint reachable');
        }

        return ['genesis_time' => $this->genesis, 'period' => $this->period];
    }

    public function roundTime(int $round): int
    {
        $this->info();

        return $this->genesis + ($round - 1) * $this->period;
    }

    public function roundAt(int $timestamp): int
    {
        $this->info();

        if ($timestamp < $this->genesis) {
            throw new RuntimeException('timestamp is before the chain genesis');
        }

        return (int) floor(($timestamp - $this->genesis) / $this->period) + 1;
    }

    public function assertFutureRound(int $round): true
    {
        $current = $this->roundAt(now()->getTimestamp());

        if ($round <= $current) {
            throw new RoundNotFutureException($round, $current);
        }

        return true;
    }

    public function round(int $round): ?array
    {
        if ($this->unreachable || ! isset($this->published[$round])) {
            return null;
        }

        return ['round' => $round, 'randomness' => $this->published[$round], 'signature' => ''];
    }

    public function sourceLabel(int $round): string
    {
        return 'drand:round:'.$round;
    }
}
