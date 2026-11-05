<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\MixedTournament;

use App\Modules\MixedTournament\Services\MexicanoService;
use App\Modules\MixedTournament\Services\RotationService;
use App\Modules\MixedTournament\Services\ScorecardBuilder;
use App\Modules\MixedTournament\Services\ScorecardTemplateService;
use App\Modules\MixedTournament\Services\WaveService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

class WaveScorecardTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function golden(string $key): array
    {
        $g = json_decode((string) file_get_contents(dirname(__DIR__, 4).'/tests/Fixtures/mixed/golden.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertNotEmpty($g[$key], $key);

        return $g[$key];
    }

    private function template(): ScorecardTemplateService
    {
        return new ScorecardTemplateService(new RotationService, new MexicanoService);
    }

    /**
     * Matches of one group the way the old unit test builds them: one match per
     * chunk of four players, padded with the first player.
     *
     * @param  list<int>  $playerIds
     * @return list<array<string, int>>
     */
    private function group(array $playerIds, bool $isExtra = false): array
    {
        $matches = [];
        foreach (array_chunk($playerIds, 4) as $chunk) {
            $chunk = array_pad($chunk, 4, $playerIds[0]);
            $matches[] = [
                'team_a_p1' => $chunk[0], 'team_a_p2' => $chunk[1],
                'team_b_p1' => $chunk[2], 'team_b_p2' => $chunk[3],
                'is_extra' => $isExtra ? 1 : 0,
            ];
        }

        return $matches;
    }

    #[Test]
    public function golden_waves(): void
    {
        foreach ($this->golden('waves') as $c) {
            $this->assertSame($c['result'], (new WaveService)->americanoWaves($c['args']['by_group'], $c['args']['court_count']), $c['name']);
        }
    }

    #[Test]
    public function golden_mexicano_slots(): void
    {
        foreach ($this->golden('mexicano_slots') as $c) {
            $this->assertSame($c['result'], (new WaveService)->mexicanoSlots($c['args']['by_group'], $c['args']['court_count']), $c['name']);
        }
    }

    #[Test]
    public function golden_wave_count(): void
    {
        foreach ($this->golden('wave_count') as $c) {
            $this->assertSame($c['result'], (new WaveService)->waveCount($c['args']['wave_by_group']), $c['name']);
        }
    }

    #[Test]
    public function golden_scorecard_template(): void
    {
        foreach ($this->golden('scorecard_template') as $c) {
            $a = $c['args'];
            if (isset($c['error'])) {
                try {
                    $this->template()->rows($a['mode'], $a['group_size']);
                    $this->fail($c['name'].': expected '.$c['error']['class']);
                } catch (Throwable $e) {
                    $this->assertSame($c['error']['class'], $e::class, $c['name']);
                    $this->assertSame($c['error']['message'], $e->getMessage(), $c['name']);
                }

                continue;
            }
            $this->assertSame($c['result'], [
                'rows' => $this->template()->rows($a['mode'], $a['group_size']),
                'player_count' => $this->template()->playerCount($a['mode'], $a['group_size']),
            ], $c['name']);
        }
    }

    #[Test]
    public function americano_five_group_runs_in_wave_one_and_the_third_group_in_wave_two(): void
    {
        $by = [0 => $this->group([1, 2, 3, 4]), 1 => $this->group([5, 6, 7, 8, 9]), 2 => $this->group([10, 11, 12, 13])];
        $r = (new WaveService)->americanoWaves($by, 2);
        $this->assertSame(1, $r[1]);
        $this->assertSame(1, $r[0]);
        $this->assertSame(2, $r[2]);
    }

    #[Test]
    public function extra_group_gets_the_last_wave_and_ties_follow_group_number(): void
    {
        $w = new WaveService;
        $by = [0 => $this->group([1, 2, 3, 4]), 1 => $this->group([5, 6, 7, 8]), 2 => $this->group([9, 10, 11, 12, 13], true)];
        $r = $w->americanoWaves($by, 2);
        $this->assertSame([1, 1, 2], [$r[0], $r[1], $r[2]]);
        $this->assertSame(2, $w->waveCount($r));

        $tie = [0 => $this->group([1, 2, 3, 4]), 1 => $this->group([5, 6, 7, 8]), 2 => $this->group([9, 10, 11, 12])];
        $this->assertSame([0 => 1, 1 => 2, 2 => 3], $w->americanoWaves($tie, 1));
        $this->assertSame([0 => 1, 1 => 2, 2 => 3], $w->americanoWaves($tie, 0));
        $this->assertSame([], $w->americanoWaves([], 2));
    }

    #[Test]
    public function mexicano_slots_count_through_the_courts(): void
    {
        $w = new WaveService;
        $by = [];
        for ($g = 0; $g < 7; $g++) {
            $by[$g] = $this->group([$g * 4 + 1, $g * 4 + 2, $g * 4 + 3, $g * 4 + 4]);
        }
        $slots = $w->mexicanoSlots($by, 3);
        $this->assertSame(['wave' => 1, 'court' => 1], $slots[0]);
        $this->assertSame(['wave' => 1, 'court' => 3], $slots[2]);
        $this->assertSame(['wave' => 2, 'court' => 1], $slots[3]);
        $this->assertSame(['wave' => 3, 'court' => 1], $slots[6]);
        $this->assertSame(3, $w->waveCount($slots));
        $this->assertSame(0, $w->waveCount([]));
        $this->assertSame([], $w->mexicanoSlots([], 3));
    }

    #[Test]
    public function blank_template_rows_and_sources(): void
    {
        $t = $this->template();
        $rows4 = $t->rows('americano', 4);
        $this->assertSame(['1+2', '1+3', '1+4'], array_column($rows4, 'team_a'));
        $this->assertSame(['3+4', '2+4', '2+3'], array_column($rows4, 'team_b'));
        $this->assertSame('', $rows4[0]['sitting']);

        $rows5 = $t->rows('americano', 5);
        $this->assertCount(5, $rows5);
        $this->assertSame('5', $rows5[0]['sitting']);
        $this->assertSame('1', $rows5[4]['sitting']);

        $mex = $t->rows('mexicano', 4);
        $this->assertCount(1, $mex);
        $this->assertSame(['1+4', '2+3', ''], [$mex[0]['team_a'], $mex[0]['team_b'], $mex[0]['sitting']]);
        $this->assertSame($mex, $t->rows('mexicano', 5));
        $this->assertSame(4, $t->playerCount('mexicano', 4));
        $this->assertSame(5, $t->playerCount('americano', 5));

        $pair = (new MexicanoService)->pair([1, 2, 3, 4]);
        $this->assertSame(implode('+', $pair[0]['team_a']), $mex[0]['team_a']);
    }

    #[Test]
    public function blank_americano_rejects_unsupported_group_size(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->template()->rows('americano', 6);
    }

    #[Test]
    public function five_player_rotation_starts_each_round_from_player_one(): void
    {
        $rows = $this->template()->rows('americano', 5);
        $this->assertSame(['1+2', '1+3', '1+4', '1+5', '2+4'], array_column($rows, 'team_a'));
        $this->assertSame(['5', '2', '3', '4', '1'], array_column($rows, 'sitting'));
    }

    /** @return array<string, int|string> */
    private function match(int $group, int $no, int $a1, int $a2, int $b1, int $b2, int $extra = 0): array
    {
        return ['group_no' => $group, 'match_no' => $no, 'team_a_p1' => $a1, 'team_a_p2' => $a2, 'team_b_p1' => $b1, 'team_b_p2' => $b2, 'is_extra' => $extra];
    }

    /** @return array{0: array<int, string>, 1: array<int, string>} */
    private function names(int $count): array
    {
        $labels = [];
        $genders = [];
        for ($i = 1; $i <= $count; $i++) {
            $labels[$i] = 'Spieler '.sprintf('%02d', $i);
            $genders[$i] = $i % 2 === 0 ? 'H' : 'D';
        }

        return [$labels, $genders];
    }

    #[Test]
    public function americano_cards_break_pages_per_wave_without_wave_numbers(): void
    {
        $matches = [];
        foreach ([0, 1, 2] as $g) {
            $p = $g * 4;
            $matches[] = $this->match($g, 1, $p + 1, $p + 2, $p + 3, $p + 4);
            $matches[] = $this->match($g, 2, $p + 1, $p + 3, $p + 2, $p + 4);
            $matches[] = $this->match($g, 3, $p + 1, $p + 4, $p + 2, $p + 3);
        }
        $matches[] = $this->match(3, 1, 1, 5, 9, 2, 1);
        [$labels, $genders] = $this->names(12);

        $cards = (new ScorecardBuilder(new WaveService))->americano($matches, $labels, $genders, 2);

        $this->assertSame([0, 1, 2, 3], array_column($cards, 'group_no'));
        $this->assertSame([false, true, true, false], array_column($cards, 'ends_wave'));
        $this->assertSame([false, false, false, true], array_column($cards, 'is_extra'));
        foreach ($cards as $card) {
            $this->assertArrayNotHasKey('wave', $card);
        }
        $this->assertSame(['no' => 1, 'label' => 'Spieler 01', 'gender' => 'D'], $cards[0]['players'][0]);
        $this->assertSame(4, count($cards[0]['players']));
        $this->assertSame(['no' => 1, 'team_a' => 'Spieler 01 & Spieler 02', 'team_b' => 'Spieler 03 & Spieler 04'], $cards[0]['pairings'][0]);
        $this->assertCount(3, $cards[0]['pairings']);
    }

    #[Test]
    public function americano_cards_are_sorted_by_wave_then_group_number(): void
    {
        // group 0 is a group of four, group 1 a group of five: the five-group plays in wave 1 together with group 0
        // when 2 courts exist; with 1 court the larger group comes first.
        $matches = [
            $this->match(0, 1, 1, 2, 3, 4),
            $this->match(1, 1, 5, 6, 7, 8),
            $this->match(1, 2, 9, 5, 6, 7),
        ];
        [$labels, $genders] = $this->names(9);
        $cards = (new ScorecardBuilder(new WaveService))->americano($matches, $labels, $genders, 1);
        $this->assertSame([1, 0], array_column($cards, 'group_no'));
        $this->assertSame([true, false], array_column($cards, 'ends_wave'));
        $this->assertSame(['Spieler 05', 'Spieler 06', 'Spieler 07', 'Spieler 08', 'Spieler 09'], array_column($cards[0]['players'], 'label'));
        $this->assertSame([1, 2, 3, 4, 5], array_column($cards[0]['players'], 'no'));
    }

    #[Test]
    public function missing_labels_and_genders_become_question_marks(): void
    {
        $cards = (new ScorecardBuilder(new WaveService))->americano([$this->match(0, 1, 1, 2, 3, 4)], [1 => 'Spieler 01'], [], 1);
        $this->assertSame(['no' => 2, 'label' => '?', 'gender' => '?'], $cards[0]['players'][1]);
        $this->assertSame('Spieler 01 & ?', $cards[0]['pairings'][0]['team_a']);
    }

    #[Test]
    public function mexicano_cards_carry_court_numbers(): void
    {
        $matches = [];
        for ($g = 0; $g < 4; $g++) {
            $p = $g * 4;
            $matches[] = $this->match($g, 1, $p + 1, $p + 4, $p + 2, $p + 3);
        }
        [$labels, $genders] = $this->names(16);

        $cards = (new ScorecardBuilder(new WaveService))->mexicano($matches, $labels, $genders, 3);

        $this->assertSame([0, 1, 2, 3], array_column($cards, 'group_no'));
        $this->assertSame([1, 2, 3, 1], array_column($cards, 'court'));
        $this->assertSame([false, false, true, false], array_column($cards, 'ends_wave'));
        $this->assertSame('Spieler 01 & Spieler 04', $cards[0]['team_a']);
        $this->assertSame('Spieler 02 & Spieler 03', $cards[0]['team_b']);
        $this->assertSame([1, 4, 2, 3], array_map(fn (array $p): int => (int) substr($p['label'], -2), $cards[0]['players']));
        foreach ($cards as $card) {
            $this->assertArrayNotHasKey('wave', $card);
        }
    }
}
