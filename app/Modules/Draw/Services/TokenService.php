<?php

declare(strict_types=1);

namespace App\Modules\Draw\Services;

use RuntimeException;

/**
 * Tokens of a draw: create, pick, close, assign.
 *
 * Lifecycle:
 *
 *   committed  tokens exist but mean nothing yet - not even to the admin
 *   picking    eligible players each pick one free token
 *   closed     picking phase over, the mapping uid -> token is published
 *   executed   the beacon is known, the SLOT list is shuffled, token i gets slot perm[i]
 *
 * The slot list is shuffled, not the token list. That keeps token 1 always
 * token 1 and the display easy to follow - it only receives a random slot.
 *
 * This class is deliberately free of framework dependencies: it computes and
 * returns records, writing is left to the callers.
 */
final class TokenService
{
    /**
     * Token identifiers for a draw.
     *
     * Fixed order, NOT shuffled per user - otherwise it would be unclear
     * afterwards what "the third token" was.
     *
     * @return list<array{position:int, token:string}>
     */
    public function tokenNames(int $count, string $prefix = 'T'): array
    {
        $out = [];

        for ($i = 1; $i <= $count; $i++) {
            $out[] = [
                'position' => $i,
                'token' => sprintf('%s-%02u', $prefix, $i),
            ];
        }

        return $out;
    }

    /**
     * Distributes the still free tokens over the eligible players who did not
     * pick, after the deadline.
     *
     * This is not a fairness problem, but HOW it is done matters:
     *
     * Formerly the non-pickers were sorted by participant number and the free
     * tokens were handed out by position. That was harmless as long as the draw
     * only ran AFTER the picking phase - nobody knew what a token meant.
     *
     * Now that the draw runs earlier, the tournament director knows the mapping
     * at closing time. A distribution sorted by tpid would be predictable AND
     * steerable: whoever still takes a specific token shifts the pairing of the
     * others.
     *
     * Therefore the list of non-pickers is shuffled with the same finalSeed that
     * determined the draw. This stays fully reproducible - anyone can recompute
     * it from the log - but hangs on a number nobody can influence.
     *
     * @param  array  $freeTokens  token rows with dtid and position
     * @param  array  $nonPickers  eligible players without a token, each with tpid, uid
     * @param  string|null  $finalSeed  hex seed of the draw; empty = old, sorted
     *                                  distribution (only for draws without a seed)
     * @param  DrawService|null  $drawService  for the reproducible shuffle
     * @return list<array{dtid:int, tpid:int, uid:?int}>
     */
    public function fallbackAssignment(
        array $freeTokens,
        array $nonPickers,
        ?string $finalSeed = null,
        ?DrawService $drawService = null,
        array $partnerMap = [],
        array $fixedGroups = [],
    ): array {
        usort($freeTokens, function ($a, $b) {
            return (int) $a['position'] - (int) $b['position'];
        });

        /* Order deterministically first, so that the shuffle starts from a
           defined state - otherwise the result would depend on the order in
           which the database returns the rows. */

        usort($nonPickers, function ($a, $b) {
            return (int) $a['tpid'] - (int) $b['tpid'];
        });

        if ($finalSeed && $drawService) {
            $nonPickers = $drawService->shuffle($nonPickers, $finalSeed);
        }

        if (count($nonPickers) > count($freeTokens)) {
            throw new RuntimeException(sprintf(
                'more players without a token (%u) than free tokens (%u) - '
                .'the token count does not match the pool',
                count($nonPickers), count($freeTokens)
            ));
        }

        /*
         * First look for a complete assignment that puts no partner pair into
         * the same group. The small pot allows backtracking and keeps an early
         * greedy choice from needlessly blocking later players. If there is no
         * conflict-free overall solution, the whole rule is not dropped: the
         * search minimises the number of conflicts, so it only gives way where a
         * complete assignment makes it unavoidable.
         */
        $solution = [];
        $used = [];
        $assignedGroups = [];
        $bestSolution = [];
        $bestConflicts = PHP_INT_MAX;

        $search = function ($assignedCount, $conflicts) use (&$search, &$solution, &$used,
            &$assignedGroups, &$bestSolution, &$bestConflicts, $nonPickers, $freeTokens,
            $partnerMap, $fixedGroups) {
            if ($conflicts >= $bestConflicts) {
                return;
            }

            if ($assignedCount >= count($nonPickers)) {
                $bestConflicts = $conflicts;
                $bestSolution = $solution;

                return;
            }

            /* Handle the currently most constrained player first. This avoids a
               factorial search when only the last player has no conflict-free
               group left. */
            $playerIndex = null;
            $fewestClean = PHP_INT_MAX;

            foreach ($nonPickers as $index => $player) {
                if (isset($solution[$index])) {
                    continue;
                }

                $tpid = (int) $player['tpid'];
                $partner = isset($partnerMap[$tpid]) ? (int) $partnerMap[$tpid] : 0;
                $clean = 0;

                foreach ($freeTokens as $tokenIndex => $token) {
                    if (isset($used[$tokenIndex])) {
                        continue;
                    }

                    $group = isset($token['group']) ? (string) $token['group'] : '';
                    $isConflict = $partner && $group !== ''
                        && ((isset($fixedGroups[$partner]) && $fixedGroups[$partner] === $group)
                            || (isset($assignedGroups[$partner])
                                && $assignedGroups[$partner] === $group));

                    if (! $isConflict) {
                        $clean++;
                    }
                }

                if ($clean < $fewestClean) {
                    $fewestClean = $clean;
                    $playerIndex = $index;
                }
            }

            $player = $nonPickers[$playerIndex];
            $tpid = (int) $player['tpid'];
            $partner = isset($partnerMap[$tpid]) ? (int) $partnerMap[$tpid] : 0;
            $candidates = [];

            foreach ($freeTokens as $tokenIndex => $token) {
                if (isset($used[$tokenIndex])) {
                    continue;
                }

                $group = isset($token['group']) ? (string) $token['group'] : '';
                $isConflict = $partner && $group !== ''
                    && ((isset($fixedGroups[$partner]) && $fixedGroups[$partner] === $group)
                        || (isset($assignedGroups[$partner])
                            && $assignedGroups[$partner] === $group));

                $candidates[] = [
                    'index' => $tokenIndex,
                    'token' => $token,
                    'group' => $group,
                    'conflict' => $isConflict ? 1 : 0,
                ];
            }

            usort($candidates, function ($a, $b) {
                if ($a['conflict'] !== $b['conflict']) {
                    return $a['conflict'] - $b['conflict'];
                }

                return $a['index'] - $b['index'];
            });

            foreach ($candidates as $candidate) {
                $used[$candidate['index']] = true;
                $assignedGroups[$tpid] = $candidate['group'];
                $solution[$playerIndex] = $candidate['token'];

                $search($assignedCount + 1, $conflicts + $candidate['conflict']);

                unset($used[$candidate['index']], $assignedGroups[$tpid],
                    $solution[$playerIndex]);

                if ($bestConflicts === 0) {
                    return;
                }
            }
        };

        $search(0, 0);
        $out = [];

        foreach ($nonPickers as $index => $player) {
            $token = $bestSolution[$index];

            $out[] = [
                'dtid' => (int) $token['dtid'],
                'tpid' => (int) $player['tpid'],
                'uid' => isset($player['uid']) ? (int) $player['uid'] : null,
            ];
        }

        return $out;
    }

    /**
     * Assigns tokens to slots - the result of the draw.
     *
     * @param  array  $tokens  token rows, sorted by position
     * @param  array  $shuffledSlots  slot list in shuffled order
     * @return array<int, string> dtid => slot
     */
    public function assignSlots(array $tokens, array $shuffledSlots): array
    {
        usort($tokens, function ($a, $b) {
            return (int) $a['position'] - (int) $b['position'];
        });

        if (count($tokens) !== count($shuffledSlots)) {
            throw new RuntimeException(sprintf(
                'tokens (%u) and slots (%u) must match', count($tokens), count($shuffledSlots)
            ));
        }

        $out = [];

        foreach ($tokens as $index => $token) {
            $out[(int) $token['dtid']] = $shuffledSlots[$index];
        }

        return $out;
    }

    /**
     * Payload of a token draw.
     *
     * The lots are the token identifiers, not the players - at commit time it is
     * not yet known who takes which token. That is the point: a token only gets
     * its meaning after the picking.
     *
     * @return array{entries:list<string>, slots:list<string>, rule:string}
     */
    public function payload(array $tokenNames, array $slots, string $rule): array
    {
        $entries = [];

        foreach ($tokenNames as $token) {
            $entries[] = $token['token'];
        }

        return [
            'entries' => $entries,
            'slots' => array_values($slots),
            'rule' => $rule,
        ];
    }
}
