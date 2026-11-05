<?php

declare(strict_types=1);

namespace App\Modules\Draw\Contracts;

use App\Modules\Draw\Services\RoundNotFutureException;

/**
 * Public randomness for the commit/reveal draw (ported from ZF2 Draw\Service\BeaconService).
 *
 * Rounds are numbered from the chain genesis in fixed periods, so a future round can be
 * announced at commit time while its value is still unknown to everyone.
 */
interface BeaconService
{
    /**
     * Chain info (genesis_time, period).
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException when no endpoint answers or the answer lacks genesis_time/period
     */
    public function info(): array;

    /**
     * Unix time at which the given round falls (inverse of roundAt).
     *
     * @throws \RuntimeException when the chain info cannot be fetched
     */
    public function roundTime(int $round): int;

    /**
     * Round number in force at the given Unix time.
     *
     * @throws \RuntimeException when the chain info cannot be fetched
     */
    public function roundAt(int $timestamp): int;

    /**
     * @throws RoundNotFutureException when the round is not after the current one
     * @throws \RuntimeException when the chain info cannot be fetched
     */
    public function assertFutureRound(int $round): true;

    /**
     * A published round, or null while it is not published (or on transport errors).
     *
     * @return array{round: int, randomness: string, signature: string}|null
     */
    public function round(int $round): ?array;

    /** Value of bs_draws.beacon_source, published with the commit. */
    public function sourceLabel(int $round): string;
}
