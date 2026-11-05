<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\MixedTournament;

use App\Modules\MixedTournament\Services\MexicanoService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\Mixed\DeterministicCallables;

class MexicanoServiceTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function golden(string $key): array
    {
        $g = json_decode((string) file_get_contents(dirname(__DIR__, 4).'/tests/Fixtures/mixed/golden.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertNotEmpty($g[$key], $key);

        return $g[$key];
    }

    private function identity(): callable
    {
        return DeterministicCallables::shuffle('identity');
    }

    #[Test]
    public function golden_rank(): void
    {
        foreach ($this->golden('mexicano_rank') as $c) {
            $this->assertSame($c['result'], (new MexicanoService)->rank($c['args']['rows'], DeterministicCallables::shuffle($c['args']['shuffle'])), $c['name']);
        }
    }

    #[Test]
    public function golden_sitters(): void
    {
        foreach ($this->golden('mexicano_sitters') as $c) {
            $a = $c['args'];
            $this->assertSame($c['result'], (new MexicanoService)->chooseSitters($a['participant_ids'], $a['sit_out_counts'], DeterministicCallables::shuffle($a['shuffle'])), $c['name']);
        }
    }

    #[Test]
    public function golden_propose(): void
    {
        foreach ($this->golden('mexicano_propose') as $c) {
            $a = $c['args'];
            $this->assertSame($c['result'], (new MexicanoService)->propose($a['ranked_ids'], $a['sit_out_counts'], DeterministicCallables::shuffle($a['shuffle'])), $c['name']);
        }
    }

    #[Test]
    public function golden_pair(): void
    {
        foreach ($this->golden('mexicano_pair') as $c) {
            $this->assertSame($c['result'], (new MexicanoService)->pair($c['args']['ranked_ids']), $c['name']);
        }
    }

    #[Test]
    public function eight_players_make_two_groups_one_with_four_against_two_three(): void
    {
        $pairs = (new MexicanoService)->pair([11, 12, 13, 14, 15, 16, 17, 18]);
        $this->assertCount(2, $pairs);
        $this->assertSame([11, 14], $pairs[0]['team_a']);
        $this->assertSame([12, 13], $pairs[0]['team_b']);
        $this->assertSame([15, 18], $pairs[1]['team_a']);
        $this->assertSame([16, 17], $pairs[1]['team_b']);
        $this->assertSame([11, 12, 13, 14], $pairs[0]['players']);
    }

    #[Test]
    public function the_two_with_the_fewest_sit_outs_pause(): void
    {
        $counts = array_fill_keys(range(1, 14), 1);
        $counts[7] = 0;
        $counts[9] = 0;
        $sitters = (new MexicanoService)->chooseSitters(range(1, 14), $counts, $this->identity());
        sort($sitters);
        $this->assertSame([7, 9], $sitters);
    }

    #[Test]
    public function nobody_sits_out_when_divisible_by_four_and_one_of_thirteen(): void
    {
        $m = new MexicanoService;
        $this->assertSame([], $m->chooseSitters(range(1, 12), [], $this->identity()));
        $this->assertCount(1, $m->chooseSitters(range(1, 13), [], $this->identity()));
    }

    #[Test]
    public function sitter_choice_depends_only_on_the_counter(): void
    {
        $counts = array_fill_keys(range(1, 5), 1);
        $counts[4] = 0;
        $this->assertSame([4], (new MexicanoService)->chooseSitters(range(1, 5), $counts, $this->identity()));
    }

    #[Test]
    public function rank_orders_by_points_then_games_and_ties_follow_the_injected_shuffle(): void
    {
        $m = new MexicanoService;
        $rows = [
            ['id' => 1, 'points_avg' => 1.0, 'games_avg' => 2.0],
            ['id' => 2, 'points_avg' => 2.0, 'games_avg' => 1.0],
            ['id' => 3, 'points_avg' => 1.0, 'games_avg' => 3.0],
        ];
        $this->assertSame([2, 3, 1], $m->rank($rows, $this->identity()));

        $tied = [
            ['id' => 7, 'points_avg' => 0.0, 'games_avg' => 0.0],
            ['id' => 8, 'points_avg' => 0.0, 'games_avg' => 0.0],
            ['id' => 9, 'points_avg' => 0.0, 'games_avg' => 0.0],
        ];
        $this->assertSame([9, 8, 7], $m->rank($tied, DeterministicCallables::shuffle('reverse')));
        $this->assertSame([7, 8, 9], $m->rank($tied, $this->identity()));
    }

    #[Test]
    public function propose_draws_sitters_first_and_keeps_the_ranking_of_the_rest(): void
    {
        $m = new MexicanoService;
        $p = $m->propose(range(1, 10), [], $this->identity());
        $this->assertCount(2, $p['sitters']);
        $this->assertCount(2, $p['groups']);

        $inGroups = array_merge(...array_column($p['groups'], 'players'));
        sort($inGroups);
        $expected = array_values(array_diff(range(1, 10), $p['sitters']));
        sort($expected);
        $this->assertSame($expected, $inGroups);
        $this->assertSame([], array_intersect($inGroups, $p['sitters']));

        $ranked = $m->propose([5, 3, 8, 1, 9, 2, 7, 4], [], $this->identity());
        $this->assertSame([5, 3, 8, 1], $ranked['groups'][0]['players']);
        $this->assertSame([9, 2, 7, 4], $ranked['groups'][1]['players']);
    }

    #[Test]
    public function invariants_hold_over_100_seeds(): void
    {
        $m = new MexicanoService;
        for ($seed = 1; $seed <= 100; $seed++) {
            mt_srand($seed);
            $n = 4 + ($seed % 14);
            $ids = range(1, $n);
            $counts = [];
            foreach ($ids as $id) {
                $counts[$id] = mt_rand(0, 3);
            }
            $shuffle = function (array $items): array {
                for ($i = count($items) - 1; $i > 0; $i--) {
                    $j = mt_rand(0, $i);
                    [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
                }

                return $items;
            };
            $ranked = $m->rank(array_map(fn (int $id): array => ['id' => $id, 'points_avg' => (float) mt_rand(0, 2), 'games_avg' => (float) mt_rand(0, 3)], $ids), $shuffle);
            $p = $m->propose($ranked, $counts, $shuffle);

            $this->assertCount($n % 4, $p['sitters'], "seed $seed");
            foreach ($p['sitters'] as $sitter) {
                foreach (array_diff($ids, $p['sitters']) as $other) {
                    $this->assertGreaterThanOrEqual($counts[$sitter], $counts[$other], "seed $seed");
                }
            }
            $expected = array_chunk(array_values(array_diff($ranked, $p['sitters'])), 4);
            $this->assertSame($expected, array_column($p['groups'], 'players'), "seed $seed");
        }
    }
}
