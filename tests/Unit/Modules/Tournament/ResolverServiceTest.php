<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Tournament;

use App\Modules\Tournament\Services\ResolverService;
use App\Modules\Tournament\Services\StandingsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ResolverServiceTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function vectors(): array
    {
        return json_decode((string) file_get_contents(dirname(__DIR__, 3).'/Fixtures/tournament/vectors.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function resolverCases(): array
    {
        $cases = [];
        foreach (self::vectors()['resolver'] as $case) {
            $cases[$case['op'].' '.$case['name']] = [$case];
        }

        return $cases;
    }

    /** The service as the vectors built it: default standings (2 points, scope tied, drop_last). */
    private function resolver(): ResolverService
    {
        return new ResolverService((new StandingsService)->setPointsPerWin(2)->setTiebreakScope('tied')->setRunnerUpMode('drop_last'));
    }

    /**
     * The recording lot resolver of the vectors ("reverse" = array_reverse of the tied list).
     *
     * @param  list<array{scope: string, tied: list<int>}>  $calls
     */
    private function reverseResolver(array &$calls): \Closure
    {
        return function (string $scope, array $tied) use (&$calls): array {
            $calls[] = ['scope' => $scope, 'tied' => $tied];

            return array_reverse($tied);
        };
    }

    /** @param  array<string, mixed>  $result */
    private function assertCaptured(array $result, callable $call, string $message): void
    {
        if (array_key_exists('error', $result)) {
            try {
                $call();
                $this->fail($message.': expected exception "'.$result['error'].'"');
            } catch (RuntimeException $e) {
                $this->assertSame($result['error'], $e->getMessage(), $message);
            }

            return;
        }
        $this->assertSame($result['ok'], $call(), $message);
    }

    /** @param  array<string, mixed>  $case */
    #[Test]
    #[DataProvider('resolverCases')]
    public function resolver_vectors_match_the_old_code(array $case): void
    {
        $r = $this->resolver();
        $calls = [];

        if (array_key_exists('lot_orders', $case)) {
            $r->setLotOrders($case['lot_orders']);
        }
        if (($case['lot_resolver'] ?? null) === 'reverse') {
            $r->setLotResolver($this->reverseResolver($calls));
        }

        $name = $case['name'];
        match ($case['op']) {
            'byPlace' => $this->assertCaptured($case['result'], fn () => $r->byPlace($case['place'], $case['standings'], $case['matches'], $case['phase_key']), $name),
            'codesAllAdvance' => $this->assertCaptured($case['result'], fn () => $r->codesAllAdvance($case['standings'], $case['matches'], $case['seed_prefix'], $case['pool_prefix'], $case['seed_count'], $case['phase_key']), $name),
            'codesPromoted' => $this->assertCaptured($case['result'], fn () => $r->codesPromoted($case['standings'], $case['matches'], $case['prefix'], $case['seconds_count'], $case['phase_key']), $name),
            'remainder' => $this->assertCaptured($case['result'], fn () => $r->remainder($case['standings'], $case['matches'], $case['promoted'], $case['prefix'], $case['phase_key']), $name),
            'codesRemainderSeeded' => $this->assertCaptured($case['result'], fn () => $r->codesRemainderSeeded($case['standings'], $case['matches'], $case['promoted'], $case['prefix'], $case['seed_count'], $case['phase_key']), $name),
            'resolveLines' => $this->assertSame($case['result'], $r->resolveLines($case['lines'], $case['codes'], $case['seed_prefix']), $name),
            'firstRoundPlayers' => $this->assertSame($case['result'], $r->firstRoundPlayers($case['lines'], $case['resolved'], $case['size']), $name),
            default => $this->fail('unknown op '.$case['op']),
        };

        if (array_key_exists('lot_resolver_calls', $case)) {
            $this->assertSame(
                array_map(fn (array $c) => [$c['scope'], $c['tied']], $case['lot_resolver_calls']),
                array_map(fn (array $c) => [$c['scope'], $c['tied']], $calls),
                $name.' lot resolver calls'
            );
        }
    }

    #[Test]
    public function every_resolver_op_is_covered(): void
    {
        $ops = array_unique(array_column(self::vectors()['resolver'], 'op'));
        sort($ops);
        $this->assertSame(['byPlace', 'codesAllAdvance', 'codesPromoted', 'codesRemainderSeeded', 'firstRoundPlayers', 'remainder', 'resolveLines'], $ops);
    }

    #[Test]
    public function no_provisional_seed_on_an_unresolved_lot_tie(): void
    {
        $tied = ['A' => [
            ['tpid' => 1, 'rank' => 1, 'grp' => 'A', 'points' => 4, 'wins' => 2, 'played' => 2, 'set_diff' => 2, 'game_diff' => 4, 'seed' => 1, 'needs_lot' => true],
            ['tpid' => 2, 'rank' => 2, 'points' => 2, 'wins' => 1, 'played' => 2, 'set_diff' => 0, 'game_diff' => 0, 'seed' => 2, 'needs_lot' => false],
        ]];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Losentscheidung in Gruppe A fuer Platz 1 erforderlich.');
        $this->resolver()->codesAllAdvance($tied, [], 'S', 'AC', 1);
    }

    /** @return array<string, array<int, array<string, mixed>>> two groups whose winners are fully tied */
    private function tiedWinners(): array
    {
        $row = fn (int $tpid, string $grp) => [
            'tpid' => $tpid, 'rank' => 1, 'grp' => $grp, 'points' => 4, 'wins' => 2, 'losses' => 0, 'played' => 2,
            'set_diff' => 4, 'game_diff' => 12, 'seed' => $tpid, 'needs_lot' => false,
        ];

        return ['A' => [$row(1, 'A')], 'B' => [$row(2, 'B')]];
    }

    #[Test]
    public function manual_lot_order_beats_the_resolver_closure(): void
    {
        $calls = [];
        $r = $this->resolver()->setLotResolver($this->reverseResolver($calls))->setLotOrders(['phase:AC:place:1' => [1, 2]]);

        $rows = $r->byPlace(1, $this->tiedWinners(), [], 'AC');

        $this->assertSame([1, 2], array_column($rows, 'tpid'));
        $this->assertSame([], $calls, 'the closure is not asked when a manual order exists');
        $this->assertSame([false, false], array_column($rows, 'needs_lot'));
    }

    #[Test]
    public function a_manual_order_that_does_not_fit_the_tie_is_ignored_and_reported(): void
    {
        $calls = [];
        $r = $this->resolver()->setLotResolver($this->reverseResolver($calls))->setLotOrders(['phase:AC:place:1' => [1, 3]]);

        $rows = $r->byPlace(1, $this->tiedWinners(), [], 'AC');

        $this->assertSame([['scope' => 'phase:AC:place:1', 'tied' => [1, 2]]], $calls, 'falls back to the derived order');
        $this->assertSame([2, 1], array_column($rows, 'tpid'));
        $this->assertSame(['phase:AC:place:1'], $r->staleScopes());
        $this->assertSame([], $this->resolver()->setLotOrders(['phase:AC:place:1' => [2, 1]])->staleScopes());
    }

    #[Test]
    public function resolver_closure_decides_a_tie_without_a_manual_order(): void
    {
        $calls = [];
        $r = $this->resolver()->setLotResolver($this->reverseResolver($calls));

        $rows = $r->byPlace(1, $this->tiedWinners(), [], 'AC');

        $this->assertSame([['scope' => 'phase:AC:place:1', 'tied' => [1, 2]]], $calls);
        $this->assertSame([2, 1], array_column($rows, 'tpid'));
    }

    #[Test]
    public function resolver_is_not_called_for_fewer_than_two_tied(): void
    {
        $calls = [];
        $r = new ResolverService((new StandingsService)->setRunnerUpMode('lot'));
        $r->setLotResolver($this->reverseResolver($calls));

        $single = ['A' => [['tpid' => 1, 'rank' => 1, 'grp' => 'A', 'seed' => 1, 'needs_lot' => false]]];
        $rows = $r->byPlace(1, $single, [], 'AC');

        $this->assertSame([], $calls);
        $this->assertTrue($rows[0]['needs_lot'], 'lot mode flags the lone candidate and nothing resolves it');
    }
}
