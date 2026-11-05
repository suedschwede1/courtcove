<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Services\ChallengeService;
use App\Modules\Pyramid\Services\LadderAdminService;
use App\Modules\Pyramid\Services\ResultService;
use App\Modules\Pyramid\Support\PyramidRuleException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsPyramidLadders;
use Tests\MysqlTestCase;

/**
 * Review Focus 5: lockForUpdate() compiles to nothing on SQLite, so only a real MySQL server can prove that the
 * services lock in ZF2's order and that the ladder row really serialises writers.
 *
 * Rows must be COMMITTED for the second connection to see them, so there is no wrapping transaction: the pyramid
 * tables are truncated before every test (after the MysqlTestCase guard has passed, never before).
 */
#[Group('mysql')]
class PyramidMysqlLockTest extends MysqlTestCase
{
    use BuildsPyramidLadders;

    private static bool $migrated = false;

    private const TABLES = [
        'bs_pyramid_events', 'bs_pyramid_challenges', 'bs_pyramid_grants', 'bs_pyramid_players', 'bs_pyramid_ladders',
    ];

    /** @var list<string> */
    private array $locks = [];

    /** True once setUp passed the MySQL guard and registered the second connection. */
    private bool $ready = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$migrated) {
            $this->artisan('migrate:fresh');
            self::$migrated = true;
        }

        foreach (self::TABLES as $table) {
            DB::table($table)->truncate();
        }

        // Cloned from the connection MysqlTestCase has just verified: same local booking_test database.
        config(['database.connections.mysql_second' => config('database.connections.mysql')]);
        DB::purge('mysql_second');

        $this->locks = [];
        $this->ready = true;
    }

    protected function tearDown(): void
    {
        if (! $this->ready) {
            parent::tearDown();

            return;
        }

        if (DB::connection('mysql_second')->transactionLevel() > 0) {
            DB::connection('mysql_second')->rollBack();
        }

        DB::statement('SET SESSION innodb_lock_wait_timeout = 50');
        DB::disconnect('mysql_second');

        parent::tearDown();
    }

    private function captureLocks(): void
    {
        $this->locks = [];

        DB::listen(function ($query): void {
            if (str_contains($query->sql, 'for update')) {
                $this->locks[] = $query->sql;
            }
        });
    }

    /**
     * @param  list<string>  $tables  expected lock sequence, one table name (optionally with a tail regex) per entry
     */
    private function assertLockSequence(array $tables, string $message): void
    {
        $this->assertCount(count($tables), $this->locks, $message.': '.implode(' | ', $this->locks));

        foreach ($tables as $i => $expected) {
            $this->assertMatchesRegularExpression('/^select (\*|`id`) from `'.$expected.'`/', $this->locks[$i], $message.' #'.$i);
        }
    }

    // Lock order (ZF2: ChallengeService, ResultService)

    #[Test]
    public function lock_order_matches_zf2(): void
    {
        $ladder = $this->makeLadder(8);
        $a = $this->playerAt($ladder, 8);
        $b = $this->playerAt($ladder, 5);
        $this->captureLocks();

        // create(): ladder row, then both player rows ORDER BY id.
        $challenge = app(ChallengeService::class)->create($ladder, $a, $b, null);

        $this->assertLockSequence(['bs_pyramid_ladders', 'bs_pyramid_players'], 'create');
        $this->assertMatchesRegularExpression('/`bs_pyramid_ladders` where (`bs_pyramid_ladders`\.)?`id` = \? limit 1 for update$/', $this->locks[0]);
        $this->assertMatchesRegularExpression('/`bs_pyramid_players` where `id` in \(\?, \?\) order by `id` asc for update$/', $this->locks[1]);

        // transition(): ladder row, then the challenge row.
        $this->locks = [];
        app(ChallengeService::class)->transition((int) $challenge->id, ['deadline' => '2030-01-01'], 'admin', ['message' => 'x'], null);

        $this->assertLockSequence(['bs_pyramid_ladders', 'bs_pyramid_challenges'], 'transition');
        $this->assertMatchesRegularExpression('/`bs_pyramid_challenges` where (`bs_pyramid_challenges`\.)?`id` = \? limit 1 for update$/', $this->locks[1]);

        // record(): ladder row, ALL ladder players ORDER BY id, then the challenge row.
        $this->locks = [];
        app(ResultService::class)->record($ladder, $challenge, ['s1a' => 6, 's1b' => 4, 's2a' => 6, 's2b' => 3], null);

        $this->assertLockSequence(['bs_pyramid_ladders', 'bs_pyramid_players', 'bs_pyramid_challenges'], 'record');
        $this->assertMatchesRegularExpression('/`bs_pyramid_players` where `ladder_id` = \? order by `id` asc for update$/', $this->locks[1]);
        $this->assertMatchesRegularExpression('/`bs_pyramid_challenges` where (`bs_pyramid_challenges`\.)?`id` = \? limit 1 for update$/', $this->locks[2]);
    }

    #[Test]
    public function forfeit_locks_like_record(): void
    {
        $ladder = $this->makeLadder(6);
        $challenge = app(ChallengeService::class)->create($ladder, $this->playerAt($ladder, 5), $this->playerAt($ladder, 3), null);
        $this->captureLocks();

        app(ResultService::class)->recordForfeit($ladder, $challenge, true, 'no show', null);

        $this->assertLockSequence(['bs_pyramid_ladders', 'bs_pyramid_players', 'bs_pyramid_challenges'], 'recordForfeit');
        $this->assertMatchesRegularExpression('/`bs_pyramid_players` where `ladder_id` = \? order by `id` asc for update$/', $this->locks[1]);
    }

    #[Test]
    public function admin_operations_take_the_ladder_row_first(): void
    {
        $ladder = $this->makeLadder(6);
        $admin = app(LadderAdminService::class);

        $this->captureLocks();
        $admin->movePlayer($ladder, $this->playerAt($ladder, 6), 2);
        $this->assertLockSequence(['bs_pyramid_ladders', 'bs_pyramid_players'], 'movePlayer');
        $this->assertMatchesRegularExpression('/`bs_pyramid_players` where `ladder_id` = \? order by `id` asc for update$/', $this->locks[1]);

        $this->locks = [];
        $admin->normalize($ladder, null);
        $this->assertSame('bs_pyramid_ladders', $this->tableOf($this->locks[0] ?? ''), 'normalize starts with the ladder');

        $challenge = app(ChallengeService::class)->create($ladder, $this->playerAt($ladder->fresh(), 5), $this->playerAt($ladder->fresh(), 4), null);

        $this->locks = [];
        $admin->extendDeadline($challenge, now()->addDays(3)->format('d.m.Y'), 'ok', null);
        $this->assertLockSequence(['bs_pyramid_ladders', 'bs_pyramid_challenges'], 'extendDeadline (transition)');

        $this->locks = [];
        $admin->forfeit($challenge, true, 'no show', null);
        $this->assertLockSequence(['bs_pyramid_ladders', 'bs_pyramid_players', 'bs_pyramid_challenges'], 'forfeit (record order)');

        $this->locks = [];
        $admin->correctResult($challenge, (int) $challenge->opponent_id, '6:0 6:0', 'fix', null);
        $this->assertLockSequence(['bs_pyramid_ladders', 'bs_pyramid_challenges'], 'correctResult');

        $this->locks = [];
        $admin->close($ladder, null);
        $this->assertLockSequence(['bs_pyramid_ladders'], 'close');
    }

    private function tableOf(string $sql): string
    {
        return preg_match('/^select (?:\*|`id`) from `([a-z_]+)`/', $sql, $m) === 1 ? $m[1] : '';
    }

    // Races

    private function holdLadderLockOnSecondConnection(PyramidLadder $ladder): void
    {
        $second = DB::connection('mysql_second');
        $second->beginTransaction();
        $second->select('select id from bs_pyramid_ladders where id = ? for update', [$ladder->id]);

        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
    }

    private function assertLockWaitTimeout(callable $write): void
    {
        try {
            $write();
            $this->fail('expected a lock wait timeout');
        } catch (QueryException $e) {
            $this->assertSame(1205, $e->errorInfo[1]);
        }
    }

    #[Test]
    public function ladder_lock_blocks_a_second_writer(): void
    {
        $ladder = $this->makeLadder(8);
        $p8 = $this->playerAt($ladder, 8);
        $p5 = $this->playerAt($ladder, 5);

        $this->holdLadderLockOnSecondConnection($ladder);

        $this->assertLockWaitTimeout(fn () => app(ChallengeService::class)->create($ladder, $p8, $p5, null));
        $this->assertSame(0, PyramidChallenge::query()->count(), 'the blocked writer left nothing behind');

        DB::connection('mysql_second')->rollBack();
        DB::statement('SET SESSION innodb_lock_wait_timeout = 50');

        $this->assertNotNull(app(ChallengeService::class)->create($ladder->fresh(), $p8->fresh(), $p5->fresh(), null));
    }

    #[Test]
    public function ladder_lock_blocks_result_and_transition_writers(): void
    {
        $ladder = $this->makeLadder(6);
        $challenge = app(ChallengeService::class)->create($ladder, $this->playerAt($ladder, 5), $this->playerAt($ladder, 3), null);
        $before = $this->positionsByLabel((int) $ladder->id);

        $this->holdLadderLockOnSecondConnection($ladder);

        $this->assertLockWaitTimeout(fn () => app(ResultService::class)->record(
            $ladder, $challenge, ['s1a' => 6, 's1b' => 4, 's2a' => 6, 's2b' => 3], null
        ));
        $this->assertLockWaitTimeout(fn () => app(ChallengeService::class)->transition(
            (int) $challenge->id, ['status' => 'withdrawn'], 'admin', ['message' => 'x'], null
        ));

        $this->assertSame($before, $this->positionsByLabel((int) $ladder->id), 'blocked writers did not move anyone');
        $this->assertSame('open', PyramidChallenge::query()->findOrFail($challenge->id)->status);
    }

    #[Test]
    public function a_second_writer_on_the_same_challenge_is_rejected_after_the_lock(): void
    {
        $ladder = $this->makeLadder(6);
        $challenge = app(ChallengeService::class)->create($ladder, $this->playerAt($ladder, 5), $this->playerAt($ladder, 3), null);
        $input = ['s1a' => 6, 's1b' => 4, 's2a' => 6, 's2b' => 3];

        app(ResultService::class)->record($ladder, $challenge, $input, null);
        $after = $this->positionsByLabel((int) $ladder->id);

        // $challenge is the stale 'open' snapshot a racing request would still hold.
        try {
            app(ResultService::class)->record($ladder, $challenge, $input, null);
            $this->fail('the stale challenge must be rejected');
        } catch (PyramidRuleException) {
            $this->assertSame($after, $this->positionsByLabel((int) $ladder->id));
        }

        $this->assertRankingIntact((int) $ladder->id);
    }
}
