<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\Tournament\Services\GenerationService;
use App\Modules\Tournament\Services\TournamentManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Throwable;

/**
 * Port of the SQLite-portable part of the old modulex/tests/integration-db.php plus the stale-read fix.
 */
class GenerationServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** Heads of the reference format (heads = 1): seeds 1-3 on A1-C1, seeds 11-14 on D1-G1. */
    private const HEADS = [1 => 'A', 2 => 'B', 3 => 'C', 11 => 'D', 12 => 'E', 13 => 'F', 14 => 'G'];

    private Tournament $tournament;

    /** @var array<int, int> seed => tpid */
    private array $tpidBySeed = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tournament = Tournament::factory()->active()->create();

        for ($seed = 1; $seed <= 24; $seed++) {
            $state = ['tid' => $this->tournament->tid, 'seed' => $seed];
            if (isset(self::HEADS[$seed])) {
                $state += ['grp' => self::HEADS[$seed], 'grp_pos' => 1];
            }
            $this->tpidBySeed[$seed] = (int) TournamentPlayer::factory()->create($state)->tpid;
        }
    }

    private function service(): GenerationService
    {
        return $this->app->make(GenerationService::class);
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        return [
            'lines' => DB::table('bs_tournament_lines')->count(),
            'matches' => DB::table('bs_tournament_matches')->count(),
        ];
    }

    #[Test]
    public function first_generation_creates_lines_and_bracket_matches(): void
    {
        $result = $this->service()->generate((int) $this->tournament->tid);

        $this->assertSame(['heads' => 0, 'matches' => 0, 'lines' => 24, 'bracketMatches' => 22], $result['created']);
        $this->assertSame(['lines' => 24, 'matches' => 22], $this->counts());
        $this->assertCount(7, $result['skipped']);
        $this->assertSame('Gruppe A: 1 von 3 Plaetzen besetzt, 3 Partie(n) noch offen - Partien folgen nach der Auslosung', $result['skipped'][0]);
    }

    #[Test]
    public function generate_current_uses_the_current_tournament(): void
    {
        $this->assertSame(24, $this->service()->generateCurrent()['created']['lines']);
    }

    #[Test]
    public function preseeded_heads_are_not_rewritten(): void
    {
        $this->service()->generate((int) $this->tournament->tid);

        $this->assertSame(7, TournamentPlayer::query()->where('grp_pos', 1)->count());
        foreach (self::HEADS as $seed => $grp) {
            $player = TournamentPlayer::query()->findOrFail($this->tpidBySeed[$seed]);
            $this->assertSame($grp, $player->grp);
        }
    }

    #[Test]
    public function missing_heads_are_assigned_and_counted(): void
    {
        TournamentPlayer::query()->where('tpid', $this->tpidBySeed[2])->update(['grp' => null, 'grp_pos' => null]);

        $result = $this->service()->generate((int) $this->tournament->tid);

        $this->assertSame(1, $result['created']['heads']);
        $player = TournamentPlayer::query()->findOrFail($this->tpidBySeed[2]);
        $this->assertSame(['B', 1], [$player->grp, (int) $player->grp_pos]);
    }

    #[Test]
    public function second_generation_is_idempotent(): void
    {
        $this->service()->generate((int) $this->tournament->tid);
        $this->app->forgetScopedInstances();

        $second = $this->service()->generate((int) $this->tournament->tid);

        $this->assertSame(['heads' => 0, 'matches' => 0, 'lines' => 0, 'bracketMatches' => 0], $second['created']);
        $this->assertSame(['lines' => 24, 'matches' => 22], $this->counts());
    }

    #[Test]
    public function group_matches_appear_once_all_group_players_are_placed(): void
    {
        TournamentPlayer::query()->where('tpid', $this->tpidBySeed[4])->update(['grp' => 'A', 'grp_pos' => 2]);
        TournamentPlayer::query()->where('tpid', $this->tpidBySeed[5])->update(['grp' => 'A', 'grp_pos' => 3]);

        $result = $this->service()->generate((int) $this->tournament->tid);

        $this->assertSame(3, $result['created']['matches']);
        $rows = TournamentMatch::query()->where('phase', 'group')->orderBy('slot')->get();
        $this->assertSame(['A-0', 'A-1', 'A-2'], $rows->pluck('mkey')->all());
        $this->assertSame([1, 2, 3], $rows->pluck('slot')->map(fn ($s) => (int) $s)->all());
        $this->assertCount(6, $result['skipped']);
    }

    #[Test]
    public function a_failure_rolls_back_everything(): void
    {
        // GeneratorService is final; the old integration test forced the failure with a trigger on the match
        // insert, which happens after the bracket lines (no group is complete, so the first insert is a KO match).
        DB::unprepared("CREATE TRIGGER fail_match_insert BEFORE INSERT ON bs_tournament_matches BEGIN SELECT RAISE(ABORT, 'forced integration failure'); END");
        TournamentPlayer::query()->where('tpid', $this->tpidBySeed[2])->update(['grp' => null, 'grp_pos' => null]);

        try {
            $this->service()->generate((int) $this->tournament->tid);
            $this->fail('the forced failure must reach the caller');
        } catch (Throwable $e) {
            $this->assertStringContainsString('forced integration failure', $e->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER fail_match_insert');
        }

        $this->assertSame(['lines' => 0, 'matches' => 0], $this->counts());
        $this->assertNull(TournamentPlayer::query()->findOrFail($this->tpidBySeed[2])->grp, 'head update rolled back');
    }

    #[Test]
    public function a_failure_does_not_leave_stale_rows_in_the_manager(): void
    {
        DB::unprepared("CREATE TRIGGER fail_match_insert BEFORE INSERT ON bs_tournament_matches BEGIN SELECT RAISE(ABORT, 'forced'); END");
        TournamentPlayer::query()->where('tpid', $this->tpidBySeed[2])->update(['grp' => null, 'grp_pos' => null]);
        $manager = $this->app->make(TournamentManager::class);

        try {
            $this->service()->generate((int) $this->tournament->tid);
        } catch (Throwable) {
        } finally {
            DB::unprepared('DROP TRIGGER fail_match_insert');
        }

        $this->assertNull($manager->player((int) $this->tournament->tid, $this->tpidBySeed[2])['grp']);
    }

    #[Test]
    public function a_pick_followed_by_generate_sees_the_new_group(): void
    {
        $tid = (int) $this->tournament->tid;
        $manager = $this->app->make(TournamentManager::class);
        $manager->players($tid); // cached before the pick, as the old shared manager was

        // the "pick": the last two players of group A get their positions
        TournamentPlayer::query()->where('tpid', $this->tpidBySeed[4])->update(['grp' => 'A', 'grp_pos' => 2]);
        TournamentPlayer::query()->where('tpid', $this->tpidBySeed[5])->update(['grp' => 'A', 'grp_pos' => 3]);

        $result = $this->service()->generate($tid);

        $this->assertSame(3, $result['created']['matches'], 'the group completed by the pick gets its matches in the same call');
        $this->assertSame('A', $manager->player($tid, $this->tpidBySeed[4])['grp'], 'manager cache is fresh afterwards');
    }
}
