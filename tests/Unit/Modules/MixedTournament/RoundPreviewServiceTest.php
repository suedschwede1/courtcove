<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\MixedTournament;

use App\Modules\MixedTournament\Services\HistoryService;
use App\Modules\MixedTournament\Services\RotationService;
use App\Modules\MixedTournament\Services\RoundPreviewService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RoundPreviewServiceTest extends TestCase
{
    private function service(): RoundPreviewService
    {
        return new RoundPreviewService(new HistoryService);
    }

    /** @return array{team_a_p1: int, team_a_p2: int, team_b_p1: int, team_b_p2: int} */
    private function match(int $a1, int $a2, int $b1, int $b2): array
    {
        return ['team_a_p1' => $a1, 'team_a_p2' => $a2, 'team_b_p1' => $b1, 'team_b_p2' => $b2];
    }

    /**
     * Full rotation of each group, in group order.
     *
     * @param  list<list<int>>  $groups
     * @return list<array{team_a_p1: int, team_a_p2: int, team_b_p1: int, team_b_p2: int}>
     */
    private function rotate(array $groups): array
    {
        $matches = [];
        foreach ($groups as $members) {
            foreach ((new RotationService)->matches(count($members)) as $m) {
                $matches[] = $this->match(
                    $members[$m['teamA'][0]], $members[$m['teamA'][1]],
                    $members[$m['teamB'][0]], $members[$m['teamB'][1]],
                );
            }
        }

        return $matches;
    }

    #[Test]
    public function repeated_from_the_second_repeat_sorted(): void
    {
        // History: 1+2 partners three times, 3+4 twice, 5+6 twice, 1+3 once.
        $past = [
            $this->match(1, 2, 7, 8), $this->match(1, 2, 7, 8), $this->match(1, 2, 7, 8),
            $this->match(3, 4, 7, 8), $this->match(3, 4, 7, 8),
            $this->match(6, 5, 7, 8), $this->match(5, 6, 7, 8),
            $this->match(1, 3, 7, 8),
        ];
        $round = [$this->match(5, 6, 3, 4), $this->match(2, 1, 9, 10)];

        $preview = $this->service()->preview($round, $past, []);

        // 1+3 (once) is not listed; ties on previous are ordered by players[0].
        $this->assertSame([
            ['players' => [2, 1], 'previous' => 3],
            ['players' => [3, 4], 'previous' => 2],
            ['players' => [5, 6], 'previous' => 2],
        ], $preview['repeated_encounters']);
        $this->assertSame([], $preview['avoid_collisions']);
    }

    #[Test]
    public function collisions_only_on_opposite_teams(): void
    {
        $round = [$this->match(1, 2, 3, 4)];
        $avoid = [['a' => 2, 'b' => 1], ['a' => 1, 'b' => 3], ['a' => 4, 'b' => 2], ['a' => 5, 'b' => 6]];

        $preview = $this->service()->preview($round, [], $avoid);

        // 1+2 are partners (no collision), 1/3 and 2/4 are opponents.
        $this->assertSame([[1, 3], [2, 4]], $preview['avoid_collisions']);
        $this->assertSame([], $preview['repeated_encounters']);
    }

    #[Test]
    public function match_counts_per_player(): void
    {
        // 13 players, 4_only: three groups of 4 and one extra group of
        // player 13 plus the fillers 1, 5 and 9 (each in two groups).
        $round = $this->rotate([[1, 2, 3, 4], [5, 6, 7, 8], [9, 10, 11, 12], [13, 1, 5, 9]]);

        $counts = $this->service()->preview($round, [], [])['match_counts'];

        $this->assertCount(13, $counts);
        $this->assertSame(6, $counts[1]);
        $this->assertSame(6, $counts[5]);
        $this->assertSame(6, $counts[9]);
        $this->assertSame(3, $counts[2]);
        $this->assertSame(3, $counts[13]);
        $this->assertSame(4 * count($round), array_sum($counts));
    }
}
