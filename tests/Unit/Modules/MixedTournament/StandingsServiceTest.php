<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\MixedTournament;

use App\Modules\MixedTournament\Services\StandingsService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StandingsServiceTest extends TestCase
{
    /**
     * @param  list<array<string, mixed>>  $standings
     * @return array<int, array<string, mixed>>
     */
    private function byId(array $standings): array
    {
        $by = [];
        foreach ($standings as $row) {
            $by[$row['id']] = $row;
        }

        return $by;
    }

    /** @return list<array<string, mixed>> */
    private function fillerFreeMatches(): array
    {
        return [
            ['round' => 1, 'team_a' => [1, 2], 'team_b' => [6, 7], 'games_a' => 3, 'games_b' => 0],
            ['round' => 2, 'team_a' => [1, 3], 'team_b' => [6, 7], 'games_a' => 2, 'games_b' => 2],
            ['round' => 3, 'team_a' => [1, 4], 'team_b' => [6, 7], 'games_a' => 0, 'games_b' => 1],
            ['round' => 4, 'team_a' => [5, 1], 'team_b' => [6, 7], 'games_a' => null, 'games_b' => null],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function fillerFree(): array
    {
        return $this->byId((new StandingsService)->standings([1, 2, 3, 4, 5, 6, 7], $this->fillerFreeMatches()));
    }

    #[Test]
    public function golden_standings(): void
    {
        $g = json_decode((string) file_get_contents(dirname(__DIR__, 4).'/tests/Fixtures/mixed/golden.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertNotEmpty($g['standings']);
        foreach ($g['standings'] as $c) {
            $actual = (new StandingsService)->standings($c['args']['participant_ids'], $c['args']['matches']);
            // The old code returned int averages when the division was exact; the port always returns floats.
            $this->assertEquals($c['result'], $actual, $c['name']);
            foreach ($actual as $row) {
                $this->assertIsFloat($row['points_avg'], $c['name']);
                $this->assertIsFloat($row['games_avg'], $c['name']);
            }
        }
    }

    #[Test]
    public function win_draw_loss_points(): void
    {
        $by = $this->fillerFree();
        $this->assertSame(3, $by[1]['played']);
        $this->assertSame(2 + 1 + 0, $by[1]['points']);
    }

    #[Test]
    public function averages_over_played_matches(): void
    {
        $by = $this->fillerFree();
        $this->assertEqualsWithDelta((3 + 2 + 0) / 3, $by[1]['games_avg'], 0.0001);
        $this->assertEqualsWithDelta(1.0, $by[1]['points_avg'], 0.0001);
        $this->assertEqualsWithDelta(2.0, $by[2]['points_avg'], 0.0001);
    }

    #[Test]
    public function unscored_matches_do_not_count(): void
    {
        $by = $this->fillerFree();
        $this->assertSame(0, $by[5]['played']);
        $this->assertSame(0.0, $by[5]['games_avg']);
        $this->assertFalse($by[5]['eligible']);
        $this->assertGreaterThanOrEqual(1, $by[5]['rounds_needed']);
        $this->assertSame(3, $by[1]['rounds_played']);
    }

    #[Test]
    public function two_rounds_behind_is_not_ranked(): void
    {
        $standings = (new StandingsService)->standings([1, 2, 3, 4, 5, 6, 7], $this->fillerFreeMatches());
        $by = $this->byId($standings);
        $this->assertSame(1, $by[2]['rounds_played']);
        $this->assertTrue($by[1]['eligible']);
        $this->assertFalse($by[2]['eligible']);
        $this->assertSame(1, $standings[0]['id']);

        $ids = array_column($standings, 'id');
        $this->assertLessThan(array_search(6, $ids, true), array_search(1, $ids, true));
        $this->assertLessThan(array_search(2, $ids, true), array_search(6, $ids, true));
    }

    #[Test]
    public function filler_counts_rounds_not_matches(): void
    {
        $matches = [
            ['round' => 1, 'team_a' => [1, 2], 'team_b' => [3, 4], 'games_a' => 3, 'games_b' => 1],
            ['round' => 1, 'team_a' => [5, 6], 'team_b' => [7, 1], 'games_a' => 2, 'games_b' => 2],
            ['round' => 2, 'team_a' => [1, 2], 'team_b' => [3, 4], 'games_a' => 1, 'games_b' => 3],
            ['round' => 2, 'team_a' => [5, 6], 'team_b' => [7, 1], 'games_a' => 4, 'games_b' => 0],
        ];
        $by = $this->byId((new StandingsService)->standings([1, 2, 3, 4, 5, 6, 7], $matches));
        $this->assertSame(4, $by[1]['played']);
        $this->assertSame(2, $by[2]['played']);
        $this->assertSame(2, $by[1]['rounds_played']);
        $this->assertSame(2, $by[2]['rounds_played']);
        foreach ($by as $row) {
            $this->assertTrue($row['eligible']);
        }
    }

    #[Test]
    public function ties_are_ordered_by_participant_id_even_when_input_is_shuffled(): void
    {
        $m = [['round' => 1, 'team_a' => [3, 9], 'team_b' => [5, 1], 'games_a' => 2, 'games_b' => 2]];
        $this->assertSame([1, 3, 5, 9], array_column((new StandingsService)->standings([9, 5, 3, 1], $m), 'id'));
    }
}
