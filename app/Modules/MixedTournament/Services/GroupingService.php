<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Services;

use InvalidArgumentException;

/**
 * Divides the participants of a round into groups and improves the proposal
 * using the mixing history and the opponent blocks.
 *
 * Framework-free: takes ready-made arrays (no database access) so the
 * assignment logic can be tested without a database. Randomness is steered
 * through injectable callables so tests stay deterministic.
 *
 * @phpstan-type Participant array{id: int|string, gender?: string|null, itn?: float|int|null, age?: int|null}
 * @phpstan-type History array{partners: array<string, int>, opponents: array<string, int>}
 * @phpstan-type AvoidPair array{a: int|string, b: int|string}
 */
final class GroupingService
{
    public function __construct(
        private readonly RotationService $rotation,
        private readonly HistoryService $history,
    ) {}

    /**
     * Group sizes for a round.
     *
     * '4_and_5' looks for the smallest number of groups of 5 for which the rest
     * works out exactly. If none is found (e.g. 6 or 7 participants) it behaves
     * like '4_only' - the rest (1-3 players) is turned into a whole additional
     * group of 4 via formExtraGroup() instead of forming an odd group.
     *
     * @return array{sizes: list<int>, leftover: int}
     */
    public function groupSizes(int $totalCount, string $mode): array
    {
        if ($totalCount < 4) {
            throw new InvalidArgumentException('at least 4 participants are needed');
        }

        if ($mode === '4_and_5') {
            $maxFives = (int) floor($totalCount / 5);

            for ($fives = 0; $fives <= $maxFives; $fives++) {
                $rest = $totalCount - ($fives * 5);

                if ($rest >= 0 && $rest % 4 === 0) {
                    $sizes = array_fill(0, $fives, 5);

                    for ($i = 0; $i < $rest / 4; $i++) {
                        $sizes[] = 4;
                    }

                    return ['sizes' => $sizes, 'leftover' => 0];
                }
            }
        }

        $fours = (int) floor($totalCount / 4);

        return [
            'sizes' => array_fill(0, $fours, 4),
            'leftover' => $totalCount - ($fours * 4),
        ];
    }

    /**
     * Initial distribution of the participants over the groups.
     *
     * 'random' and 'ladies_apart' use the snake distribution (fillSnake) - the
     * only difference is the order in which participants enter the queue. For
     * 'ladies_apart' the women come first in the queue (shuffled among
     * themselves) so that they are spread over the groups before the men are
     * filled in - a heuristic, no guarantee of a perfectly even spread with an
     * odd number of women.
     *
     * 'itn' and 'itn_balanced' both sort by ITN descending but distribute the
     * sorted list differently over the groups:
     *   - 'itn': fillConsecutive() - players of similar strength END UP IN THE
     *     SAME group (group 0 = strongest players, group 1 = next strength
     *     class, ...) - close, balanced matches WITHIN each group.
     *   - 'itn_balanced': fillSnake() - strength is spread EVENLY OVER the
     *     groups (no group is stronger on average than another) - this was the
     *     behaviour of 'itn' until 'itn' was introduced in its current form.
     *
     * 'age' works like 'itn': sorted by age (youngest first, unknown age counts
     * as "oldest" and ends up last), then distributed block-wise
     * (fillConsecutive) - group 0 gets the youngest players, group 1 the next
     * age class, etc.
     *
     * @param  list<Participant>  $participants  per entry: id, gender ('m'/'f'/null), itn (float/null), age (int/null)
     * @param  list<int>  $sizes  from groupSizes()['sizes']
     * @param  string  $mode  'random' | 'itn' | 'itn_balanced' | 'age' | 'ladies_apart'
     * @param  callable(array<int, mixed>): array<int, mixed>  $shuffle  array $items -> array (for deterministic tests)
     * @return list<list<int>> list of groups, each a list of participant ids
     */
    public function initialAssignment(array $participants, array $sizes, string $mode, callable $shuffle): array
    {
        if ($mode === 'ladies_apart') {
            $women = array_values(array_filter($participants, fn ($p) => $p['gender'] === 'f'));
            $men = array_values(array_filter($participants, fn ($p) => $p['gender'] !== 'f'));

            return $this->fillSnake(array_merge($shuffle($women), $shuffle($men)), $sizes);
        }

        if ($mode === 'itn' || $mode === 'itn_balanced') {
            $ordered = $participants;
            usort($ordered, function ($a, $b) {
                $itnA = $a['itn'] !== null ? $a['itn'] : 0;
                $itnB = $b['itn'] !== null ? $b['itn'] : 0;

                return $itnB <=> $itnA;
            });

            return $mode === 'itn' ? $this->fillConsecutive($ordered, $sizes) : $this->fillSnake($ordered, $sizes);
        }

        if ($mode === 'age') {
            $ordered = $participants;
            usort($ordered, function ($a, $b) {
                $ageA = $a['age'] ?? PHP_INT_MAX;
                $ageB = $b['age'] ?? PHP_INT_MAX;

                return $ageA <=> $ageB;
            });

            return $this->fillConsecutive($ordered, $sizes);
        }

        return $this->fillSnake($shuffle($participants), $sizes);
    }

    /**
     * Builds a whole additional group of 4 from 1-3 leftover players (4_only
     * mode): the leftover players plus (4 minus leftover count) fillers.
     *
     * Every filler comes from a DIFFERENT, already formed group - never two
     * from the same one. A group of 4 always needs all 4 members at the same
     * time; two fillers missing at once would block that group's own match. The
     * fillers therefore effectively play twice in this round (home group +
     * extra group).
     *
     * $excludedFillerIds (participants with no_extra_match=1) are skipped when
     * choosing fillers - per group the first NOT excluded member is taken, so
     * not necessarily the first member of the group.
     *
     * Throws if there are not enough different groups with at least one
     * eligible member for the required fillers - better a visible error in
     * round generation than a group whose own matches would be blocked by two
     * members borrowed at the same time.
     *
     * @param  list<list<int>>  $groups
     * @param  array<int, int>  $leftoverIds
     * @param  list<int>  $excludedFillerIds
     * @return array{group: list<int>, doubled: list<int>} group = list of 4 participant ids, doubled = ids of the
     *                                                     fillers who play twice this round
     */
    public function formExtraGroup(array $groups, array $leftoverIds, array $excludedFillerIds = []): array
    {
        $leftoverIds = array_values($leftoverIds);
        $neededFillers = 4 - count($leftoverIds);

        $fillers = [];

        foreach ($groups as $group) {
            if (count($fillers) >= $neededFillers) {
                break;
            }

            foreach ($group as $memberId) {
                if (! in_array($memberId, $excludedFillerIds, true)) {
                    $fillers[] = $memberId;
                    break;
                }
            }
        }

        if (count($fillers) < $neededFillers) {
            throw new InvalidArgumentException(
                'not enough eligible groups to fill the extra group without doubling a source group'
            );
        }

        return ['group' => array_merge($leftoverIds, $fillers), 'doubled' => $fillers];
    }

    /**
     * Scores a grouping: the higher, the more repeats from the history and
     * opponent blocks it contains. 0 is ideal.
     *
     * Marked opponent blocks deliberately weigh far more (1000) than any repeat
     * penalty from the history (a few dozen encounters at most on a tournament
     * day) - so an improvement on the blocks always beats an improvement on the
     * mixing when in doubt.
     *
     * @param  list<list<int>>  $groups
     * @param  History  $history
     * @param  list<AvoidPair>  $avoidPairs
     */
    public function penalty(array $groups, array $history, array $avoidPairs): int
    {
        $penalty = 0;

        foreach ($groups as $group) {
            foreach ($this->rotation->matches(count($group)) as $match) {
                $a1 = $group[$match['teamA'][0]];
                $a2 = $group[$match['teamA'][1]];
                $b1 = $group[$match['teamB'][0]];
                $b2 = $group[$match['teamB'][1]];

                $penalty += $this->pairCount($history['partners'], $a1, $a2);
                $penalty += $this->pairCount($history['partners'], $b1, $b2);

                foreach ([$a1, $a2] as $a) {
                    foreach ([$b1, $b2] as $b) {
                        $penalty += $this->pairCount($history['opponents'], $a, $b);

                        if ($this->isAvoided($avoidPairs, $a, $b)) {
                            $penalty += 1000;
                        }
                    }
                }
            }
        }

        return $penalty;
    }

    /**
     * Improves a grouping by limited random swapping of one player each between
     * two groups (hill climbing: a swap is only accepted if it lowers the
     * penalty). Stops early as soon as the penalty reaches 0. $randomInt is
     * injectable so tests stay deterministic; in production the caller wraps
     * the randomizer in it.
     *
     * Call protocol per iteration: randomInt(0, g-1) twice for the groups,
     * `continue` (iteration consumed) when they are equal, else
     * randomInt(0, |gi|-1) and randomInt(0, |gj|-1).
     *
     * @param  list<list<int>>  $groups
     * @param  History  $history
     * @param  list<AvoidPair>  $avoidPairs
     * @param  callable(int, int): int  $randomInt
     * @return list<list<int>>
     */
    public function repair(array $groups, array $history, array $avoidPairs, callable $randomInt, int $maxIterations = 200): array
    {
        $best = $groups;
        $bestPenalty = $this->penalty($best, $history, $avoidPairs);
        $groupKeys = array_keys($best);

        if (count($groupKeys) < 2) {
            return $best;
        }

        for ($i = 0; $i < $maxIterations && $bestPenalty > 0; $i++) {
            $gi = $groupKeys[$randomInt(0, count($groupKeys) - 1)];
            $gj = $groupKeys[$randomInt(0, count($groupKeys) - 1)];

            if ($gi === $gj) {
                continue;
            }

            $pi = $randomInt(0, count($best[$gi]) - 1);
            $pj = $randomInt(0, count($best[$gj]) - 1);

            $candidate = $best;
            $tmp = $candidate[$gi][$pi];
            $candidate[$gi][$pi] = $candidate[$gj][$pj];
            $candidate[$gj][$pj] = $tmp;

            $candidatePenalty = $this->penalty($candidate, $history, $avoidPairs);

            if ($candidatePenalty < $bestPenalty) {
                $best = $candidate;
                $bestPenalty = $candidatePenalty;
            }
        }

        return $best;
    }

    /**
     * Distributes an ordered participant list block-wise over the groups -
     * group 0 gets the first $sizes[0] entries, group 1 the next $sizes[1], etc.
     * For 'itn'/'age': players of similar strength/age thereby play against
     * each other instead of against clearly stronger/weaker or younger/older
     * participants.
     *
     * @param  list<Participant>  $ordered
     * @param  list<int>  $sizes
     * @return list<list<int>> list of groups, each a list of participant ids
     */
    private function fillConsecutive(array $ordered, array $sizes): array
    {
        $groups = [];
        $offset = 0;

        foreach ($sizes as $size) {
            $groups[] = array_map(fn ($p) => $p['id'], array_slice($ordered, $offset, $size));
            $offset += $size;
        }

        return $groups;
    }

    /**
     * Distributes an ordered participant list in a snake pattern (0,1,...,n-1,
     * n-1,...,0,0,...) over the groups, taking their (possibly different) sizes
     * into account.
     *
     * @param  array<int, Participant>  $ordered
     * @param  list<int>  $sizes
     * @return list<list<int>> list of groups, each a list of participant ids
     */
    private function fillSnake(array $ordered, array $sizes): array
    {
        $groupCount = count($sizes);
        $groups = array_fill(0, $groupCount, []);
        $queue = array_values($ordered);
        $forward = true;

        while ($queue) {
            $order = $forward ? range(0, $groupCount - 1) : range($groupCount - 1, 0);

            foreach ($order as $g) {
                if (! $queue) {
                    break;
                }

                if (count($groups[$g]) >= $sizes[$g]) {
                    continue;
                }

                $next = array_shift($queue);
                $groups[$g][] = $next['id'];
            }

            $forward = ! $forward;
        }

        return $groups;
    }

    /** @param  array<string, int>  $counts */
    private function pairCount(array $counts, int|string $a, int|string $b): int
    {
        $key = $this->history->key($a, $b);

        return $counts[$key] ?? 0;
    }

    /** @param  list<AvoidPair>  $avoidPairs */
    private function isAvoided(array $avoidPairs, int|string $a, int|string $b): bool
    {
        foreach ($avoidPairs as $pair) {
            if (($pair['a'] == $a && $pair['b'] == $b) || ($pair['a'] == $b && $pair['b'] == $a)) {
                return true;
            }
        }

        return false;
    }
}
