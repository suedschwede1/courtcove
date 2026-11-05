<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\Tournament\Services\LotOrderService;
use App\Modules\Tournament\Services\NoCurrentTournament;
use App\Modules\Tournament\Services\TournamentManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TournamentManagerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, mixed> */
    private static function vectors(): array
    {
        return json_decode((string) file_get_contents(dirname(__DIR__, 3).'/Fixtures/tournament/vectors.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function scenarios(): array
    {
        $cases = [];
        foreach (self::vectors()['manager'] as $case) {
            $cases[$case['name']] = [$case];
        }

        return $cases;
    }

    /**
     * Decimal columns come back as strings on MySQL and as numbers on SQLite; the vectors were recorded
     * with strings, so both sides are compared with one decimal.
     */
    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $item) {
            if (($key === 'itn_rated' || $key === 'itn') && is_numeric($item)) {
                $value[$key] = sprintf('%.1f', (float) $item);
            } else {
                $value[$key] = $this->normalize($item);
            }
        }

        return $value;
    }

    /** @param  array<string, mixed>  $case */
    private function seedScenario(array $case): TournamentManager
    {
        DB::table('bs_tournaments')->insert($case['tournament']);
        DB::table('bs_tournament_players')->insert($case['players']);
        DB::table('bs_tournament_matches')->insert($case['matches']);

        return $this->app->make(TournamentManager::class);
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
        $this->assertSame($this->normalize($result['ok']), $this->normalize($call()), $message);
    }

    /**
     * Everything that does not depend on a lot decision: identical with and without a draw.
     *
     * @param  array<string, mixed>  $case
     */
    #[Test]
    #[DataProvider('scenarios')]
    public function lot_independent_outputs_match_the_old_code(array $case): void
    {
        $m = $this->seedScenario($case);
        $e = $case['expected'];

        $this->assertSame($e['group_complete'], array_combine(array_keys($e['group_complete']), array_map(fn ($g) => $m->groupComplete(1, $g), array_keys($e['group_complete']))));
        $this->assertSame($e['phase_complete'], ['AC' => $m->phaseComplete(1, 'AC'), 'DG' => $m->phaseComplete(1, 'DG')]);
        $this->assertSame($e['phase_matches_AC'], count($m->phaseMatches(1, 'AC')));
        $states = [];
        foreach ($m->matches(1) as $row) {
            $states[(int) $row['tmid']] = $m->matchState($row);
        }
        foreach ($e['match_states'] as $s) {
            $this->assertSame($s['state'], $states[$s['tmid']], 'match '.$s['mkey']);
        }
        $this->assertSame($this->normalize($e['draw_pool_DG']), $this->normalize($m->drawPool(1, 'DG')));
        $this->assertSame($this->normalize($e['draw_pool_AC']), $this->normalize($m->drawPool(1, 'AC')));
        $this->assertSame($this->normalize($e['group_players_C']), $this->normalize($m->groupPlayers(1, 'C')));
        $this->assertSame($e['player_matches_8'], array_map(fn ($r) => (int) $r['tmid'], $m->playerMatches(1, 8)));
        $this->assertSame($e['current_tid'], (int) $m->current()->tid);
    }

    #[Test]
    public function without_a_draw_the_calculated_outputs_match_the_old_code(): void
    {
        $case = self::vectors()['manager'][1];
        $this->assertSame('without-draw', $case['name']);
        $m = $this->seedScenario($case);
        $e = $case['expected'];

        foreach ($e['tables'] as $grp => $table) {
            $this->assertSame($this->normalize($table), $this->normalize($m->table(1, $grp)), "table $grp");
        }
        $this->assertCaptured($e['promotion_AC'], fn () => $m->promotion(1, 'AC'), 'promotion AC');
        $this->assertCaptured($e['promotion_AC_one_second'], fn () => $m->promotion(1, 'AC', 1), 'promotion AC one second');
        $this->assertCaptured($e['phase_standings_AC'], fn () => $m->phaseStandings(1, 'AC'), 'phase standings AC');
        $this->assertCaptured($e['progress'], fn () => $m->progress(1), 'progress');
    }

    #[Test]
    public function current_ignores_test_copies_and_prefers_active(): void
    {
        $older = Tournament::factory()->create(['season' => 2026]);
        $newerReal = Tournament::factory()->create(['season' => 2027]);
        Tournament::factory()->test()->create(['season' => 2028]);
        $m = $this->app->make(TournamentManager::class);

        $this->assertSame($newerReal->tid, $m->current()->tid, 'a newer test copy never becomes current');

        $older->update(['active' => 1]);
        $this->assertSame($older->tid, $m->current()->tid, 'the active flag beats the season');

        $this->assertSame(
            array_map(fn (Tournament $t) => $t->tid, $m->all()),
            Tournament::query()->orderByDesc('season')->orderByDesc('tid')->pluck('tid')->all(),
            'all() lists newest first and includes test copies'
        );
    }

    #[Test]
    public function current_throws_without_a_real_tournament(): void
    {
        $m = $this->app->make(TournamentManager::class);
        try {
            $m->current();
            $this->fail('expected NoCurrentTournament');
        } catch (NoCurrentTournament $e) {
            $this->assertSame('no tournament configured', $e->getMessage());
        }

        Tournament::factory()->test()->create();
        $this->expectException(NoCurrentTournament::class);
        $m->current();
    }

    #[Test]
    public function get_throws_model_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->app->make(TournamentManager::class)->get(999);
    }

    #[Test]
    public function match_state_follows_winner_then_played_date(): void
    {
        $m = $this->app->make(TournamentManager::class);

        $this->assertSame('played', $m->matchState(['winner' => 7, 'played' => null]));
        $this->assertSame('played', $m->matchState(['winner' => 7, 'played' => '2027-05-02']));
        $this->assertSame('scheduled', $m->matchState(['winner' => null, 'played' => '2027-05-02']));
        $this->assertSame('open', $m->matchState(['winner' => null, 'played' => null]));
        $this->assertSame('open', $m->matchState(['winner' => 0, 'played' => null]));
    }

    #[Test]
    public function cache_is_per_instance_and_forget_clears_it(): void
    {
        $t = Tournament::factory()->create();
        TournamentPlayer::factory()->create(['tid' => $t->tid, 'seed' => 1, 'label' => 'Demo Spieler 01']);
        $first = $this->app->make(TournamentManager::class);
        $this->assertCount(1, $first->players($t->tid));

        TournamentPlayer::factory()->create(['tid' => $t->tid, 'seed' => 2, 'label' => 'Demo Spieler 02']);
        TournamentMatch::factory()->create(['tid' => $t->tid, 'phase' => 'group', 'grp' => 'A', 'mkey' => 'A-0', 'slot' => 1, 'tpid_a' => 1, 'tpid_b' => 2]);

        $this->assertCount(1, $first->players($t->tid), 'cached within the instance');

        $first->forget($t->tid);
        $this->assertCount(2, $first->players($t->tid), 'forget() clears the per-tid cache');
        $this->assertCount(1, $first->matches($t->tid));

        $second = new TournamentManager($this->app->make(LotOrderService::class));
        $this->assertCount(2, $second->players($t->tid), 'a new instance starts with an empty cache');
    }

    #[Test]
    public function players_come_ordered_by_seed_then_tpid_as_attribute_rows(): void
    {
        $t = Tournament::factory()->create();
        $c = TournamentPlayer::factory()->create(['tid' => $t->tid, 'seed' => 2]);
        $a = TournamentPlayer::factory()->create(['tid' => $t->tid, 'seed' => 1]);
        $b = TournamentPlayer::factory()->create(['tid' => $t->tid, 'seed' => 1]);
        $m = $this->app->make(TournamentManager::class);

        $players = $m->players($t->tid);

        $this->assertSame([$a->tpid, $b->tpid, $c->tpid], array_keys($players));
        $this->assertSame($a->tpid, $m->player($t->tid, $a->tpid)['tpid']);
        $this->assertNull($m->player($t->tid, 99999));
        $this->assertArrayNotHasKey('relations', $players[$a->tpid]);
    }
}
