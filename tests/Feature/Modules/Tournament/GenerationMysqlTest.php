<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentLine;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\Tournament\Services\GenerationService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Tournament\Concerns\RebuildsTournamentMysqlTables;
use Tests\MysqlTestCase;
use Tests\Support\SecondConnection;
use Throwable;

/**
 * GenerationService on a real MySQL server: a database error halfway through rolls everything back (the old
 * modulex/tests/integration-db.php scenario), and two concurrent generations never create a match twice.
 */
#[Group('mysql')]
class GenerationMysqlTest extends MysqlTestCase
{
    use RebuildsTournamentMysqlTables;

    private Tournament $tournament;

    /** Format small-two-groups: six players in groups A and B (heads seeds 1 and 2), nothing generated yet. */
    private function championship(): int
    {
        $format = json_decode((string) file_get_contents(base_path('tests/Fixtures/tournament/formats/small-two-groups.json')), true);
        $this->tournament = Tournament::factory()->active()->withFormat($format)->create();

        $layout = [1 => ['A', null], 2 => ['B', null], 3 => ['B', 2], 4 => ['A', 2], 5 => ['A', 3], 6 => ['B', 3]];
        foreach ($layout as $seed => [$group, $pos]) {
            TournamentPlayer::factory()->create([
                'tid' => $this->tournament->tid,
                'uid' => null,
                'label' => sprintf('Demo Spieler %02d', $seed),
                'seed' => $seed,
                'grp' => $pos === null ? null : $group,
                'grp_pos' => $pos,
            ]);
        }

        return (int) $this->tournament->tid;
    }

    /** @return array<int, array<string, mixed>> */
    private function players(int $tid): array
    {
        return TournamentPlayer::query()->where('tid', $tid)->orderBy('tpid')->get()->map->getAttributes()->all();
    }

    #[Test]
    public function forced_generation_error_reaches_the_caller_and_rolls_back(): void
    {
        $tid = $this->championship();
        $before = $this->players($tid);

        // Triggers need SUPER while the binary log is on, so the forced failure is a constraint instead: bracket
        // matches are inserted with grp NULL, after heads, group matches and bracket lines were already written.
        DB::statement('ALTER TABLE bs_tournament_matches MODIFY grp CHAR(1) NOT NULL');

        // Count what was really written before the failure, independent of the order inside the service.
        $written = ['heads' => 0, 'matches' => 0, 'lines' => 0];
        DB::listen(function (QueryExecuted $q) use (&$written): void {
            if (str_starts_with($q->sql, 'update `bs_tournament_players`')) {
                $written['heads']++;
            } elseif (str_starts_with($q->sql, 'insert into `bs_tournament_matches`')) {
                $written['matches']++;
            } elseif (str_starts_with($q->sql, 'insert into `bs_tournament_lines`')) {
                $written['lines']++;
            }
        });

        $failed = null;
        try {
            app(GenerationService::class)->generate($tid);
        } catch (Throwable $e) {
            $failed = $e;
        } finally {
            DB::statement('ALTER TABLE bs_tournament_matches MODIFY grp CHAR(1) NULL DEFAULT NULL');
        }

        $this->assertInstanceOf(QueryException::class, $failed, 'the forced database error reaches the caller');
        $this->assertSame(1048, $failed->errorInfo[1]);
        $this->assertSame(['heads' => 2, 'matches' => 6, 'lines' => 4], $written, 'heads, group matches and lines were written before the failure');
        $this->assertSame(0, TournamentMatch::query()->where('tid', $tid)->count(), 'group and bracket matches rolled back');
        $this->assertSame(0, TournamentLine::query()->where('tid', $tid)->count(), 'bracket lines rolled back');
        $this->assertSame($before, $this->players($tid), 'the head assignment rolled back');

        // Repaired, the same generation goes through.
        $result = app(GenerationService::class)->generate($tid);
        $this->assertSame(6, $result['created']['matches']);
        $this->assertSame(2, $result['created']['heads']);
    }

    #[Test]
    public function concurrent_generations_create_no_duplicate_matches(): void
    {
        $tid = $this->championship();
        $second = SecondConnection::make();
        SecondConnection::waitAtMost(1);

        // B: a full generation inside an open transaction on the second session (models follow the default).
        $default = DB::getDefaultConnection();
        $second->beginTransaction();
        try {
            DB::setDefaultConnection(SecondConnection::NAME);
            $this->app->forgetScopedInstances();
            $first = app(GenerationService::class)->generate($tid);
        } finally {
            DB::setDefaultConnection($default);
            $this->app->forgetScopedInstances();
        }
        $this->assertSame(6, $first['created']['matches']);

        // A runs into B's rows (heads and the unique key tid_mkey) and gives up; nothing of A remains.
        try {
            app(GenerationService::class)->generate($tid);
            $this->fail('the second generation must wait for the first one');
        } catch (QueryException $e) {
            $this->assertSame(1205, $e->errorInfo[1]);
        }

        $second->commit();
        $this->app->forgetScopedInstances();

        $again = app(GenerationService::class)->generate($tid);
        $this->assertSame(0, $again['created']['matches'], 'idempotent after the other generation');
        $this->assertSame(0, $again['created']['bracketMatches']);

        $keys = TournamentMatch::query()->where('tid', $tid)->pluck('mkey')->all();
        $this->assertSame(count($keys), count(array_unique($keys)), 'no match key twice');
        $this->assertSame(6 + $first['created']['bracketMatches'], count($keys));
    }

    #[Test]
    public function the_unique_key_refuses_a_duplicate_match_key(): void
    {
        $tid = $this->championship();
        app(GenerationService::class)->generate($tid);

        try {
            DB::table('bs_tournament_matches')->insert(['tid' => $tid, 'phase' => 'G1', 'mkey' => 'A-0', 'grp' => 'A']);
            $this->fail('tid + mkey must be unique');
        } catch (QueryException $e) {
            $this->assertSame(1062, $e->errorInfo[1]);
        }
    }
}
