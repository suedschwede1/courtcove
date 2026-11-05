<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Draw;

use App\Models\User;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\Draw\Services\TokenPicker;
use App\Modules\Draw\Support\TokenPageContext;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\TournamentBase\Support\ActionRefused;
use App\Modules\TournamentBase\Support\ModuleFlash;
use App\Modules\TournamentBase\Support\TestModeSession;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Draw\Concerns\BuildsTokenDraw;
use Tests\TestCase;

class TokenPickTest extends TestCase
{
    use BuildsTokenDraw;
    use LazilyRefreshDatabase;

    private function pick(User $user, DrawToken|string $token): void
    {
        $dtid = $token instanceof DrawToken ? (string) $token->dtid : $token;

        $this->actingAs($user)
            ->post(route('draw.tokens.pick', ['did' => $this->draw->did]), ['dtid' => $dtid])
            ->assertRedirect(route('draw.tokens', ['did' => $this->draw->did]));
    }

    private function assertFlash(string $type, string $text): void
    {
        $this->assertContains(['type' => $type, 'text' => $text], session(ModuleFlash::KEY, []));
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'tokens' => DrawToken::query()->where('did', $this->draw->did)->orderBy('dtid')->get()->map->getAttributes()->all(),
            'players' => TournamentPlayer::query()->where('tid', $this->tournament->tid)->orderBy('tpid')->get()->map->getAttributes()->all(),
            'matches' => TournamentMatch::query()->where('tid', $this->tournament->tid)->count(),
        ];
    }

    #[Test]
    public function pick_claims_writes_group_and_generates(): void
    {
        $this->tokenDraw();
        $token = $this->tokenWithSlot('B2');

        $this->pick($this->users[3], $token);

        $fresh = $token->fresh();
        $this->assertSame([$this->tpid[3], (int) $this->users[3]->uid, 'pick'], [(int) $fresh->claimed_tpid, (int) $fresh->claimed_uid, $fresh->assigned_by]);
        $player = TournamentPlayer::query()->findOrFail($this->tpid[3]);
        $this->assertSame(['B', 2], [$player->grp, (int) $player->grp_pos]);
        $this->assertFlash('success', 'Token '.$token->token.' ist deiner – du bist in Gruppe B. Deine Gegner stehen unter "Meine Spiele".');

        // The head of B and the new B2 are both known: their match exists.
        $this->assertTrue(TournamentMatch::query()->where('tid', $this->tournament->tid)->where('grp', 'B')
            ->where(fn ($q) => $q->where('tpid_a', $this->tpid[3])->orWhere('tpid_b', $this->tpid[3]))->exists());

        // Completing group B creates every B match.
        $this->pick($this->users[4], $this->tokenWithSlot('B3'));
        $this->assertSame(3, TournamentMatch::query()->where('tid', $this->tournament->tid)->where('grp', 'B')->count());
    }

    #[Test]
    public function the_claim_records_the_acting_user_in_a_test_simulation(): void
    {
        $this->tokenDraw(['is_test' => 1]);
        $admin = User::factory()->create(['status' => 'admin']);
        $this->actingAs($admin);
        $this->app->make(TestModeSession::class)->simulate($admin, 'player', $this->tpid[4]);
        $token = $this->tokenWithSlot('A2');

        $this->pick($admin, $token);

        $fresh = $token->fresh();
        $this->assertSame([$this->tpid[4], (int) $admin->uid], [(int) $fresh->claimed_tpid, (int) $fresh->claimed_uid]);
    }

    #[Test]
    public function pick_outside_picking_fails(): void
    {
        $this->tokenDraw();
        Draw::query()->whereKey($this->draw->did)->update(['status' => 'closed']);
        $before = $this->snapshot();

        $this->pick($this->users[3], $this->tokenWithSlot('A2'));

        $this->assertFlash('error', 'Die Wahlphase dieser Auslosung ist nicht offen.');
        $this->assertSame($before, $this->snapshot());
    }

    #[Test]
    public function draw_closed_after_the_page_was_built_is_refused_under_the_lock(): void
    {
        $this->tokenDraw();
        $c = TokenPageContext::load($this->draw, $this->users[3]);
        Draw::query()->whereKey($this->draw->did)->update(['status' => 'closed']);
        $before = $this->snapshot();

        try {
            app(TokenPicker::class)->pick($c, (int) $this->tokenWithSlot('A2')->dtid, $this->users[3]);
            $this->fail('no refusal');
        } catch (ActionRefused $e) {
            $this->assertSame('Diese Auslosung ist nicht mehr in der Wahlphase.', __($e->key, $e->replace));
        }

        $this->assertSame($before, $this->snapshot());
    }

    #[Test]
    public function draw_discarded_after_the_page_was_built_is_refused_under_the_lock(): void
    {
        $this->tokenDraw();
        $c = TokenPageContext::load($this->draw, $this->users[3]);
        Draw::query()->whereKey($this->draw->did)->update(['discarded' => '2027-03-01 10:00:00', 'discarded_reason' => 'Testgrund']);
        $before = $this->snapshot();

        try {
            app(TokenPicker::class)->pick($c, (int) $this->tokenWithSlot('A2')->dtid, $this->users[3]);
            $this->fail('no refusal');
        } catch (ActionRefused $e) {
            $this->assertSame('Diese Auslosung ist nicht mehr in der Wahlphase.', __($e->key, $e->replace));
        }

        // No claim reached the token: the discarded draw cannot get a claim between delete's check and delete.
        $this->assertSame($before, $this->snapshot());
    }

    #[Test]
    public function second_pick_is_refused(): void
    {
        $this->tokenDraw();
        $first = $this->tokenWithSlot('A2');
        $this->pick($this->users[3], $first);
        $before = $this->snapshot();

        $this->pick($this->users[3], $this->tokenWithSlot('B2'));

        $this->assertFlash('error', 'Du hast bereits Token '.$first->token.' gewählt. Eine Änderung ist nicht möglich.');
        $this->assertSame($before, $this->snapshot());
    }

    #[Test]
    public function token_taken_by_somebody_else_is_refused(): void
    {
        $this->tokenDraw();
        $token = $this->tokenWithSlot('A2');
        $this->pick($this->users[4], $token);

        $this->pick($this->users[3], $token);

        $this->assertFlash('error', 'Token '.$token->token.' war schneller weg. Bitte einen anderen wählen.');
        $this->assertSame($this->tpid[4], (int) $token->fresh()->claimed_tpid);
        $this->assertNull(TournamentPlayer::query()->findOrFail($this->tpid[3])->grp);
    }

    #[Test]
    public function blocked_token_is_refused_under_lock(): void
    {
        $this->tokenDraw();
        $this->partners(3, 4);
        $c = TokenPageContext::load($this->draw, $this->users[3]);
        $this->assertSame([], $c->blockedTokens);

        // The partner picks into group B between page load and pick.
        $this->claimDirectly($this->tokenWithSlot('B3'), 4);
        $b2 = $this->tokenWithSlot('B2');

        try {
            app(TokenPicker::class)->pick($c, (int) $b2->dtid, $this->users[3]);
            $this->fail('no refusal');
        } catch (ActionRefused $e) {
            $this->assertSame('Token '.$b2->token.' ist nicht wählbar. Bitte einen anderen nehmen.', __($e->key, $e->replace));
        }

        $this->assertNull($b2->fresh()->claimed_tpid);
    }

    #[Test]
    public function generation_failure_keeps_the_claim(): void
    {
        $this->tokenDraw();
        // A format that cannot be read makes the generation fail after the claim.
        Tournament::query()->whereKey($this->tournament->tid)->update(['format' => '']);
        $token = $this->tokenWithSlot('A2');

        $this->pick($this->users[3], $token);

        $this->assertSame($this->tpid[3], (int) $token->fresh()->claimed_tpid);
        $flashes = session(ModuleFlash::KEY, []);
        $this->assertSame('error', $flashes[0]['type']);
        $this->assertStringStartsWith('Token ist belegt, die Gruppenzuteilung hat aber nicht geklappt: ', $flashes[0]['text']);
        $this->assertFlash('success', 'Token '.$token->token.' ist deiner – du bist in Gruppe A. Deine Gegner stehen unter "Meine Spiele".');
    }

    #[Test]
    public function token_without_slot_gives_the_no_group_text(): void
    {
        $this->tokenDraw();
        $token = $this->tokenWithSlot('A2');
        DrawToken::query()->whereKey($token->dtid)->update(['slot' => null]);

        $this->pick($this->users[3], $token);

        $this->assertFlash('success', 'Token '.$token->token.' ist deiner. Die Gruppe erscheint, sobald die Ziehung ausgeführt ist.');
        $this->assertNull(TournamentPlayer::query()->findOrFail($this->tpid[3])->grp);
    }

    #[Test]
    public function discarded_draw_refuses(): void
    {
        $this->tokenDraw();
        Draw::query()->whereKey($this->draw->did)->update(['discarded' => '2027-03-01 10:00:00', 'discarded_reason' => 'Testgrund']);
        $before = $this->snapshot();

        $this->pick($this->users[3], $this->tokenWithSlot('A2'));

        $this->assertFlash('error', 'Diese Auslosung wurde verworfen.');
        $this->assertSame($before, $this->snapshot());
    }

    #[Test]
    public function refusals_follow_the_zf2_order(): void
    {
        $this->tokenDraw();
        $stranger = User::factory()->create(['status' => 'enabled']);

        $this->pick($stranger, $this->tokenWithSlot('A2'));
        $this->assertFlash('error', 'Du bist nicht als Teilnehmer dieses Turniers gemeldet.');

        $this->pick($this->users[1], $this->tokenWithSlot('A2'));
        $this->assertFlash('error', 'Du bist bei dieser Auslosung nicht im Lostopf. Gesetzte Gruppenköpfe und Spieler des anderen Bewerbs wählen nicht mit.');

        $admin = User::factory()->create(['status' => 'admin']);
        TournamentPlayer::query()->whereKey($this->tpid[6])->update(['uid' => $admin->uid]);
        $this->actingAs($admin);
        $this->app->make(TestModeSession::class)->simulate($admin, 'player', $this->tpid[4]);
        $this->pick($admin, $this->tokenWithSlot('A2'));
        $this->assertFlash('error', 'Im Testmodus ist die Tokenwahl gesperrt. Beende ihn zuerst.');

        $this->assertNull(DrawToken::query()->where('did', $this->draw->did)->whereNotNull('claimed_tpid')->first());
    }

    #[Test]
    public function unknown_foreign_or_malformed_tokens_are_refused(): void
    {
        $this->tokenDraw();
        $other = Draw::factory()->token(4, 'AC', ['A2', 'B2', 'A3', 'B3'])->executed()->create(['tid' => $this->tournament->tid]);
        $foreign = DrawToken::query()->where('did', $other->did)->orderBy('position')->firstOrFail();

        foreach ([(string) $foreign->dtid, '999999', 'x', '', '99999999999999999999'] as $dtid) {
            $this->pick($this->users[3], $dtid);
            $this->assertFlash('error', 'Diesen Token gibt es nicht.');
        }

        $this->actingAs($this->users[3])
            ->post(route('draw.tokens.pick', ['did' => $this->draw->did]), ['dtid' => ['1']])
            ->assertRedirect(route('draw.tokens', ['did' => $this->draw->did]));
        $this->assertFlash('error', 'Diesen Token gibt es nicht.');

        $this->assertNull($foreign->fresh()->claimed_tpid);
        $this->assertNull(DrawToken::query()->where('did', $this->draw->did)->whereNotNull('claimed_tpid')->first());
    }

    #[Test]
    public function get_on_pick_redirects_to_the_token_page(): void
    {
        $this->tokenDraw();

        $this->actingAs($this->users[3])->get('/draw/tokens/'.$this->draw->did.'/pick')
            ->assertRedirect(route('draw.tokens', ['did' => $this->draw->did]));
        $this->actingAs($this->users[3])->get('/draw/tokens/999999/pick')->assertNotFound();
    }

    #[Test]
    public function token_routes_need_the_login_only_and_csrf(): void
    {
        $web = app('router')->getMiddlewareGroups()['web'];
        $csrf = array_values(array_filter($web, fn ($m) => is_string($m) && class_exists($m) && is_a($m, PreventRequestForgery::class, true)));
        $this->assertNotEmpty($csrf);
        $excluded = app($csrf[0])->getExcludedPaths();

        foreach (['draw.tokens', 'draw.tokens.pick', 'draw.tokens.pick.redirect'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $middleware = $route->gatherMiddleware();
            $this->assertContains('web', $middleware, $name);
            $this->assertContains('tournament-base.login:draw::common.login_required', $middleware, $name);
            $this->assertNotContains('can:admin.draw', $middleware, $name);
            $this->assertNotContains('auth', $middleware, $name);
            foreach ($excluded as $pattern) {
                $this->assertFalse(Request::create('/'.$route->uri(), 'POST')->is($pattern === '/' ? $pattern : trim((string) $pattern, '/')));
            }
        }

        // With verification active a token-less pick is refused and nothing is claimed.
        $this->tokenDraw();
        $this->withMiddleware();
        $this->app['env'] = 'local';
        $this->actingAs($this->users[3])->from(route('draw.tokens', ['did' => $this->draw->did]))
            ->post(route('draw.tokens.pick', ['did' => $this->draw->did]), ['dtid' => (string) $this->tokenWithSlot('A2')->dtid])
            ->assertRedirect(route('draw.tokens', ['did' => $this->draw->did]))
            ->assertSessionHas('error');
        $this->assertNull(DrawToken::query()->where('did', $this->draw->did)->whereNotNull('claimed_tpid')->first());
    }

    #[Test]
    public function real_login_session_can_pick(): void
    {
        $this->tokenDraw();
        $this->users[3]->forceFill(['pw' => bcrypt('geheim-123')])->save();

        $this->post('/login', ['email' => $this->users[3]->email, 'password' => 'geheim-123'])->assertRedirect();
        $this->assertAuthenticatedAs($this->users[3]);

        $token = $this->tokenWithSlot('A3');
        $this->post(route('draw.tokens.pick', ['did' => $this->draw->did]), ['dtid' => (string) $token->dtid])
            ->assertRedirect(route('draw.tokens', ['did' => $this->draw->did]));
        $this->assertSame($this->tpid[3], (int) $token->fresh()->claimed_tpid);
        $this->get(route('draw.tokens', ['did' => $this->draw->did]))->assertOk()->assertSee('Dein Token');
    }
}
