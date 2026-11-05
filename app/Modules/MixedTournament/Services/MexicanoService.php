<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Services;

/**
 * Mexicano: the pairings of every round come from the standings of the
 * finished rounds, not from a fixed rotation table. Each group plays exactly
 * ONE match (Americano: three), then everybody is re-ranked and re-paired.
 *
 * A service of its own on purpose: Americano must not regress, and Mexicano
 * needs neither a rotation table nor the mixing repair - on the contrary, the
 * format WANTS similarly strong players together repeatedly.
 *
 * Framework-free and without database access, like RotationService and
 * GroupingService.
 */
final class MexicanoService
{
    /**
     * Orders standing rows into the ranking the groups are built from:
     * points average descending, then games average descending.
     *
     * Ties are resolved RANDOMLY. Without that rule the stable sort (and so
     * the order of the participant ids) would decide, and the same people
     * would systematically play together all day, which defeats the idea of
     * the format. Ties are common: everybody starts at 0.
     *
     * Same technique as chooseSitters(): shuffle first, then stable sort.
     *
     * @param  array<int|string, array{id: int|string, points_avg: int|float, games_avg: int|float}>  $rows
     * @param  callable(array<int, mixed>): array<int, mixed>  $shuffle
     * @return list<int> participant ids, best ranked first
     */
    public function rank(array $rows, callable $shuffle): array
    {
        $ordered = $shuffle(array_values($rows));

        usort($ordered, function (array $a, array $b): int {
            if ($a['points_avg'] !== $b['points_avg']) {
                return $b['points_avg'] <=> $a['points_avg'];
            }

            return $b['games_avg'] <=> $a['games_avg'];
        });

        return array_map(fn (array $row): int => (int) $row['id'], $ordered);
    }

    /**
     * Picks the participants who sit out this round: as many as are missing
     * to the next number divisible by 4.
     *
     * Those with the FEWEST automatic sit-outs so far are chosen, so the
     * pausing evens out over the tournament day by itself and the numbers of
     * played rounds never differ by more than one for a constant set of
     * participants. The eligibility rule in StandingsService (one round of
     * tolerance) relies on exactly that.
     *
     * IMPORTANT: $sitOutCounts holds only AUTOMATIC sit-outs. Whoever was not
     * selected for a round at all does not appear there - a returning player
     * brings the old counter and is therefore neither favoured nor
     * disadvantaged.
     *
     * Ties are decided by chance (shuffle first, stable sort afterwards) so
     * that it is not always the same person.
     *
     * @param  array<int, int>  $participantIds
     * @param  array<int|string, int>  $sitOutCounts  participant id => automatic sit-outs so far
     * @param  callable(array<int, mixed>): array<int, mixed>  $shuffle
     * @return list<int>
     */
    public function chooseSitters(array $participantIds, array $sitOutCounts, callable $shuffle): array
    {
        $sitterCount = count($participantIds) % 4;

        if ($sitterCount === 0) {
            return [];
        }

        $ordered = $shuffle(array_values($participantIds));

        // usort is stable since PHP 8.0 - the random order stays intact
        // within equal sit-out counts.
        usort($ordered, function ($a, $b) use ($sitOutCounts): int {
            $countA = $sitOutCounts[$a] ?? 0;
            $countB = $sitOutCounts[$b] ?? 0;

            return $countA <=> $countB;
        });

        return array_slice($ordered, 0, $sitterCount);
    }

    /**
     * Complete round proposal: determine the sitters, pair the rest in groups
     * of four.
     *
     * The sitters are drawn BEFORE the groups are built, but the ranking of
     * the others is kept - array_diff keeps the order of the first array.
     * The other way round the bottom of the table would pause systematically.
     *
     * @param  array<int, int>  $rankedIds  participant ids, best ranked first (from rank())
     * @param  array<int|string, int>  $sitOutCounts
     * @param  callable(array<int, mixed>): array<int, mixed>  $shuffle
     * @return array{groups: list<array{players: list<int>, team_a: array{int, int}, team_b: array{int, int}}>, sitters: list<int>}
     */
    public function propose(array $rankedIds, array $sitOutCounts, callable $shuffle): array
    {
        $sitters = $this->chooseSitters($rankedIds, $sitOutCounts, $shuffle);
        $playing = array_values(array_diff($rankedIds, $sitters));

        return ['groups' => $this->pair($playing), 'sitters' => $sitters];
    }

    /**
     * Builds the groups and their pairing from a list ranked by standing:
     * inside every group of four, rank 1 with rank 4 plays rank 2 with rank 3.
     *
     * @param  array<int, int>  $rankedIds  best ranked first; the count has to be divisible by 4 (chooseSitters() sees to it)
     * @return list<array{players: list<int>, team_a: array{int, int}, team_b: array{int, int}}>
     */
    public function pair(array $rankedIds): array
    {
        $groups = [];

        foreach (array_chunk(array_values($rankedIds), 4) as $four) {
            if (count($four) < 4) {
                continue;
            }

            $groups[] = [
                'players' => $four,
                'team_a' => [$four[0], $four[3]],
                'team_b' => [$four[1], $four[2]],
            ];
        }

        return $groups;
    }
}
