<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\MixedTournament;

use App\Modules\MixedTournament\Services\HistoryService;
use App\Modules\MixedTournament\Services\RotationService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

class RotationHistoryTest extends TestCase
{
    /** @return array<string, mixed> */
    private function golden(): array
    {
        return json_decode((string) file_get_contents(dirname(__DIR__, 4).'/tests/Fixtures/mixed/golden.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @param  array<string, mixed>  $case */
    private function assertCase(array $case, callable $run): void
    {
        if (isset($case['error'])) {
            try {
                $run();
                $this->fail($case['name'].': expected '.$case['error']['class']);
            } catch (Throwable $e) {
                $this->assertSame($case['error']['class'], $e::class, $case['name']);
                $this->assertSame($case['error']['message'], $e->getMessage(), $case['name']);
            }

            return;
        }
        $this->assertSame($case['result'], $run(), $case['name']);
    }

    #[Test]
    public function golden_rotation(): void
    {
        $cases = $this->golden()['rotation'];
        $this->assertNotEmpty($cases);
        foreach ($cases as $c) {
            $this->assertCase($c, fn () => (new RotationService)->matches($c['args']['group_size']));
        }
    }

    #[Test]
    public function golden_history(): void
    {
        $cases = $this->golden()['history'];
        $this->assertNotEmpty($cases);
        $history = new HistoryService;
        foreach ($cases as $c) {
            $this->assertCase($c, fn () => isset($c['args']['matches'])
                ? $history->build($c['args']['matches'])
                : $history->key($c['args']['a'], $c['args']['b']));
        }
    }

    #[Test]
    public function golden_mixing_score(): void
    {
        $cases = $this->golden()['mixing_score'];
        $this->assertNotEmpty($cases);
        $history = new HistoryService;
        foreach ($cases as $c) {
            $this->assertCase($c, fn () => $history->mixingScore($c['args']['partner_counts'], $c['args']['participant_ids']));
        }
    }

    #[Test]
    public function unsupported_group_size_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unsupported group size 3');
        (new RotationService)->matches(3);
    }

    /**
     * @param  list<array{teamA: list<int>, teamB: list<int>, sitting: list<int>}>  $matches
     * @return array{partnerOk: bool, opponentOk: bool, sitCounts: list<int>}
     */
    private function checkRotationInvariants(int $groupSize, array $matches, int $expectedOpponentCount): array
    {
        $partnerCounts = [];
        $opponentCounts = [];
        $sitCounts = array_fill(0, $groupSize, 0);

        foreach ($matches as $match) {
            foreach ([$match['teamA'], $match['teamB']] as $team) {
                $key = min($team).':'.max($team);
                $partnerCounts[$key] = ($partnerCounts[$key] ?? 0) + 1;
            }
            foreach ($match['teamA'] as $a) {
                foreach ($match['teamB'] as $b) {
                    $key = min($a, $b).':'.max($a, $b);
                    $opponentCounts[$key] = ($opponentCounts[$key] ?? 0) + 1;
                }
            }
            foreach ($match['sitting'] as $s) {
                $sitCounts[$s]++;
            }
        }

        $partnerOk = true;
        $opponentOk = true;
        for ($a = 0; $a < $groupSize; $a++) {
            for ($b = $a + 1; $b < $groupSize; $b++) {
                $pair = $a.':'.$b;
                if (($partnerCounts[$pair] ?? null) !== 1) {
                    $partnerOk = false;
                }
                if (($opponentCounts[$pair] ?? null) !== $expectedOpponentCount) {
                    $opponentOk = false;
                }
            }
        }

        return ['partnerOk' => $partnerOk, 'opponentOk' => $opponentOk, 'sitCounts' => $sitCounts];
    }

    #[Test]
    public function four_player_rotation_partners_once_opponents_twice(): void
    {
        $four = (new RotationService)->matches(4);
        $this->assertCount(3, $four);
        $check = $this->checkRotationInvariants(4, $four, 2);
        $this->assertTrue($check['partnerOk']);
        $this->assertTrue($check['opponentOk']);
    }

    #[Test]
    public function five_player_rotation_partners_once_opponents_twice_sits_once(): void
    {
        $five = (new RotationService)->matches(5);
        $this->assertCount(5, $five);
        $check = $this->checkRotationInvariants(5, $five, 2);
        $this->assertTrue($check['partnerOk']);
        $this->assertTrue($check['opponentOk']);
        $this->assertSame([1, 1, 1, 1, 1], $check['sitCounts']);
    }

    #[Test]
    public function history_key_is_undirected(): void
    {
        $history = new HistoryService;
        $this->assertSame($history->key(2, 5), $history->key(5, 2));
        $this->assertSame('2:5', $history->key('5', 2));

        $built = $history->build([
            ['team_a_p1' => 1, 'team_a_p2' => 2, 'team_b_p1' => 3, 'team_b_p2' => 4],
            ['team_a_p1' => 1, 'team_a_p2' => 3, 'team_b_p1' => 2, 'team_b_p2' => 4],
        ]);
        $this->assertSame(1, $built['partners']['1:2']);
        $this->assertArrayNotHasKey('1:4', $built['partners']);
        $this->assertSame(2, $built['opponents']['1:4']);
    }

    #[Test]
    public function mixing_rating_thresholds(): void
    {
        $history = new HistoryService;
        $score = function (int $n, int $repeated) use ($history): array {
            $ids = range(1, $n);
            $counts = [];
            $k = 0;
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $counts[$history->key($ids[$i], $ids[$j])] = $k++ < $repeated ? 2 : 1;
                }
            }

            return $history->mixingScore($counts, $ids);
        };

        $this->assertSame('none', $history->mixingScore([], range(1, 5))['rating']);
        $this->assertSame('good', $score(5, 1)['rating']);      // 1/10 = 10%
        $this->assertSame('ok', $score(5, 3)['rating']);        // 3/10 = 30%
        $poor = $score(14, 28);                                  // 28/91 = 30.8% -> shown as 31
        $this->assertSame(31, $poor['repeat_rate']);
        $this->assertSame('poor', $poor['rating']);
    }
}
