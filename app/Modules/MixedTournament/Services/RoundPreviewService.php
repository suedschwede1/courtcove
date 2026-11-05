<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Services;

/**
 * Warnings for a round proposal (old MixedManager::roundPreview() body):
 * repeated encounters, avoid-pair collisions and the match count per player.
 * Framework-free; the DB-bound caller passes the matches of the round in
 * round order, the matches of the other confirmed rounds and the avoid pairs.
 */
final class RoundPreviewService
{
    public function __construct(private readonly HistoryService $history) {}

    /**
     * Partners and opponents are deliberately NOT counted separately: in both
     * rotations every pair of a group is partners exactly once and opponents
     * exactly twice, so "together or against each other" follows from being
     * in the same group. `previous` therefore counts earlier SHARED GROUPS,
     * measured exactly by the partner history (one shared group = one
     * partnership).
     *
     * Only from the SECOND repeat on: meeting someone once again is
     * unavoidable with a dozen participants and several rounds. Most frequent
     * repeat first, ties by participant id so two calls show the same order.
     *
     * @param  list<array{team_a_p1: int|string, team_a_p2: int|string, team_b_p1: int|string, team_b_p2: int|string}>  $roundMatches  in round order
     * @param  list<array{team_a_p1: int|string, team_a_p2: int|string, team_b_p1: int|string, team_b_p2: int|string}>  $pastMatches  other confirmed/completed rounds
     * @param  list<array{a: int|string, b: int|string}>  $avoidPairs
     * @return array{repeated_encounters: list<array{players: array{int, int}, previous: int}>, avoid_collisions: list<array{int, int}>, match_counts: array<int, int>}
     */
    public function preview(array $roundMatches, array $pastMatches, array $avoidPairs): array
    {
        $history = $this->history->build($pastMatches);

        $avoid = [];
        foreach ($avoidPairs as $pair) {
            $avoid[$this->history->key($pair['a'], $pair['b'])] = true;
        }

        $encounters = [];
        $collisions = [];
        $matchCounts = [];

        foreach ($roundMatches as $match) {
            $teamA = [(int) $match['team_a_p1'], (int) $match['team_a_p2']];
            $teamB = [(int) $match['team_b_p1'], (int) $match['team_b_p2']];
            $all = array_merge($teamA, $teamB);

            foreach ($all as $id) {
                $matchCounts[$id] = isset($matchCounts[$id]) ? $matchCounts[$id] + 1 : 1;
            }

            foreach ($all as $i => $a) {
                foreach (array_slice($all, $i + 1) as $b) {
                    $key = $this->history->key($a, $b);

                    if (! isset($encounters[$key])) {
                        $encounters[$key] = [
                            'players' => [$a, $b],
                            'previous' => $history['partners'][$key] ?? 0,
                        ];
                    }

                    if (isset($avoid[$key]) && in_array($a, $teamA, true) !== in_array($b, $teamA, true)) {
                        $collisions[$key] = [$a, $b];
                    }
                }
            }
        }

        $repeated = array_values(array_filter($encounters, fn (array $pair): bool => $pair['previous'] > 1));

        usort($repeated, function (array $a, array $b): int {
            if ($a['previous'] !== $b['previous']) {
                return $b['previous'] - $a['previous'];
            }

            if ($a['players'][0] !== $b['players'][0]) {
                return $a['players'][0] - $b['players'][0];
            }

            return $a['players'][1] - $b['players'][1];
        });

        return [
            'repeated_encounters' => $repeated,
            'avoid_collisions' => array_values($collisions),
            'match_counts' => $matchCounts,
        ];
    }
}
