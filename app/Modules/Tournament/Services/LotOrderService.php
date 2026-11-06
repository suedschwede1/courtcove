<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Services\DrawService;
use App\Modules\Tournament\Models\Tournament;

/**
 * Lot decision for ties that no criterion can separate (the lot part of ZF2's TournamentManager).
 *
 * A manual entry in lot_orders[scope] (an array, not empty) always wins while it fits the tie (exactly the
 * needs_lot tpids); a stale entry is ignored and reported through staleScopes(). Otherwise the order is derived
 * from the seed of the draw that was published before the group phase, so there is no second draw:
 *
 *   scopeSeed = HMAC-SHA256(key = lot seed, message = scope)
 *   order     = DrawService::shuffle(tied tpids sorted ascending, scopeSeed)
 *
 * The lot seed is the final_seed of the executed, not discarded draw with the LOWEST did of the
 * tournament. Later draws can never change a tie that is already decided. Without such a draw nothing
 * is derived: the tie stays flagged needs_lot for the entry by hand, better than a coin nobody can
 * check. Draw maths stays in DrawService.
 */
final class LotOrderService
{
    /** @var array<int, string> lot seed per tid (only found seeds are cached, as in ZF2) */
    private array $seeds = [];

    /** @var array<int, array<string, true>> manual entries found not to fit their tie, per tid */
    private array $stale = [];

    public function __construct(private readonly DrawService $draws) {}

    /**
     * Manual lot results by scope; anything that does not decode to an array gives an empty list.
     *
     * @return array<string, mixed>
     */
    public function lotOrders(Tournament $t): array
    {
        return $t->lotOrdersArray();
    }

    /**
     * Lot decision for a scope: a manual entry wins, otherwise derived.
     *
     * @param  array<int, array<string, mixed>>  $rows  ranking rows that may carry needs_lot
     * @return array<int, int|string>
     */
    public function lotOrder(Tournament $t, string $scope, array $rows = []): array
    {
        $orders = $this->lotOrders($t);

        // A manual entry has priority: if the lot was drawn on site, that counts and not the calculation.
        // It applies only while it still fits the tie; a stale entry is ignored (and reported to the
        // admin page) instead of breaking every table that shows the scope.
        if (isset($orders[$scope]) && is_array($orders[$scope]) && $orders[$scope]) {
            if (StandingsService::lotOrderFits($rows, $orders[$scope])) {
                return $orders[$scope];
            }

            $this->stale[(int) $t->tid][$scope] = true;
        }

        return $this->derivedLotOrder($t, $scope, $rows);
    }

    /**
     * Derives the lot decision from the published seed of the first executed draw.
     *
     * Why this needs no new draw: for the group draw the drand round has to lie in the future, otherwise
     * the result could be computed in advance and one could wait for a fitting round. Here the result is a
     * FUNCTION of the already published seed and the context. Calling again changes nothing, so there is
     * nothing to try. The decision is immediate, recomputable (seed in the protocol, context in the
     * table), not steerable (the seed was fixed before the tie existed) and repeatable.
     *
     * @param  array<int, array<string, mixed>>  $rows  ranking rows that may carry needs_lot
     * @return list<int> ordered tpid list, empty when nothing can be derived
     */
    public function derivedLotOrder(Tournament $t, string $scope, array $rows): array
    {
        $tied = [];

        foreach ($rows as $row) {
            if (! empty($row['needs_lot'])) {
                $tied[] = (int) $row['tpid'];
            }
        }

        if (count($tied) < 2) {
            return [];
        }

        $seed = $this->lotSeed($t);

        if (! $seed) {
            // Without a published seed there is nothing to recompute: the tie stays needs_lot and the
            // entry by hand applies.
            return [];
        }

        // Order deterministically first, so that shuffling starts from a defined state.
        sort($tied);

        return $this->draws->shuffle($tied, hash_hmac('sha256', $scope, $seed));
    }

    /**
     * The seed all lot decisions of this tournament derive from: the final_seed of the FIRST executed,
     * not discarded draw. The lowest did is searched explicitly over all rows, so the order in which the
     * database returns rows does not matter. If the tournament is reset completely and the first draw is
     * discarded with it, the first draw of the new run is the new starting value.
     */
    public function lotSeed(Tournament $t): ?string
    {
        $tid = (int) $t->tid;

        if (isset($this->seeds[$tid])) {
            return $this->seeds[$tid];
        }

        $firstDid = null;
        $seed = null;

        foreach (Draw::query()->where('tid', $tid)->get(['did', 'final_seed', 'discarded']) as $row) {
            $did = (int) $row->did;

            if ($row->final_seed && ! $row->discarded
                && ($firstDid === null || $did < $firstDid)) {
                $firstDid = $did;
                $seed = (string) $row->final_seed;
            }
        }

        if ($seed !== null) {
            $this->seeds[$tid] = $seed;
        }

        return $seed;
    }

    /**
     * Scopes whose manual entry was ignored in this request because it no longer fits the tie.
     *
     * @return list<string>
     */
    public function staleScopes(Tournament $t): array
    {
        return array_keys($this->stale[(int) $t->tid] ?? []);
    }

    /** Drops the cached seed of a tournament (after a draw was executed, discarded or reset). */
    public function forget(int $tid): void
    {
        unset($this->seeds[$tid], $this->stale[$tid]);
    }
}
