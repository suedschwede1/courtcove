<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Services\DrawService;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Services\LotOrderService;
use App\Modules\Tournament\Services\TournamentManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LotOrderServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, mixed> */
    private static function vectors(): array
    {
        return json_decode((string) file_get_contents(dirname(__DIR__, 3).'/Fixtures/tournament/vectors.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function lotCases(): array
    {
        $cases = [];
        foreach (self::vectors()['lot'] as $case) {
            $cases[$case['name']] = [$case];
        }

        return $cases;
    }

    /** @return array<string, mixed> */
    private static function lotCase(string $name): array
    {
        return array_column(self::vectors()['lot'], null, 'name')[$name];
    }

    private function service(): LotOrderService
    {
        return $this->app->make(LotOrderService::class);
    }

    /**
     * Creates tournament 1 and its draws exactly as the vector case describes them.
     *
     * @param  array<string, mixed>  $case
     */
    private function seedCase(array $case): Tournament
    {
        $tournament = Tournament::factory()->create(['tid' => 1, 'lot_orders' => $case['lot_orders_json']]);
        $this->seedDraws($case['draws']);

        return $tournament;
    }

    /** @param  list<array{did: int, final_seed: string|null, discarded: bool}>  $draws */
    private function seedDraws(array $draws): void
    {
        foreach ($draws as $d) {
            Draw::factory()->create([
                'did' => $d['did'],
                'tid' => 1,
                'final_seed' => $d['final_seed'],
                'discarded' => $d['discarded'] ? '2027-03-01 10:00:00' : null,
            ]);
        }
    }

    /** @param  array<string, mixed>  $case */
    #[Test]
    #[DataProvider('lotCases')]
    public function lot_vectors_match_the_old_code(array $case): void
    {
        $t = $this->seedCase($case);
        $service = $this->service();

        $this->assertSame($case['expected_order'], $service->lotOrder($t, $case['scope'], $case['rows']), $case['name']);
        $this->assertSame($case['expected_seed'], $service->lotSeed($t), $case['name'].' seed');
    }

    #[Test]
    public function hmac_uses_scope_as_message_and_seed_as_key(): void
    {
        $seen = 0;
        $discriminating = 0;
        foreach (self::vectors()['lot'] as $case) {
            if ($case['expected_seed'] === null) {
                continue;
            }
            $seen++;
            $this->assertSame(hash_hmac('sha256', $case['scope'], $case['expected_seed']), $case['expected_hmac'], $case['name']);
            $this->assertNotSame(hash_hmac('sha256', $case['expected_seed'], $case['scope']), $case['expected_hmac'], $case['name'].' (swapped arguments)');

            // The derived order is exactly DrawService::shuffle over the sorted tied tpids with that HMAC.
            $tied = [];
            foreach ($case['rows'] as $row) {
                if (! empty($row['needs_lot'])) {
                    $tied[] = $row['tpid'];
                }
            }
            sort($tied);
            $manual = json_decode((string) $case['lot_orders_json'], true);
            $hasManual = is_array($manual) && isset($manual[$case['scope']]) && is_array($manual[$case['scope']]) && $manual[$case['scope']];
            if (! $hasManual && count($tied) >= 2) {
                $this->assertSame((new DrawService)->shuffle($tied, $case['expected_hmac']), $case['expected_order'], $case['name'].' order');

                // The ported service itself, on a clean database per case.
                DB::table('bs_draws')->delete();
                Tournament::query()->delete();
                $t = $this->seedCase($case);
                $actual = $this->service()->lotOrder($t, $case['scope'], $case['rows']);
                $this->assertSame($case['expected_order'], $actual, $case['name'].' service order');

                $swapped = (new DrawService)->shuffle($tied, hash_hmac('sha256', $case['expected_seed'], $case['scope']));
                if (count($tied) >= 3 && $swapped !== $case['expected_order']) {
                    $this->assertNotSame($swapped, $actual, $case['name'].' must not use swapped HMAC arguments');
                    $discriminating++;
                }
            }
        }
        $this->assertGreaterThan(5, $seen);
        $this->assertGreaterThan(0, $discriminating, 'at least one case must tell swapped arguments apart');
    }

    #[Test]
    public function first_draw_discarded_falls_through_to_the_next_executed_draw(): void
    {
        $case = self::lotCase('first-discarded');
        $t = $this->seedCase($case);

        $seedOfDid5 = hash('sha256', 'demo-lot-seed-5');
        $this->assertSame($seedOfDid5, $this->service()->lotSeed($t));
        $this->assertSame($case['expected_seed'], $seedOfDid5);
        $this->assertNotSame(hash('sha256', 'demo-lot-seed-3'), $this->service()->lotSeed($t), 'the discarded draw 3 is never used');
    }

    #[Test]
    public function the_lowest_did_wins_whatever_the_row_order_is(): void
    {
        $case = self::lotCase('first-executed');
        $this->assertSame([5, 3], array_column($case['draws'], 'did'), 'the vector lists did 5 before did 3');
        $t = $this->seedCase($case);

        $this->assertSame(hash('sha256', 'demo-lot-seed-3'), $this->service()->lotSeed($t));
    }

    #[Test]
    public function no_executed_draw_keeps_needs_lot(): void
    {
        foreach (['no-draw', 'all-discarded', 'only-committed'] as $name) {
            $case = self::lotCase($name);
            $this->assertSame([], $case['expected_order'], $name);
            $this->assertNull($case['expected_seed'], $name);

            DB::table('bs_draws')->delete();
            Tournament::query()->delete();
            $t = $this->seedCase($case);
            $this->assertSame([], $this->service()->lotOrder($t, $case['scope'], $case['rows']), $name);
            $this->assertNull($this->service()->lotSeed($t), $name);
        }

        // Through the read model: the table keeps the tie flagged without a draw, and a draw resolves it.
        $scenario = self::vectors()['manager'][1];
        $this->assertSame('without-draw', $scenario['name']);
        DB::table('bs_draws')->delete();
        Tournament::query()->delete();
        $m = $this->seedManager($scenario, []);
        $table = $m->table(1, 'C');
        $flagged = array_column(array_filter($table, fn (array $r) => $r['needs_lot']), 'tpid');
        sort($flagged);
        $this->assertSame([8, 9, 10], $flagged);

        // all draws discarded: still unresolved
        $this->seedDraws([['did' => 4, 'final_seed' => hash('sha256', 'x'), 'discarded' => true]]);
        $m->forget(1);
        $this->assertCount(3, array_filter($m->table(1, 'C'), fn (array $r) => $r['needs_lot']));

        // an executed draw decides it
        $this->seedDraws([['did' => 6, 'final_seed' => hash('sha256', 'y'), 'discarded' => false]]);
        $m->forget(1);
        $this->assertCount(0, array_filter($m->table(1, 'C'), fn (array $r) => $r['needs_lot']));
    }

    #[Test]
    public function a_later_draw_never_changes_a_decided_tie(): void
    {
        $case = self::lotCase('scope-group');
        $t = $this->seedCase($case);
        $service = $this->service();

        $before = $service->lotOrder($t, $case['scope'], $case['rows']);
        $this->assertSame($case['expected_order'], $before);

        $this->seedDraws([['did' => 9, 'final_seed' => hash('sha256', 'a-later-draw'), 'discarded' => false]]);
        $service->forget(1);

        $this->assertSame($before, $service->lotOrder($t, $case['scope'], $case['rows']));
        $this->assertSame($case['expected_seed'], $service->lotSeed($t));
        $this->assertSame($before, $this->app->make(LotOrderService::class)->lotOrder($t, $case['scope'], $case['rows']));
    }

    #[Test]
    public function rows_order_does_not_matter(): void
    {
        $case = self::lotCase('unsorted-tied');
        $t = $this->seedCase($case);

        $this->assertSame($case['expected_order'], $this->service()->lotOrder($t, $case['scope'], array_reverse($case['rows'])));
        $this->assertSame($case['expected_order'], $this->service()->lotOrder($t, $case['scope'], $case['rows']));
    }

    #[Test]
    public function the_seed_cache_is_per_tid_and_per_instance(): void
    {
        $case = self::lotCase('first-executed');
        $t = $this->seedCase($case);
        $service = $this->service();
        $this->assertSame(hash('sha256', 'demo-lot-seed-3'), $service->lotSeed($t));

        // a lower did executed later is not seen by the same instance until forget() ...
        $this->seedDraws([['did' => 1, 'final_seed' => hash('sha256', 'demo-lot-seed-1'), 'discarded' => false]]);
        $this->assertSame(hash('sha256', 'demo-lot-seed-3'), $service->lotSeed($t));
        $service->forget(1);
        $this->assertSame(hash('sha256', 'demo-lot-seed-1'), $service->lotSeed($t));

        // ... and a fresh instance always reads the database
        $this->assertSame(hash('sha256', 'demo-lot-seed-1'), (new LotOrderService(new DrawService))->lotSeed($t));

        // other tournaments have their own seed
        $other = Tournament::factory()->create(['tid' => 2]);
        $this->assertNull($service->lotSeed($other));
    }

    /**
     * Seeds tournament 1 of a manager scenario with the given draws.
     *
     * @param  array<string, mixed>  $scenario
     * @param  list<array<string, mixed>>  $draws
     */
    private function seedManager(array $scenario, array $draws): TournamentManager
    {
        DB::table('bs_tournaments')->insert($scenario['tournament']);
        DB::table('bs_tournament_players')->insert($scenario['players']);
        DB::table('bs_tournament_matches')->insert($scenario['matches']);
        foreach ($draws as $d) {
            Draw::factory()->create($d);
        }

        return $this->app->make(TournamentManager::class);
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $item) {
            $value[$key] = (($key === 'itn_rated' || $key === 'itn') && is_numeric($item))
                ? sprintf('%.1f', (float) $item)
                : $this->normalize($item);
        }
        if (isset($value['tmid'])) {
            ksort($value); // match rows: the column order of the table is not part of the contract
        }

        return $value;
    }

    #[Test]
    public function manager_outputs_with_an_executed_draw_match_the_old_code(): void
    {
        $case = self::vectors()['manager'][0];
        $this->assertSame('with-executed-draw', $case['name']);
        $m = $this->seedManager($case, array_map(fn (array $d) => ['did' => $d['did'], 'tid' => $d['tid'], 'final_seed' => $d['final_seed'], 'discarded' => $d['discarded']], $case['draws']));
        $e = $case['expected'];

        foreach ($e['tables'] as $grp => $table) {
            $this->assertSame($this->normalize($table), $this->normalize($m->table(1, $grp)), "table $grp");
        }
        foreach (['promotion_AC' => fn () => $m->promotion(1, 'AC'), 'promotion_AC_one_second' => fn () => $m->promotion(1, 'AC', 1),
            'phase_standings_AC' => fn () => $m->phaseStandings(1, 'AC'), 'progress' => fn () => $m->progress(1)] as $key => $call) {
            if (array_key_exists('error', $e[$key])) {
                try {
                    $call();
                    $this->fail("$key: expected exception");
                } catch (InvalidArgumentException $ex) {
                    $this->assertSame($e[$key]['error'], $ex->getMessage(), $key);
                }
            } else {
                $this->assertSame($this->normalize($e[$key]['ok']), $this->normalize($call()), $key);
            }
        }
    }
}
