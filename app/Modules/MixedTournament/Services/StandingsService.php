<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Services;

/**
 * Overall standings: first points (win 2, draw 1, loss 0) on AVERAGE per
 * played match, then games won on average per match - both averages, not
 * sums, because not everybody plays the same number of matches on a
 * tournament day (extra-group fillers play one round twice, see
 * GroupingService::formExtraGroup()).
 *
 * Only players who played at most ONE round less than the player with the
 * most rounds are ranked. Counted by rounds on purpose and not by matches: a
 * filler plays twice as many matches, but within the same round - a match
 * threshold would throw the whole rest of the field out of the ranking.
 * Counted by rounds this does not matter, and the same holds for the
 * rotating sitters in Mexicano.
 *
 * Fully tied rows are ordered by participant id (a deterministic replacement
 * for the old reliance on database row order).
 */
final class StandingsService
{
    /**
     * @param  array<int, int>  $participantIds  all participant ids (also those without a scored match)
     * @param  list<array{round: int|string, team_a: array<int, int>, team_b: array<int, int>, games_a: int|null, games_b: int|null}>  $matches  `round` (round id) is the basis of the eligibility rule; games null = not played yet
     * @return list<array{id: int, points: int, played: int, games_won: int, games_avg: float, points_avg: float, rounds_played: int, eligible: bool, rounds_needed: int}>
     */
    public function standings(array $participantIds, array $matches): array
    {
        $rows = [];
        $roundsSeen = [];

        foreach ($participantIds as $id) {
            $rows[$id] = ['id' => $id, 'points' => 0, 'played' => 0, 'games_won' => 0];
            $roundsSeen[$id] = [];
        }

        foreach ($matches as $match) {
            if ($match['games_a'] === null || $match['games_b'] === null) {
                continue;
            }

            // Only scored matches count as a played round.
            foreach (array_merge($match['team_a'], $match['team_b']) as $id) {
                if (isset($roundsSeen[$id])) {
                    $roundsSeen[$id][$match['round']] = true;
                }
            }

            $this->applyTeam($rows, $match['team_a'], (int) $match['games_a'], (int) $match['games_b']);
            $this->applyTeam($rows, $match['team_b'], (int) $match['games_b'], (int) $match['games_a']);
        }

        $result = array_values($rows);

        foreach ($result as &$row) {
            $row['games_avg'] = $row['played'] > 0 ? (float) ($row['games_won'] / $row['played']) : 0.0;
            $row['points_avg'] = $row['played'] > 0 ? (float) ($row['points'] / $row['played']) : 0.0;
            $row['rounds_played'] = count($roundsSeen[$row['id']]);
        }
        unset($row);

        $maxRounds = 0;
        foreach ($result as $row) {
            $maxRounds = max($maxRounds, $row['rounds_played']);
        }
        foreach ($result as &$row) {
            $row['eligible'] = $row['rounds_played'] > 0 && $row['rounds_played'] >= $maxRounds - 1;
            $row['rounds_needed'] = $row['eligible'] ? 0 : max(1, $maxRounds - 1 - $row['rounds_played']);
        }
        unset($row);

        usort($result, function (array $a, array $b): int {
            if ($a['eligible'] !== $b['eligible']) {
                return $a['eligible'] ? -1 : 1;
            }

            return $this->compareRows($a, $b);
        });

        return $result;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function compareRows(array $a, array $b): int
    {
        if ($a['points_avg'] !== $b['points_avg']) {
            return $b['points_avg'] <=> $a['points_avg'];
        }
        if ($a['games_avg'] !== $b['games_avg']) {
            return $b['games_avg'] <=> $a['games_avg'];
        }

        return $a['id'] <=> $b['id'];
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $rows
     * @param  array<int, int>  $team
     */
    private function applyTeam(array &$rows, array $team, int $gamesFor, int $gamesAgainst): void
    {
        $points = $gamesFor > $gamesAgainst ? 2 : ($gamesFor === $gamesAgainst ? 1 : 0);

        foreach ($team as $id) {
            if (! isset($rows[$id])) {
                continue;
            }

            $rows[$id]['points'] += $points;
            $rows[$id]['played'] += 1;
            $rows[$id]['games_won'] += $gamesFor;
        }
    }
}
