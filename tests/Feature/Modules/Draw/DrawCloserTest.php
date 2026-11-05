<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Draw;

use App\Models\User;
use App\Modules\Draw\Contracts\BeaconService;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawEligible;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\Draw\Services\DrawCloser;
use App\Modules\Draw\Services\DrawCommitService;
use App\Modules\Draw\Services\DrawExecutor;
use App\Modules\Draw\Services\DrawService;
use App\Modules\Draw\Services\DrawVerifier;
use App\Modules\Draw\Services\TokenPicker;
use App\Modules\Draw\Services\TokenService;
use App\Modules\Draw\Support\TokenPageContext;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\TournamentBase\Support\ActionRefused;
use App\Modules\TournamentBase\Support\ModuleFlash;
use App\Modules\TournamentBase\Support\TestModeSession;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Date;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Draw\Concerns\BuildsTokenDraw;
use Tests\Support\FakeBeaconService;
use Tests\TestCase;

class DrawCloserTest extends TestCase
{
    use BuildsTokenDraw;
    use LazilyRefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['status' => 'admin']);
        $this->app->instance(BeaconService::class, new FakeBeaconService(1700000000, 30));
    }

    /** @return list<array{type: string, text: string}> */
    private function close(?Draw $draw = null): array
    {
        $this->actingAs($this->admin);

        return app(DrawCloser::class)->close(($draw ?? $this->draw)->fresh(), $this->admin);
    }

    private function pickAs(int $seed, string $slot): void
    {
        $c = TokenPageContext::load($this->draw->fresh(), $this->users[$seed]);
        app(TokenPicker::class)->pick($c, (int) $this->tokenWithSlot($slot)->dtid, $this->users[$seed]);
        $this->app->forgetScopedInstances();
    }

    /** The fallback computed independently with TokenService on the inputs close sees. */
    private function expectedFallback(): array
    {
        $players = TournamentPlayer::query()->where('tid', $this->tournament->tid)->get()->keyBy('tpid');
        $tokens = DrawToken::query()->where('did', $this->draw->did)->get();
        $claimed = $tokens->whereNotNull('claimed_tpid')->pluck('claimed_tpid')->map(fn ($t) => (int) $t)->all();
        $free = $tokens->whereNull('claimed_tpid')->map(fn (DrawToken $t) => [
            'dtid' => (int) $t->dtid, 'position' => (int) $t->position, 'group' => preg_replace('/[0-9]+$/', '', (string) $t->slot),
        ])->values()->all();
        $nonPickers = DrawEligible::query()->where('did', $this->draw->did)->get()
            ->reject(fn ($e) => in_array((int) $e->tpid, $claimed, true))
            ->map(fn ($e) => ['tpid' => (int) $e->tpid, 'uid' => $players[$e->tpid]->uid])->values()->all();
        $partners = [];
        $fixed = [];
        foreach ($players as $p) {
            if ($p->partner_tpid) {
                $partners[(int) $p->tpid] = (int) $p->partner_tpid;
            }
            if ($p->grp) {
                $fixed[(int) $p->tpid] = $p->grp;
            }
        }
        foreach ($tokens->whereNotNull('claimed_tpid') as $t) {
            $fixed[(int) $t->claimed_tpid] = preg_replace('/[0-9]+$/', '', (string) $t->slot);
        }

        return (new TokenService)->fallbackAssignment($free, $nonPickers, $this->draw->fresh()->final_seed, new DrawService, $partners, $fixed);
    }

    #[Test]
    public function close_assigns_non_pickers_with_the_fallback(): void
    {
        $this->tokenDraw();
        $this->pickAs(3, 'B2');
        $expected = $this->expectedFallback();
        $this->assertCount(3, $expected);

        $flashes = $this->close();

        $this->assertSame('Wahlphase geschlossen. 1 Token(s) selbst gewählt, 3 zugeteilt. Der Server-Seed ist jetzt im Protokoll - die Ziehung ist damit für jeden nachrechenbar.', $flashes[0]['text']);
        foreach ($expected as $entry) {
            $token = DrawToken::query()->findOrFail($entry['dtid']);
            $this->assertSame([$entry['tpid'], 'fallback'], [(int) $token->claimed_tpid, $token->assigned_by]);
        }
        $this->assertSame('pick', $this->tokenWithSlot('B2')->assigned_by);

        $draw = $this->draw->fresh();
        $this->assertSame('closed', $draw->status);
        $this->assertNotNull($draw->closed);
        $this->assertTrue($draw->isRevealed());
        $this->assertTrue(app(DrawVerifier::class)->verify($draw)->ok());
    }

    #[Test]
    public function close_applies_and_generates(): void
    {
        $this->tokenDraw();
        $this->pickAs(3, 'A2');

        $flashes = $this->close();

        $this->assertSame(['success', 'success', 'success'], array_column($flashes, 'type'));
        $this->assertSame('4 Zuordnungen atomar angewendet.', $flashes[1]['text']);
        $this->assertStringStartsWith('Alle Spieler sind zugeteilt, ', $flashes[2]['text']);
        for ($seed = 3; $seed <= 6; $seed++) {
            $this->assertNotNull(TournamentPlayer::query()->findOrFail($this->tpid[$seed])->grp, "seed $seed");
        }
        $this->assertNotNull($this->draw->fresh()->applied_at);
        $this->assertSame((int) $this->admin->uid, (int) $this->draw->fresh()->applied_uid);
        $this->assertSame(3, TournamentMatch::query()->where('tid', $this->tournament->tid)->where('grp', 'A')->count());
        $this->assertSame(3, TournamentMatch::query()->where('tid', $this->tournament->tid)->where('grp', 'B')->count());
        // Every player sits on the slot of his token.
        foreach (DrawToken::query()->where('did', $this->draw->did)->get() as $token) {
            $player = TournamentPlayer::query()->findOrFail($token->claimed_tpid);
            $this->assertSame($token->slot, $player->grp.$player->grp_pos);
        }
    }

    #[Test]
    public function partner_rule_is_respected_by_fallback(): void
    {
        $this->tokenDraw();
        $this->partners(3, 2); // seed 2 heads group B
        $this->partners(4, 1); // seed 1 heads group A
        TournamentPlayer::query()->whereKey($this->tpid[2])->update(['partner_tpid' => $this->tpid[3]]);

        $this->close();

        $this->assertSame('A', TournamentPlayer::query()->findOrFail($this->tpid[3])->grp);
        $this->assertSame('B', TournamentPlayer::query()->findOrFail($this->tpid[4])->grp);
    }

    #[Test]
    public function parallel_close_is_refused(): void
    {
        $this->tokenDraw();
        $stale = $this->draw->fresh();
        Draw::query()->whereKey($this->draw->did)->update(['status' => 'closed']);

        $this->actingAs($this->admin);
        $flashes = app(DrawCloser::class)->close($stale, $this->admin);

        $this->assertSame([['type' => 'error', 'text' => 'Die Wahlphase wurde parallel bereits geschlossen.']], $flashes);
        $this->assertNull(DrawToken::query()->where('did', $this->draw->did)->whereNotNull('claimed_tpid')->first());

        try {
            $this->close(); // fresh model: not picking any more
            $this->fail('no refusal');
        } catch (ActionRefused $e) {
            $this->assertSame('Diese Auslosung ist nicht in der Wahlphase.', __($e->key));
        }
    }

    #[Test]
    public function too_many_non_pickers_roll_the_close_back(): void
    {
        $this->tokenDraw();
        DrawEligible::query()->create(['did' => $this->draw->did, 'tpid' => $this->tpid[1]]);

        $flashes = $this->close();

        $this->assertSame('error', $flashes[0]['type']);
        $this->assertCount(1, $flashes);
        $this->assertSame('picking', $this->draw->fresh()->status);
        $this->assertNull($this->draw->fresh()->closed);
        $this->assertNull(DrawToken::query()->where('did', $this->draw->did)->whereNotNull('claimed_tpid')->first());
    }

    #[Test]
    public function apply_failure_keeps_close_and_flashes_the_hint(): void
    {
        $this->tokenDraw();
        $other = Tournament::factory()->create();
        $this->actingAs($this->admin);
        $this->app->make(TestModeSession::class)->selectTid($this->admin, (int) $other->tid);

        $flashes = $this->close();

        $this->assertSame('closed', $this->draw->fresh()->status);
        $this->assertNull($this->draw->fresh()->applied_at);
        $this->assertSame(4, DrawToken::query()->where('did', $this->draw->did)->whereNotNull('claimed_tpid')->count());
        $this->assertSame(['success', 'error'], array_column($flashes, 'type'));
        $this->assertSame(
            'Wahlphase ist geschlossen, das Zuteilen der Nichtwähler ist aber fehlgeschlagen: Auslosung fehlt, ist noch nicht abgeschlossen, wurde nicht gezogen, ist verworfen oder gehört zu einem anderen Turnier. - in der Turnierleitung "anwenden" aufrufen.',
            $flashes[1]['text'],
        );
        $this->assertNull(TournamentPlayer::query()->findOrFail($this->tpid[5])->grp);
    }

    #[Test]
    public function close_without_tid_does_not_apply(): void
    {
        $this->tokenDraw([], withTid: false);

        $flashes = $this->close();

        $this->assertCount(1, $flashes);
        $this->assertSame('closed', $this->draw->fresh()->status);
        $this->assertNull($this->draw->fresh()->applied_at);
        $this->assertSame(4, DrawToken::query()->where('did', $this->draw->did)->where('assigned_by', 'fallback')->count());
        $this->assertNull(TournamentPlayer::query()->findOrFail($this->tpid[3])->grp);
    }

    #[Test]
    public function route_requires_admin_draw(): void
    {
        $this->tokenDraw();
        $url = route('draw.close', ['did' => $this->draw->did]);

        $this->post($url)->assertRedirect();
        $this->assertGuest();
        $this->actingAs($this->users[3])->post($url)->assertForbidden();
        $this->actingAs($this->users[3])->post(route('draw.close', ['did' => 999999]))->assertForbidden();
        auth()->logout();
        $this->assertSame('picking', $this->draw->fresh()->status);

        $this->actingAs($this->admin)->get('/draw/close/'.$this->draw->did)->assertRedirect(route('draw.index'));
        $this->actingAs($this->admin)->post(route('draw.close', ['did' => 999999]))
            ->assertRedirect(route('draw.index'))->assertSessionHas('error', 'Diese Auslosung ist nicht in der Wahlphase.');

        // Real login session; the flashes land in the module list of the overview.
        auth()->logout();
        $this->admin->forceFill(['pw' => bcrypt('geheim-123')])->save();
        $this->post('/login', ['email' => $this->admin->email, 'password' => 'geheim-123']);
        $this->post($url)->assertRedirect(route('draw.index'));
        $this->assertSame('closed', $this->draw->fresh()->status);
        $this->assertCount(3, session(ModuleFlash::KEY, []));
        $this->get(route('draw.index'))->assertOk()->assertSee('Alle Spieler sind zugeteilt');

        // A closed draw is refused with the old text.
        $this->post($url)->assertRedirect(route('draw.index'))->assertSessionHas('error', 'Diese Auslosung ist nicht in der Wahlphase.');
    }

    #[Test]
    public function picking_rows_offer_the_close_button(): void
    {
        $this->tokenDraw();

        $this->app->instance(BeaconService::class, new FakeBeaconService(1700000000, 30));
        $html = (string) $this->actingAs($this->admin)->get(route('draw.index'))->assertOk()->getContent();

        preg_match_all('/pending = JSON\.parse\(&#039;(.*?)&#039;\)/', $html, $m);
        $confirms = array_map(static fn (string $literal): array => json_decode((string) json_decode('"'.$literal.'"'), true), $m[1]);
        $close = collect($confirms)->firstWhere('url', route('draw.close', ['did' => $this->draw->did]));
        $this->assertNotNull($close);
        $this->assertSame('Wahlphase schließen? Freie Tokens werden den Nichtwählern zugeteilt.', $close['text']);
        $this->assertSame('Wahlphase schließen', $close['submit']);
    }

    #[Test]
    public function every_draw_created_by_the_new_flows_verifies(): void
    {
        $beacon = new FakeBeaconService(1700000000, 30);
        $this->app->instance(BeaconService::class, $beacon);
        Date::setTestNow(Date::createFromTimestamp(1700000000 + 999 * 30));

        // Manual direct draw: commit (Task 23) and execute (Task 25).
        $direct = app(DrawCommitService::class)->commitManual([
            'title' => 'Freie Ziehung', 'entries' => "101\n102\n103", 'slots' => "A2\nB2\nC2", 'rule' => 'Reihenfolge auf die Slots', 'round' => '1010', 'tid' => '',
        ], $this->admin);

        // Token draw of a championship: committed, executed, one pick, close with fallback (Tasks 25-29).
        $this->tokenDraw();
        $token = Draw::factory()->token(4, 'AC', ['A2', 'B2', 'A3', 'B3'])
            ->create(['tid' => $this->tournament->tid, 'beacon_source' => 'drand:round:1010']);
        DrawEligible::query()->where('did', $this->draw->did)->update(['did' => $token->did]);
        Draw::query()->whereKey($this->draw->did)->delete();
        DrawToken::query()->where('did', $this->draw->did)->delete();
        $this->draw = $token;

        $beacon->publish(1010, str_repeat('7c', 32));
        Date::setTestNow(Date::createFromTimestamp($beacon->roundTime(1010) + 5));
        app(DrawExecutor::class)->execute($direct->fresh());
        app(DrawExecutor::class)->execute($token->fresh());
        $this->assertSame('picking', $token->fresh()->status);

        $this->pickAs(4, 'B3');
        $flashes = $this->close($token);
        $this->assertSame('success', $flashes[array_key_last($flashes)]['type']);

        $this->artisan('draw:verify', ['--all' => true])->assertExitCode(0);
        foreach (Draw::query()->get() as $draw) {
            $this->assertTrue(app(DrawVerifier::class)->verify($draw)->ok(), (string) $draw->title);
        }
    }
}
