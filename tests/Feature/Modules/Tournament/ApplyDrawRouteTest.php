<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Models\User;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentPlayer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Draw\Concerns\BuildsTokenDraw;
use Tests\TestCase;

class ApplyDrawRouteTest extends TestCase
{
    use BuildsTokenDraw;
    use LazilyRefreshDatabase;

    /** A closed token draw: every token claimed by the pot players 3 to 6. */
    private function closedDraw(): Draw
    {
        $this->tokenDraw();
        foreach (['A2' => 3, 'B2' => 4, 'A3' => 5, 'B3' => 6] as $slot => $seed) {
            $this->claimDirectly($this->tokenWithSlot($slot), $seed);
        }
        Draw::query()->whereKey($this->draw->did)->update(['status' => 'closed', 'closed' => now()->format('Y-m-d H:i:s')]);

        return $this->draw->fresh();
    }

    private function boss(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function apply_route_applies_and_flashes(): void
    {
        $draw = $this->closedDraw();
        $admin = $this->boss();

        $this->actingAs($admin)->post(route('tournament.admin.apply-draw', ['did' => $draw->did]))
            ->assertRedirect(route('tournament.admin'))
            ->assertSessionHas('success', '4 Zuordnungen atomar angewendet.');

        $this->assertSame(['A', 2], [TournamentPlayer::query()->whereKey($this->tpid[3])->value('grp'), (int) TournamentPlayer::query()->whereKey($this->tpid[3])->value('grp_pos')]);
        $this->assertSame(['B', 3], [TournamentPlayer::query()->whereKey($this->tpid[6])->value('grp'), (int) TournamentPlayer::query()->whereKey($this->tpid[6])->value('grp_pos')]);
        $fresh = $draw->fresh();
        $this->assertNotNull($fresh->applied_at);
        $this->assertSame((int) $admin->uid, (int) $fresh->applied_uid);

        // A second apply is refused and writes nothing.
        $this->actingAs($admin)->post(route('tournament.admin.apply-draw', ['did' => $draw->did]))
            ->assertSessionHas('error', 'Diese Auslosung wurde bereits angewendet.');
    }

    #[Test]
    public function only_draws_of_the_selected_tournament_apply(): void
    {
        $draw = $this->closedDraw();
        $admin = $this->boss();
        // Another (newer, active) real tournament is now the current one: the draw belongs to the old one.
        Tournament::query()->update(['active' => 0]);
        Tournament::factory()->active()->create(['season' => 2030]);

        $this->actingAs($admin)->post(route('tournament.admin.apply-draw', ['did' => $draw->did]))
            ->assertSessionHas('error', 'Auslosung fehlt, ist noch nicht abgeschlossen, wurde nicht gezogen, ist verworfen oder gehört zu einem anderen Turnier.');
        $this->actingAs($admin)->post('/tournament/admin/apply-draw/999999')
            ->assertSessionHas('error', 'Auslosung fehlt, ist noch nicht abgeschlossen, wurde nicht gezogen, ist verworfen oder gehört zu einem anderen Turnier.');
        $this->actingAs($admin)->post('/tournament/admin/apply-draw/99999999999')
            ->assertSessionHas('error', 'Auslosung fehlt, ist noch nicht abgeschlossen, wurde nicht gezogen, ist verworfen oder gehört zu einem anderen Turnier.');

        $this->assertNull($draw->fresh()->applied_at);
        $this->assertNull(TournamentPlayer::query()->whereKey($this->tpid[3])->value('grp'));
    }

    #[Test]
    public function requires_admin_tournament_and_csrf(): void
    {
        $draw = $this->closedDraw();
        $url = route('tournament.admin.apply-draw', ['did' => $draw->did]);

        $this->post($url)
            ->assertRedirect(route('login', ['redirect_to' => '/tournament/admin/apply-draw/'.$draw->did]))
            ->assertSessionHas('error', 'Für den Turnierbereich musst du angemeldet sein.');
        $this->actingAs($this->users[3])->post($url)->assertForbidden();
        $this->actingAs($this->users[3])->post('/tournament/admin/apply-draw/999999')->assertForbidden();

        $drawAssist = User::factory()->status('assist')->create();
        $drawAssist->setMeta('allow.admin.draw', 'true');
        $this->actingAs($drawAssist->fresh())->post($url)->assertForbidden();
        $this->assertNull($draw->fresh()->applied_at);

        // With CSRF verification active, a post without a token is refused.
        $this->withMiddleware();
        $this->app['env'] = 'local';
        $this->actingAs($this->boss())->from(route('tournament.admin'))->post($url)
            ->assertRedirect(route('tournament.admin'))
            ->assertSessionHas('error', 'Ungültiger oder abgelaufener Formularschlüssel. Bitte Seite neu laden.');
        $this->assertNull($draw->fresh()->applied_at);
    }

    #[Test]
    public function get_redirects_to_admin(): void
    {
        $draw = $this->closedDraw();
        $admin = $this->boss();

        $this->actingAs($admin)->get('/tournament/admin/apply-draw/'.$draw->did)->assertRedirect(route('tournament.admin'));
        foreach (['prepare-draw', 'prepare-bracket-draw'] as $action) {
            $this->actingAs($admin)->get('/tournament/admin/'.$action)->assertRedirect(route('tournament.admin'));
        }
        $this->assertNull($draw->fresh()->applied_at);
    }

    #[Test]
    public function the_admin_page_offers_apply_for_the_closed_draw(): void
    {
        $draw = $this->closedDraw();

        $this->actingAs($this->boss())->get(route('tournament.admin'))
            ->assertOk()
            ->assertSee('action="'.route('tournament.admin.apply-draw', ['did' => $draw->did]).'"', false);

        $this->assertSame(4, DrawToken::query()->where('did', $draw->did)->whereNotNull('claimed_tpid')->count());
    }

    #[Test]
    public function a_real_login_session_applies(): void
    {
        $draw = $this->closedDraw();
        $admin = User::factory()->create(['status' => 'admin', 'email' => 'leitung@example.test', 'pw' => bcrypt('geheim-123')]);

        $this->post('/login', ['email' => 'leitung@example.test', 'password' => 'geheim-123']);
        $this->assertAuthenticatedAs($admin);

        $this->post(route('tournament.admin.apply-draw', ['did' => $draw->did]))->assertRedirect(route('tournament.admin'));
        $this->assertNotNull($draw->fresh()->applied_at);
    }
}
