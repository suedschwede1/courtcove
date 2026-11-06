<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Draw;

use App\Models\User;
use App\Modules\Draw\Contracts\BeaconService;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\Draw\Services\DrawCloser;
use App\Modules\Draw\Services\DrawLifecycleService;
use App\Modules\Draw\Services\TokenClaimer;
use App\Modules\Draw\Services\TokenPicker;
use App\Modules\Draw\Support\TokenPageContext;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\TournamentBase\Support\ActionRefused;
use App\Modules\TournamentBase\Support\ModuleFlash;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Draw\Concerns\BuildsTokenDraw;
use Tests\Feature\Modules\Tournament\Concerns\RebuildsTournamentMysqlTables;
use Tests\MysqlTestCase;
use Tests\Support\FakeBeaconService;
use Tests\Support\SecondConnection;

/**
 * Review Focus 2 on a real MySQL server: the pick's FOR UPDATE and the UPDATE ... JOIN claim compile to
 * nothing special on SQLite, so only two real sessions can prove that close, pick and delete serialise on
 * the draw row and that the claim is atomic.
 *
 * Two sessions in one process: the second connection (SecondConnection) plays the "other request" and
 * holds a lock or an uncommitted write; the default connection runs into it and gives up after one second
 * (error 1205). Interleavings that need a commit in the middle of a request are injected through a query
 * listener at a fixed point of the request.
 */
#[Group('mysql')]
class TokenRaceMysqlTest extends MysqlTestCase
{
    use BuildsTokenDraw;
    use RebuildsTournamentMysqlTables;

    private const LOCK_SQL = 'SELECT did, status, discarded FROM bs_draws WHERE did = ? FOR UPDATE';

    private const CLAIM_SQL = 'UPDATE bs_draw_tokens AS t INNER JOIN bs_draws AS d ON d.did = t.did '
        .'SET t.claimed_tpid = ?, t.claimed_uid = ?, t.claimed = ?, t.assigned_by = ? '
        .'WHERE t.did = ? AND t.dtid = ? AND t.claimed_tpid IS NULL '
        .'AND d.status = ?';

    private ?Connection $second = null;

    private function second(): Connection
    {
        return $this->second ??= SecondConnection::make();
    }

    /** The other request: a pick of $seed for $token that holds the draw row and has claimed, not committed. */
    private function secondHoldsPick(int $seed, DrawToken $token): void
    {
        $second = $this->second();
        $second->beginTransaction();
        $second->select(self::LOCK_SQL, [$this->draw->did]);
        $affected = $second->affectingStatement(self::CLAIM_SQL, [
            $this->tpid[$seed], $this->users[$seed]->uid, '2027-03-01 18:00:00', 'pick', $this->draw->did, $token->dtid, 'picking',
        ]);
        $this->assertSame(1, $affected, 'the held pick claimed its token');
    }

    private function pickOverHttp(int $seed, DrawToken $token): void
    {
        $this->app->forgetScopedInstances();

        $this->actingAs($this->users[$seed])
            ->post(route('draw.tokens.pick', ['did' => $this->draw->did]), ['dtid' => (string) $token->dtid])
            ->assertRedirect(route('draw.tokens', ['did' => $this->draw->did]));
    }

    /** @return list<array{type: string, text: string}> */
    private function flashes(): array
    {
        return session(ModuleFlash::KEY, []);
    }

    private function assertLockWaitTimeout(callable $statement, string $message): void
    {
        try {
            $statement();
            $this->fail('expected a lock wait timeout: '.$message);
        } catch (QueryException $e) {
            $this->assertSame(1205, $e->errorInfo[1], $message.': '.$e->getMessage());
        }
    }

    private function claimedCount(): int
    {
        return DrawToken::query()->where('did', $this->draw->did)->whereNotNull('claimed_tpid')->count();
    }

    /** @return list<array{type: string, text: string}> */
    private function closeAsAdmin(User $admin): array
    {
        $this->app->forgetScopedInstances();
        $this->actingAs($admin);

        return app(DrawCloser::class)->close($this->draw->fresh(), $admin);
    }

    // Executed SQL (a portable variant must fail here)

    #[Test]
    public function pick_runs_the_locking_read_and_the_join_claim_in_one_transaction(): void
    {
        $this->tokenDraw();
        $token = $this->tokenWithSlot('B2');
        $c = TokenPageContext::load($this->draw->fresh(), $this->users[3]);

        /** @var list<array{0: string, 1: int}> $log */
        $log = [];
        DB::listen(function (QueryExecuted $q) use (&$log): void {
            $log[] = [$q->sql, $q->connection->transactionLevel()];
        });

        app(TokenPicker::class)->pick($c, (int) $token->dtid, $this->users[3]);

        $sqls = array_column($log, 0);
        $lock = array_search(self::LOCK_SQL, $sqls, true);
        $claim = array_search(self::CLAIM_SQL, $sqls, true);

        $this->assertIsInt($lock, 'FOR UPDATE on the draw row: '.implode(' | ', $sqls));
        $this->assertIsInt($claim, 'UPDATE ... JOIN claim: '.implode(' | ', $sqls));
        $this->assertLessThan($claim, $lock, 'lock before claim');
        $this->assertSame(1, $log[$lock][1], 'the lock runs inside the pick transaction');
        $this->assertSame(1, $log[$claim][1], 'the claim runs in the same transaction');

        foreach ($sqls as $sql) {
            $this->assertStringNotContainsString('EXISTS (SELECT 1 FROM bs_draws', $sql, 'the SQLite claim must not run on MySQL');
        }

        // The group write comes after the commit.
        $groupWrite = null;
        foreach ($log as $i => [$sql, $level]) {
            if ($i > $claim && str_starts_with($sql, 'update `bs_tournament_players` set `grp`')) {
                $groupWrite = $level;
                break;
            }
        }
        $this->assertSame(0, $groupWrite, 'grp/grp_pos are written after the claim is committed');
        $this->assertSame($this->tpid[3], (int) $token->fresh()->claimed_tpid);
    }

    // The three plan races

    #[Test]
    public function pick_waits_for_a_close_holding_the_draw_row(): void
    {
        $this->tokenDraw();
        $token = $this->tokenWithSlot('A2');

        DB::beginTransaction();
        $this->assertNotNull(app(TokenClaimer::class)->lockDraw((int) $this->draw->did));

        $this->assertLockWaitTimeout(fn () => $this->second()->affectingStatement(self::CLAIM_SQL, [
            $this->tpid[3], $this->users[3]->uid, '2027-03-01 18:00:00', 'pick', $this->draw->did, $token->dtid, 'picking',
        ]), 'the claim must wait for the draw row');

        DB::rollBack();
        $this->assertSame(0, $this->claimedCount());

        // Released: the same claim goes through.
        $this->assertSame(1, $this->second()->affectingStatement(self::CLAIM_SQL, [
            $this->tpid[3], $this->users[3]->uid, '2027-03-01 18:00:00', 'pick', $this->draw->did, $token->dtid, 'picking',
        ]));
    }

    #[Test]
    public function claim_after_close_commits_affects_no_row(): void
    {
        $this->tokenDraw();
        $token = $this->tokenWithSlot('A2');

        // Close's conditional update, uncommitted: a claim waits for it ...
        DB::beginTransaction();
        $this->assertSame(1, Draw::query()->whereKey($this->draw->did)->where('status', 'picking')->update([
            'status' => 'closed', 'closed' => '2027-03-01 20:00:00',
        ]));

        $this->assertLockWaitTimeout(fn () => $this->second()->affectingStatement(self::CLAIM_SQL, [
            $this->tpid[3], $this->users[3]->uid, '2027-03-01 18:00:00', 'pick', $this->draw->did, $token->dtid, 'picking',
        ]), 'a claim during close waits');

        // ... and once it is committed, the claim affects nothing.
        DB::commit();

        $this->assertSame(0, $this->second()->affectingStatement(self::CLAIM_SQL, [
            $this->tpid[3], $this->users[3]->uid, '2027-03-01 18:00:00', 'pick', $this->draw->did, $token->dtid, 'picking',
        ]));
        $this->app->forgetScopedInstances();
        $this->assertFalse(app(TokenClaimer::class)->claimWhilePicking((int) $this->draw->did, (int) $token->dtid, $this->tpid[4], (int) $this->users[4]->uid));
        $this->assertSame(0, $this->claimedCount());
    }

    #[Test]
    public function two_claims_on_one_token_leave_exactly_one_owner(): void
    {
        $this->tokenDraw();
        $token = $this->tokenWithSlot('B3');
        $claimer = app(TokenClaimer::class);

        DB::beginTransaction();
        $this->assertTrue($claimer->claimWhilePicking((int) $this->draw->did, (int) $token->dtid, $this->tpid[3], (int) $this->users[3]->uid));

        $this->assertLockWaitTimeout(fn () => $this->second()->affectingStatement(self::CLAIM_SQL, [
            $this->tpid[4], $this->users[4]->uid, '2027-03-01 18:00:00', 'pick', $this->draw->did, $token->dtid, 'picking',
        ]), 'the second claim waits for the first one');

        DB::commit();

        $this->assertSame(0, $this->second()->affectingStatement(self::CLAIM_SQL, [
            $this->tpid[4], $this->users[4]->uid, '2027-03-01 18:00:00', 'pick', $this->draw->did, $token->dtid, 'picking',
        ]), 'after the commit the token is gone');

        $this->assertSame($this->tpid[3], (int) $token->fresh()->claimed_tpid);
        $this->assertSame(1, $this->claimedCount());
    }

    #[Test]
    public function one_player_cannot_claim_two_tokens_on_the_live_shape(): void
    {
        foreach (['bs_draw_eligible', 'bs_draw_tokens', 'bs_draws'] as $table) {
            DB::statement("DROP TABLE IF EXISTS {$table}");
        }
        $this->loadSqlFixture('tests/Fixtures/draw/live-schema.sql');
        $this->runModuleMigrations();
        $this->assertContains('did_claimed', array_column(DB::select('SHOW INDEX FROM bs_draw_tokens'), 'Key_name'));

        $this->tokenDraw();
        $claimer = app(TokenClaimer::class);
        $did = (int) $this->draw->did;

        $this->assertTrue($claimer->claimWhilePicking($did, (int) $this->tokenWithSlot('A2')->dtid, $this->tpid[3], (int) $this->users[3]->uid));

        try {
            $claimer->claimWhilePicking($did, (int) $this->tokenWithSlot('B2')->dtid, $this->tpid[3], (int) $this->users[3]->uid);
            $this->fail('did_claimed must refuse a second token');
        } catch (QueryException $e) {
            $this->assertSame(1062, $e->errorInfo[1]);
            $this->assertStringContainsString('did_claimed', $e->getMessage());
        }

        // In parallel: an uncommitted claim of seed 4 makes the second claim of seed 4 wait, then fail.
        $second = $this->second();
        $second->beginTransaction();
        $this->assertSame(1, $second->affectingStatement(self::CLAIM_SQL, [
            $this->tpid[4], $this->users[4]->uid, '2027-03-01 18:00:00', 'pick', $did, $this->tokenWithSlot('B2')->dtid, 'picking',
        ]));

        SecondConnection::waitAtMost(1);
        $this->assertLockWaitTimeout(
            fn () => $claimer->claimWhilePicking($did, (int) $this->tokenWithSlot('A3')->dtid, $this->tpid[4], (int) $this->users[4]->uid),
            'the duplicate key check waits for the open claim',
        );

        $second->commit();

        try {
            $claimer->claimWhilePicking($did, (int) $this->tokenWithSlot('A3')->dtid, $this->tpid[4], (int) $this->users[4]->uid);
            $this->fail('did_claimed must refuse the second token after the commit');
        } catch (QueryException $e) {
            $this->assertSame(1062, $e->errorInfo[1]);
        }

        $this->assertSame(2, $this->claimedCount());
    }

    // Through the real request

    #[Test]
    public function same_player_picking_twice_in_parallel_ends_in_the_already_flash(): void
    {
        $this->tokenDraw();
        $first = $this->tokenWithSlot('A2');
        $other = $this->tokenWithSlot('B3');
        $injected = false;

        // Request 1 of the same player commits its pick right after request 2 passed its "already picked?"
        // pre-check (the token lookup is the next query) and before request 2 takes the draw lock.
        DB::listen(function (QueryExecuted $q) use (&$injected, $first): void {
            if ($injected || $q->connectionName === SecondConnection::NAME
                || preg_match('/^select \* from `bs_draw_tokens` where `did` = \? and `bs_draw_tokens`\.`dtid` = \? limit 1$/', $q->sql) !== 1) {
                return;
            }
            $injected = true;
            $this->secondHoldsPick(3, $first);
            $this->second()->commit();
        });

        $this->pickOverHttp(3, $other);

        $this->assertTrue($injected, 'the parallel pick ran');
        $this->assertSame([['type' => 'error', 'text' => 'Du hast bereits Token '.$first->token.' gewählt. Eine Änderung ist nicht möglich.']], $this->flashes());
        $this->assertNull($other->fresh()->claimed_tpid, 'the second token stays free');
        $this->assertSame($this->tpid[3], (int) $first->fresh()->claimed_tpid);
    }

    #[Test]
    public function pick_held_up_by_a_lock_flashes_a_retry_instead_of_an_error_page(): void
    {
        $this->tokenDraw();
        $held = $this->tokenWithSlot('A2');
        $wanted = $this->tokenWithSlot('B2');

        $this->secondHoldsPick(3, $held);
        SecondConnection::waitAtMost(1);

        $this->pickOverHttp(3, $wanted);

        $this->assertSame([['type' => 'error', 'text' => 'Gerade wählen mehrere gleichzeitig. Bitte nochmals versuchen.']], $this->flashes());
        $this->assertNull($wanted->fresh()->claimed_tpid);

        $this->second()->commit();
        $this->assertSame($this->tpid[3], (int) $held->fresh()->claimed_tpid);

        // Once the other request is through, a retry shows the token the player already holds.
        $this->pickOverHttp(3, $wanted);
        $this->assertSame([['type' => 'error', 'text' => 'Du hast bereits Token '.$held->token.' gewählt. Eine Änderung ist nicht möglich.']], $this->flashes());
    }

    #[Test]
    public function concurrent_picks_by_two_partners_never_share_a_group(): void
    {
        $this->tokenDraw();
        $this->partners(3, 4);
        $a2 = $this->tokenWithSlot('A2');
        $a3 = $this->tokenWithSlot('A3');

        // Partner 4 is picking A2 right now: partner 3 cannot compute the block on the old state, he waits.
        $this->secondHoldsPick(4, $a2);
        SecondConnection::waitAtMost(1);

        $this->pickOverHttp(3, $a3);
        $this->assertSame('Gerade wählen mehrere gleichzeitig. Bitte nochmals versuchen.', $this->flashes()[0]['text'] ?? null);
        $this->assertNull($a3->fresh()->claimed_tpid);

        $this->second()->commit();

        // Under the lock the block is recomputed from the committed state: group A is closed for 3.
        $this->pickOverHttp(3, $a3);
        $this->assertSame([['type' => 'error', 'text' => 'Token '.$a3->token.' ist nicht wählbar. Bitte einen anderen nehmen.']], $this->flashes());
        $this->assertNull($a3->fresh()->claimed_tpid);

        $this->pickOverHttp(3, $this->tokenWithSlot('B2'));
        $this->assertSame('success', $this->flashes()[0]['type'] ?? null);
        $this->assertSame('B', TournamentPlayer::query()->findOrFail($this->tpid[3])->grp);
    }

    #[Test]
    public function close_waits_for_a_pick_holding_the_draw_row(): void
    {
        $this->app->instance(BeaconService::class, new FakeBeaconService(1700000000, 30));
        $admin = User::factory()->create(['status' => 'admin']);
        $this->tokenDraw();
        $held = $this->tokenWithSlot('B2');

        $this->secondHoldsPick(3, $held);
        SecondConnection::waitAtMost(1);

        $flashes = $this->closeAsAdmin($admin);

        $this->assertSame([['type' => 'error', 'text' => 'Wahlphase schließen fehlgeschlagen: interner Fehler, Details stehen im Fehlerprotokoll.']], $flashes);
        $this->assertSame('picking', $this->draw->fresh()->status, 'close rolled back');

        $this->second()->commit();

        $flashes = $this->closeAsAdmin($admin);

        $this->assertSame('Wahlphase geschlossen. 1 Token(s) selbst gewählt, 3 zugeteilt. Der Server-Seed ist jetzt im Protokoll - die Ziehung ist damit für jeden nachrechenbar.', $flashes[0]['text']);
        $this->assertSame('pick', $held->fresh()->assigned_by);
        $this->assertSame(4, $this->claimedCount());
        $this->assertSame('closed', $this->draw->fresh()->status);
    }

    #[Test]
    public function close_runs_the_conditional_status_update_before_the_fallback_claims(): void
    {
        $this->app->instance(BeaconService::class, new FakeBeaconService(1700000000, 30));
        $admin = User::factory()->create(['status' => 'admin']);
        $this->tokenDraw();
        // A guest without an account (uid NULL) is assigned by the fallback: ZF2 cast the uid to 0.
        TournamentPlayer::query()->whereKey($this->tpid[6])->update(['uid' => null]);
        $this->assertStringContainsString('STRICT_TRANS_TABLES', (string) DB::selectOne('SELECT @@SESSION.sql_mode AS m')->m);

        /** @var list<array{0: string, 1: int}> $writes */
        $writes = [];
        DB::listen(function (QueryExecuted $q) use (&$writes): void {
            if (str_starts_with($q->sql, 'update `bs_draw')) {
                $writes[] = [$q->sql, $q->connection->transactionLevel()];
            }
        });

        $this->closeAsAdmin($admin);

        $this->assertMatchesRegularExpression('/^update `bs_draws` set `status` = \?, `closed` = \? where `bs_draws`\.`did` = \? and `status` = \?$/', $writes[0][0] ?? '');
        $this->assertSame(1, $writes[0][1] ?? null, 'the status update runs inside the close transaction');
        $claims = array_values(array_filter($writes, fn (array $w): bool => str_starts_with($w[0], 'update `bs_draw_tokens`')));
        $this->assertCount(4, $claims);
        foreach ($claims as [$sql, $level]) {
            $this->assertMatchesRegularExpression('/where `dtid` = \? and `claimed_tpid` is null$/', $sql);
            $this->assertSame(1, $level, 'fallback claims in the close transaction');
        }

        $guest = DrawToken::query()->where('did', $this->draw->did)->where('claimed_tpid', $this->tpid[6])->firstOrFail();
        $this->assertSame(0, $guest->getAttributes()['claimed_uid'], 'claimed_uid stored as 0 under strict mode');
        $this->assertSame('fallback', $guest->assigned_by);
    }

    // Delete against the claim (a picking draw is executed and never deletable, so the race is on the lock)

    #[Test]
    public function delete_waits_for_a_claim_holding_the_draw_row_and_then_keeps_the_draw(): void
    {
        $this->tokenDraw();
        $draw = Draw::factory()->token(4, 'DG', ['A2', 'B2', 'A3', 'B3'])->discarded()
            ->create(['tid' => $this->tournament->tid, 'title' => 'Demo-Meisterschaft - verworfen']);
        $token = DrawToken::query()->where('did', $draw->did)->orderBy('position')->firstOrFail();

        $locks = [];
        DB::listen(function (QueryExecuted $q) use (&$locks): void {
            if (str_ends_with($q->sql, 'for update')) {
                $locks[] = $q->sql;
            }
        });

        $second = $this->second();
        $second->beginTransaction();
        $second->select(self::LOCK_SQL, [$draw->did]);
        $this->assertSame(1, $second->affectingStatement(
            'UPDATE bs_draw_tokens SET claimed_tpid = ?, claimed_uid = ?, claimed = ?, assigned_by = ? WHERE dtid = ? AND claimed_tpid IS NULL',
            [$this->tpid[3], $this->users[3]->uid, '2027-03-01 18:00:00', 'fallback', $token->dtid],
        ));
        SecondConnection::waitAtMost(1);

        $this->assertLockWaitTimeout(fn () => app(DrawLifecycleService::class)->delete($draw->fresh()), 'delete waits for the draw row');
        $this->assertNotNull(Draw::query()->find($draw->did), 'nothing deleted');
        $this->assertSame([], $locks, 'a failed statement is not logged');

        $second->commit();

        try {
            app(DrawLifecycleService::class)->delete($draw->fresh());
            $this->fail('a claimed token keeps the draw');
        } catch (ActionRefused $e) {
            $this->assertSame('draw::admin.delete.claimed', $e->key);
        }
        // The count ran under the draw row lock (same order as pick and close).
        $this->assertMatchesRegularExpression('/^select \* from `bs_draws` where `bs_draws`\.`did` = \? limit 1 for update$/', $locks[0] ?? '');
        $this->assertSame(4, DrawToken::query()->where('did', $draw->did)->count());
    }
}
