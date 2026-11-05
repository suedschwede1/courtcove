<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Services;

/**
 * Pairings for the blank scorecard: the slip without names that is printed in
 * stock before the tournament. Instead of player names it carries position
 * codes - "1+3 against 2+4" means position 1 with position 3 against position
 * 2 with position 4 inside the group.
 *
 * The codes are DERIVED from RotationService and MexicanoService and not
 * copied here. A second copy of the rotation table would be the obvious
 * solution and the wrong one: if the rotation changes, the blank slip would
 * keep printing a pairing the system no longer produces - and nobody would
 * notice because there are no names on the slip to reveal the contradiction.
 */
final class ScorecardTemplateService
{
    public function __construct(
        private readonly RotationService $rotation,
        private readonly MexicanoService $mexicano,
    ) {}

    /**
     * The pairings of one group as position codes.
     *
     * @param  string  $mode  'americano' or 'mexicano'
     * @param  int  $groupSize  4 or 5 for Americano; no effect for Mexicano
     * @return list<array{team_a: string, team_b: string, sitting: string}>
     */
    public function rows(string $mode, int $groupSize): array
    {
        if ($mode === 'mexicano') {
            return $this->mexicanoRows();
        }

        return $this->americanoRows($groupSize);
    }

    /**
     * How many name lines the slip needs. Always four for Mexicano - the
     * format has no groups of five.
     */
    public function playerCount(string $mode, int $groupSize): int
    {
        return $mode === 'mexicano' ? 4 : $groupSize;
    }

    /**
     * RotationService returns zero-based positions; on the slip people count
     * from 1.
     *
     * @return list<array{team_a: string, team_b: string, sitting: string}>
     */
    private function americanoRows(int $groupSize): array
    {
        $rows = [];

        foreach ($this->rotation->matches($groupSize) as $match) {
            $rows[] = [
                'team_a' => $this->code($match['teamA']),
                'team_b' => $this->code($match['teamB']),
                'sitting' => $this->code($match['sitting']),
            ];
        }

        return $rows;
    }

    /**
     * MexicanoService::pair() works on participant ids. Fed with 1..4 the
     * "ids" are exactly the position numbers - so the pairing comes from the
     * same source as in real play.
     *
     * @return list<array{team_a: string, team_b: string, sitting: string}>
     */
    private function mexicanoRows(): array
    {
        $rows = [];

        foreach ($this->mexicano->pair([1, 2, 3, 4]) as $group) {
            $rows[] = [
                'team_a' => implode('+', $group['team_a']),
                'team_b' => implode('+', $group['team_b']),
                'sitting' => '',
            ];
        }

        return $rows;
    }

    /** @param  list<int>  $positions */
    private function code(array $positions): string
    {
        return implode('+', array_map(fn (int $position): int => $position + 1, $positions));
    }
}
