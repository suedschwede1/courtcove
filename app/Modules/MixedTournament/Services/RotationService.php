<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Services;

use InvalidArgumentException;

/**
 * Fixed rotation tables for Americano doubles in groups of 4 and 5.
 *
 * Both tables are built so that every player partners every other player
 * exactly once. In the group of 5 every pair is additionally opponents exactly
 * twice - derived from the classic round-robin scheme for an odd number of
 * participants (each round has a "sitting" position, the pairings of the
 * remaining 4 positions of a round are read as one team-against-team match
 * instead of singles). Framework-free and therefore testable without a database.
 */
final class RotationService
{
    /**
     * @param  int  $groupSize  4 or 5
     * @return list<array{teamA: list<int>, teamB: list<int>, sitting: list<int>}> per entry: teamA (2 zero-based
     *                                                                             positions), teamB (2 positions),
     *                                                                             sitting (0 or 1 position, empty for size 4)
     */
    public function matches(int $groupSize): array
    {
        if ($groupSize === 4) {
            return [
                ['teamA' => [0, 1], 'teamB' => [2, 3], 'sitting' => []],
                ['teamA' => [0, 2], 'teamB' => [1, 3], 'sitting' => []],
                ['teamA' => [0, 3], 'teamB' => [1, 2], 'sitting' => []],
            ];
        }

        if ($groupSize === 5) {
            return [
                ['teamA' => [0, 1], 'teamB' => [2, 3], 'sitting' => [4]],
                ['teamA' => [0, 2], 'teamB' => [3, 4], 'sitting' => [1]],
                ['teamA' => [0, 3], 'teamB' => [4, 1], 'sitting' => [2]],
                ['teamA' => [0, 4], 'teamB' => [1, 2], 'sitting' => [3]],
                ['teamA' => [1, 3], 'teamB' => [2, 4], 'sitting' => [0]],
            ];
        }

        throw new InvalidArgumentException(sprintf('unsupported group size %u', $groupSize));
    }
}
