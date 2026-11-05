<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Tournament;

use App\Modules\Tournament\Services\StandingsService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StandingsServiceTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function vectors(): array
    {
        return json_decode((string) file_get_contents(dirname(__DIR__, 3).'/Fixtures/tournament/vectors.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function standingsCases(): array
    {
        $cases = [];
        foreach (self::vectors()['standings'] as $case) {
            $cases[$case['op'].' '.$case['name']] = [$case];
        }

        return $cases;
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function runnerUpCases(): array
    {
        $cases = [];
        foreach (self::vectors()['runner_up'] as $case) {
            $cases[$case['name']] = [$case];
        }

        return $cases;
    }

    /** @param  array<string, mixed>  $result */
    private function assertCaptured(array $result, callable $call, string $message): void
    {
        if (array_key_exists('error', $result)) {
            try {
                $call();
                $this->fail($message.': expected exception "'.$result['error'].'"');
            } catch (InvalidArgumentException $e) {
                $this->assertSame($result['error'], $e->getMessage(), $message);
            }

            return;
        }
        $this->assertSame($result['ok'], $call(), $message);
    }

    /** The service as the vectors configured it: points per win and scope from the case, drop_last by default. */
    private function service(int $points = 2, string $scope = 'tied', string $mode = 'drop_last'): StandingsService
    {
        return (new StandingsService)->setPointsPerWin($points)->setTiebreakScope($scope)->setRunnerUpMode($mode);
    }

    /** @param  array<string, mixed>  $case */
    #[Test]
    #[DataProvider('standingsCases')]
    public function standings_vectors_match_the_old_code(array $case): void
    {
        if ($case['op'] === 'rank') {
            $svc = $this->service((int) $case['config']['points_per_win'], (string) $case['config']['tiebreak_scope']);
            $this->assertSame($case['result'], $svc->rank($case['players'], $case['matches']));

            return;
        }
        $this->assertSame('resolveLot', $case['op']);
        $this->assertCaptured($case['result'], fn () => $this->service()->resolveLot($case['rows'], $case['order']), $case['name']);
    }

    /** @param  array<string, mixed>  $case */
    #[Test]
    #[DataProvider('runnerUpCases')]
    public function runner_up_vectors_match_the_old_code(array $case): void
    {
        $svc = $this->service(2, 'tied', $case['mode']);
        $this->assertCaptured(
            $case['result'],
            fn () => $svc->rankRunnersUp($case['seconds'], $case['group_standings'], $case['matches']),
            $case['name']
        );
    }

    /** @return array<string, mixed> */
    private function match(int $a, int $b, ?int $winner, ?int $sa, ?int $sb, ?int $ga, ?int $gb): array
    {
        return ['tpid_a' => $a, 'tpid_b' => $b, 'winner' => $winner, 'sets_a' => $sa, 'sets_b' => $sb, 'games_a' => $ga, 'games_b' => $gb];
    }

    /** @return list<array<string, mixed>> */
    private function players(int ...$tpids): array
    {
        return array_map(fn (int $t) => ['tpid' => $t, 'label' => 'Demo Spieler '.$t, 'seed' => $t, 'points_adjust' => 0], $tpids);
    }

    #[Test]
    public function only_matches_with_a_winner_count(): void
    {
        $rows = $this->service()->rank($this->players(1, 2, 3), [
            $this->match(1, 2, 1, 2, 0, 12, 6),
            $this->match(1, 3, null, 0, 0, 0, 0),
            $this->match(2, 3, 0, 2, 1, 14, 12),
            $this->match(2, 3, null, null, null, null, null),
        ]);

        $byTpid = array_column($rows, null, 'tpid');
        $this->assertSame(1, $byTpid[1]['played']);
        $this->assertSame(1, $byTpid[2]['played']);
        $this->assertSame(0, $byTpid[3]['played']);
        $this->assertSame(1, $byTpid[1]['wins']);
        $this->assertSame(2, $byTpid[1]['points']);
    }

    #[Test]
    public function triple_tie_is_flagged_needs_lot_and_never_decided(): void
    {
        $rows = $this->service()->rank($this->players(1, 2, 3, 4), [
            $this->match(1, 2, 1, 2, 0, 12, 6), $this->match(2, 3, 2, 2, 0, 12, 6), $this->match(3, 1, 3, 2, 0, 12, 6),
            $this->match(1, 4, 1, 2, 0, 12, 6), $this->match(2, 4, 2, 2, 0, 12, 6), $this->match(3, 4, 3, 2, 0, 12, 6),
        ]);

        $flags = array_column($rows, 'needs_lot', 'tpid');
        $this->assertSame([1 => true, 2 => true, 3 => true, 4 => false], $flags);
        $this->assertSame(['lot', 'lot', 'lot'], array_column(array_slice($rows, 0, 3), 'decided_by'));
        $this->assertSame([1, 2, 3, 4], array_column($rows, 'tpid'), 'provisional order is by seed, never a decision');
    }

    /** @return array{0: list<array<string, mixed>>, 1: array<string, list<array<string, mixed>>>, 2: list<array<string, mixed>>} */
    private function runnerUpFixture(): array
    {
        $seconds = [
            ['tpid' => 2, 'grp' => 'D', 'seed' => 2, 'points' => 2, 'wins' => 1, 'losses' => 1, 'played' => 2, 'set_diff' => 0, 'game_diff' => 1],
            ['tpid' => 6, 'grp' => 'F', 'seed' => 6, 'points' => 4, 'wins' => 2, 'losses' => 1, 'played' => 3, 'set_diff' => 1, 'game_diff' => 5],
        ];
        $tables = [
            'D' => [['tpid' => 1, 'rank' => 1, 'needs_lot' => false], ['tpid' => 2, 'rank' => 2, 'needs_lot' => false], ['tpid' => 3, 'rank' => 3, 'needs_lot' => false]],
            'F' => [['tpid' => 5, 'rank' => 1, 'needs_lot' => false], ['tpid' => 6, 'rank' => 2, 'needs_lot' => false], ['tpid' => 7, 'rank' => 3, 'needs_lot' => false], ['tpid' => 8, 'rank' => 4, 'needs_lot' => false]],
        ];
        $matches = [
            $this->match(1, 2, 1, 2, 1, 12, 10), $this->match(2, 3, 2, 2, 0, 12, 6), $this->match(5, 6, 5, 2, 0, 12, 7),
            $this->match(6, 7, 6, 2, 1, 11, 9), $this->match(6, 8, 6, 2, 0, 12, 2),
        ];

        return [$seconds, $tables, $matches];
    }

    #[Test]
    public function drop_last_removes_exactly_the_match_against_the_last(): void
    {
        [$seconds, $tables, $matches] = $this->runnerUpFixture();

        $ranked = $this->service()->rankRunnersUp($seconds, $tables, $matches);
        $six = array_values(array_filter($ranked, fn (array $r) => $r['tpid'] === 6))[0];
        $two = array_values(array_filter($ranked, fn (array $r) => $r['tpid'] === 2))[0];

        $this->assertSame(2, $six['played']);
        $this->assertSame(2, $six['points']);
        $this->assertSame(-1, $six['set_diff']);
        $this->assertSame(-3, $six['game_diff']);
        $this->assertSame(2, $two['points'], 'a group of three keeps its record');
        $this->assertSame(1, $two['game_diff']);
    }

    #[Test]
    public function drop_last_blocks_on_an_unresolved_last_place(): void
    {
        [$seconds, $tables, $matches] = $this->runnerUpFixture();
        $tables['F'][3]['needs_lot'] = true;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('drop_last requires a resolved last place; a lot decision is still open');
        $this->service()->rankRunnersUp($seconds, $tables, $matches);
    }

    #[Test]
    public function persisted_lot_decides_the_order(): void
    {
        $rows = [
            ['tpid' => 10, 'rank' => 1, 'needs_lot' => true, 'decided_by' => 'lot'],
            ['tpid' => 11, 'rank' => 2, 'needs_lot' => true, 'decided_by' => 'lot'],
        ];

        $resolved = $this->service()->resolveLot($rows, [11, 10]);

        $this->assertSame(11, $resolved[0]['tpid']);
        $this->assertFalse($resolved[0]['needs_lot']);
        $this->assertSame([1, 2], array_column($resolved, 'rank'));
    }
}
