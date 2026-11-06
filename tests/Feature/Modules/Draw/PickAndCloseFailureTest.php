<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Draw;

use App\Models\User;
use App\Modules\Draw\Contracts\BeaconService;
use App\Modules\Draw\Models\DrawEligible;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\Draw\Services\DrawCloser;
use App\Modules\Draw\Services\TokenPicker;
use App\Modules\Draw\Support\TokenPageContext;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\Tournament\Services\Draws\DrawApplier;
use App\Modules\TournamentBase\Support\ActionRefused;
use App\Modules\TournamentBase\Support\ModuleFlash;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Feature\Modules\Draw\Concerns\BuildsTokenDraw;
use Tests\Support\FakeBeaconService;
use Tests\TestCase;

/**
 * Failure handling of pick and close (review 26-29 M-1, M-2, M-5, M-7). Database errors are injected
 * through a query listener; the real lock waits and deadlocks run in TokenRaceMysqlTest.
 */
class PickAndCloseFailureTest extends TestCase
{
    use BuildsTokenDraw;
    use LazilyRefreshDatabase;

    private const BUSY = 'Gerade wählen mehrere gleichzeitig. Bitte nochmals versuchen.';

    private function pick(int $seed, DrawToken $token): TestResponse
    {
        $this->app->forgetScopedInstances();

        return $this->actingAs($this->users[$seed])
            ->post(route('draw.tokens.pick', ['did' => $this->draw->did]), ['dtid' => (string) $token->dtid]);
    }

    private function failOn(string $sqlStart, \Throwable $error): void
    {
        $thrown = false;
        DB::listen(function (QueryExecuted $q) use (&$thrown, $sqlStart, $error): void {
            if (! $thrown && str_starts_with($q->sql, $sqlStart)) {
                $thrown = true;

                throw $error;
            }
        });
    }

    private static function queryError(string $message, string $sqlState, int $code): QueryException
    {
        $pdo = new PDOException("SQLSTATE[{$sqlState}]: {$message}");
        $pdo->errorInfo = [$sqlState, $code, $message];

        return new QueryException('sqlite', 'SELECT 1', [], $pdo);
    }

    /** @return array<string, array{string, string, int}> */
    public static function concurrencyErrors(): array
    {
        return [
            'lock wait timeout' => ['General error: 1205 Lock wait timeout exceeded; try restarting transaction', 'HY000', 1205],
            'deadlock' => ['Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction', '40001', 1213],
        ];
    }

    #[Test]
    #[DataProvider('concurrencyErrors')]
    public function a_concurrency_error_during_the_pick_flashes_a_retry(string $message, string $state, int $code): void
    {
        $this->tokenDraw();
        $token = $this->tokenWithSlot('A2');
        Log::spy();
        $this->failOn('SELECT did, status, discarded FROM bs_draws', self::queryError($message, $state, $code));

        $this->pick(3, $token)->assertRedirect(route('draw.tokens', ['did' => $this->draw->did]));

        $this->assertSame([['type' => 'error', 'text' => self::BUSY]], session(ModuleFlash::KEY));
        $this->assertNull($token->fresh()->claimed_tpid);
        Log::shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function another_database_error_during_the_pick_is_not_swallowed(): void
    {
        $this->tokenDraw();
        $this->failOn('SELECT did, status, discarded FROM bs_draws', self::queryError('General error: 1 something else broke', 'HY000', 1));

        $this->pick(3, $this->tokenWithSlot('A2'))->assertStatus(500);
    }

    #[Test]
    public function a_unique_violation_without_a_held_token_flashes_a_retry(): void
    {
        $this->tokenDraw();
        $token = $this->tokenWithSlot('A2');
        $pdo = new PDOException('SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed');
        $pdo->errorInfo = ['23000', 19, 'UNIQUE constraint failed'];
        $this->failOn('UPDATE bs_draw_tokens', new UniqueConstraintViolationException('sqlite', 'UPDATE bs_draw_tokens', [], $pdo));

        $this->pick(3, $token)->assertRedirect();

        $this->assertSame([['type' => 'error', 'text' => self::BUSY]], session(ModuleFlash::KEY));
    }

    #[Test]
    public function a_group_write_failure_shows_no_raw_message(): void
    {
        $this->tokenDraw();
        $token = $this->tokenWithSlot('A2');
        Log::spy();
        $this->failOn('insert into "bs_tournament_matches"', self::queryError('General error: 1 near "secret_table": syntax error', 'HY000', 1));

        $this->pick(3, $token)->assertRedirect();

        $flashes = session(ModuleFlash::KEY);
        $this->assertSame('Token ist belegt, die Gruppenzuteilung hat aber nicht geklappt: interner Fehler, Details stehen im Fehlerprotokoll', $flashes[0]['text']);
        $this->assertSame('success', $flashes[1]['type']);
        $this->assertSame($this->tpid[3], (int) $token->fresh()->claimed_tpid);
        Log::shouldHaveReceived('error')->once();
    }

    #[Test]
    public function the_partner_group_is_read_fresh_under_the_lock(): void
    {
        $this->tokenDraw();
        $this->partners(3, 4);
        $c = TokenPageContext::load($this->draw->fresh(), $this->users[3]);
        $this->assertSame([], $c->blockedTokens);

        // The partner's group is written by another request after the page was built (the manager of this
        // request still holds the old player rows).
        TournamentPlayer::query()->whereKey($this->tpid[4])->update(['grp' => 'A', 'grp_pos' => 2]);
        $a3 = $this->tokenWithSlot('A3');

        try {
            app(TokenPicker::class)->pick($c, (int) $a3->dtid, $this->users[3]);
            $this->fail('the partner block must use the current group');
        } catch (ActionRefused $e) {
            $this->assertSame('draw::tokens.pick.blocked', $e->key);
        }
        $this->assertNull($a3->fresh()->claimed_tpid);
    }

    #[Test]
    public function pick_buttons_name_their_token(): void
    {
        $this->tokenDraw();

        $response = $this->actingAs($this->users[3])->get(route('draw.tokens', ['did' => $this->draw->did]))->assertOk();

        foreach (DrawToken::query()->where('did', $this->draw->did)->get() as $token) {
            $response->assertSee('aria-label="Token '.$token->token.' wählen?"', false);
        }
    }

    // Close

    /** @return list<array{type: string, text: string}> */
    private function close(User $admin): array
    {
        $this->app->forgetScopedInstances();
        $this->actingAs($admin);

        return app(DrawCloser::class)->close($this->draw->fresh(), $admin);
    }

    #[Test]
    public function a_failed_close_shows_a_generic_text_and_logs_the_detail(): void
    {
        $this->app->instance(BeaconService::class, new FakeBeaconService(1700000000, 30));
        $admin = User::factory()->create(['status' => 'admin']);
        $this->tokenDraw();
        DrawEligible::query()->create(['did' => $this->draw->did, 'tpid' => $this->tpid[1]]); // more non-pickers than tokens
        Log::spy();

        $this->assertSame(
            [['type' => 'error', 'text' => 'Wahlphase schließen fehlgeschlagen: interner Fehler, Details stehen im Fehlerprotokoll.']],
            $this->close($admin),
        );
        $this->assertSame('picking', $this->draw->fresh()->status);
        Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => $context['exception'] instanceof RuntimeException
            && str_contains($context['exception']->getMessage(), 'more players without a token'))->once();
    }

    #[Test]
    public function a_generation_failure_after_close_keeps_close_and_apply(): void
    {
        $this->app->instance(BeaconService::class, new FakeBeaconService(1700000000, 30));
        $admin = User::factory()->create(['status' => 'admin']);
        $this->tokenDraw();
        $this->failOn('insert into "bs_tournament_matches"', new RuntimeException('forced generation failure'));

        $flashes = $this->close($admin);

        $this->assertSame(['success', 'success', 'error'], array_column($flashes, 'type'));
        $this->assertSame('4 Zuordnungen atomar angewendet.', $flashes[1]['text']);
        $this->assertSame(
            'Wahlphase ist geschlossen, das Zuteilen der Nichtwähler ist aber fehlgeschlagen: interner Fehler, Details stehen im Fehlerprotokoll - in der Turnierleitung "anwenden" aufrufen.',
            $flashes[2]['text'],
        );
        $draw = $this->draw->fresh();
        $this->assertSame('closed', $draw->status);
        $this->assertNotNull($draw->applied_at, 'apply is committed before the generation runs');
        $this->assertSame(0, TournamentMatch::query()->where('tid', $this->tournament->tid)->count(), 'the generation rolled back');
        $this->assertNotNull(TournamentPlayer::query()->findOrFail($this->tpid[5])->grp);
    }

    #[Test]
    public function apply_writes_player_rows_only_within_the_tournament(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $this->tokenDraw();
        foreach (['A2' => 3, 'B2' => 4, 'A3' => 5, 'B3' => 6] as $slot => $seed) {
            $this->claimDirectly($this->tokenWithSlot($slot), $seed);
        }
        $this->draw->newQuery()->whereKey($this->draw->did)->update(['status' => 'closed', 'closed' => '2027-03-01 20:00:00']);

        $updates = [];
        DB::listen(function (QueryExecuted $q) use (&$updates): void {
            if (str_starts_with($q->sql, 'update "bs_tournament_players"')) {
                $updates[] = $q->sql;
            }
        });

        $this->assertSame(4, app(DrawApplier::class)->apply($this->draw->fresh(), (int) $this->tournament->tid, $admin));

        $this->assertCount(4, $updates);
        foreach ($updates as $sql) {
            $this->assertSame('update "bs_tournament_players" set "grp" = ?, "grp_pos" = ? where "tid" = ? and "tpid" = ?', $sql);
        }
    }
}
