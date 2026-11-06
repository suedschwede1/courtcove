<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Models\User;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\TournamentBase\Support\TestModeSession;
use App\Services\UiLinter;
use App\Services\UiRules;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Tournament\Concerns\BuildsChampionship;
use Tests\TestCase;

class AdminOverviewTest extends TestCase
{
    use BuildsChampionship;
    use LazilyRefreshDatabase;

    #[Test]
    public function requires_admin_tournament(): void
    {
        $this->championship();

        $this->get(route('tournament.admin'))
            ->assertRedirect(route('login', ['redirect_to' => '/tournament/admin']))
            ->assertSessionHas('error', 'Für den Turnierbereich musst du angemeldet sein.');

        $this->actingAs($this->member())->get(route('tournament.admin'))->assertForbidden();

        $assist = User::factory()->status('assist')->create();
        $this->actingAs($assist)->get(route('tournament.admin'))->assertForbidden();
        $assist->setMeta('allow.admin.tournament', 'true');
        $this->actingAs($assist->fresh())->get(route('tournament.admin'))->assertOk()->assertSee('Demo-Meisterschaft');

        $this->actingAs($this->admin())->get(route('tournament.admin'))
            ->assertOk()
            ->assertSee('Turnierleitung')
            ->assertSee('6 Teilnehmer · '.TournamentMatch::query()->where('tid', $this->tournament->tid)->count().' Partien angelegt · Status in Vorbereitung');
    }

    #[Test]
    public function a_real_login_session_gets_through(): void
    {
        $this->championship();
        $admin = $this->admin();
        $admin->forceFill(['pw' => bcrypt('geheim-123')])->save();

        $this->post('/login', ['email' => $admin->email, 'password' => 'geheim-123']);
        $this->assertAuthenticatedAs($admin);

        $this->get(route('tournament.admin'))->assertOk()->assertSee('Format ist gültig.');
    }

    #[Test]
    public function without_any_tournament_the_empty_notice_answers(): void
    {
        $this->actingAs($this->admin())->get(route('tournament.admin'))
            ->assertOk()
            ->assertSee('Derzeit ist keine Vereinsmeisterschaft angelegt.');
    }

    #[Test]
    public function shows_running_draws_with_pick_progress(): void
    {
        $this->championship();
        $draw = Draw::factory()->token(4, 'AC', ['A2', 'B2', 'A3', 'B3'])->executed()
            ->create(['tid' => $this->tournament->tid, 'title' => 'Demo Gruppenauslosung', 'pick_deadline' => '2027-04-08 20:00:00']);
        DrawToken::query()->where('did', $draw->did)->where('position', 1)->update(['claimed_tpid' => $this->tpid[3], 'claimed_uid' => 1]);

        // A token draw of another tournament stays out.
        $other = Tournament::factory()->create(['title' => 'Andere Meisterschaft']);
        Draw::factory()->token(4, 'AC', ['A2', 'B2', 'A3', 'B3'])->executed()->create(['tid' => $other->tid, 'title' => 'Fremde Auslosung']);

        $this->actingAs($this->admin())->get(route('tournament.admin'))
            ->assertOk()
            ->assertSee('Laufende Ziehungen')
            ->assertSee('Demo Gruppenauslosung')
            ->assertSee('Wahlphase offen – Gruppe erscheint beim Klick')
            ->assertSee('1 von 4 Tokens gewählt')
            ->assertSee('Wahl bis 08.04.2027 20:00')
            ->assertSee('Wahlphase schließen')
            ->assertSee(route('draw.close', ['did' => $draw->did]), false)
            ->assertSee(route('draw.tokens', ['did' => $draw->did]), false)
            ->assertSee(route('draw.record', ['slug' => $draw->slug]), false)
            ->assertDontSee('Fremde Auslosung');
    }

    #[Test]
    public function draw_buttons_need_the_draw_privilege(): void
    {
        $this->championship();
        $draw = Draw::factory()->token(4, 'AC', ['A2', 'B2', 'A3', 'B3'])->executed()
            ->create(['tid' => $this->tournament->tid, 'title' => 'Demo Gruppenauslosung']);

        $assist = User::factory()->status('assist')->create();
        $assist->setMeta('allow.admin.tournament', 'true');

        $this->actingAs($assist->fresh())->get(route('tournament.admin'))
            ->assertOk()
            ->assertSee('Demo Gruppenauslosung')
            ->assertDontSee(route('draw.close', ['did' => $draw->did]), false)
            ->assertDontSee('href="'.route('draw.index').'"', false);
    }

    #[Test]
    public function lists_unapplied_executed_draws_only(): void
    {
        $this->championship();
        $tid = $this->tournament->tid;
        Draw::factory()->executed()->create(['tid' => $tid, 'title' => 'Wartet auf Anwendung']);
        Draw::factory()->token(4, 'AC', ['A2', 'B2', 'A3', 'B3'])->executed()->closed()->create(['tid' => $tid, 'title' => 'Geschlossen offen']);
        Draw::factory()->executed()->create(['tid' => $tid, 'title' => 'Schon angewendet', 'applied_at' => '2027-04-01 10:00:00', 'applied_uid' => 1]);
        Draw::factory()->executed()->discarded()->create(['tid' => $tid, 'title' => 'Verworfen ausgeführt']);
        Draw::factory()->create(['tid' => $tid, 'title' => 'Nicht gezogen']);

        $html = (string) $this->actingAs($this->admin())->get(route('tournament.admin'))->assertOk()->getContent();
        $apply = $this->section($html, 'Ausgeführte Auslosungen anwenden');

        $this->assertStringContainsString('Wartet auf Anwendung', $apply);
        $this->assertStringContainsString('Geschlossen offen', $apply);
        $this->assertStringNotContainsString('Schon angewendet', $apply);
        $this->assertStringNotContainsString('Verworfen ausgeführt', $apply);
        $this->assertStringNotContainsString('Nicht gezogen', $apply);
    }

    #[Test]
    public function lists_open_lot_decisions(): void
    {
        $this->championship();
        // Group A: seeds 1 and 4 both beat seed 5 by the same score, their own match is open: a full tie.
        $this->beat(1, 5);
        $this->beat(4, 5);

        $this->actingAs($this->admin())->get(route('tournament.admin'))
            ->assertOk()
            ->assertSee('Losentscheid offen: Gruppe A')
            ->assertSee('Demo Spieler 01 (Teilnehmer '.$this->tpid[1].')')
            ->assertSee('Demo Spieler 04 (Teilnehmer '.$this->tpid[4].')')
            ->assertSee('group:A')
            ->assertDontSee('Derzeit ist kein Losentscheid nötig.');

        // A manual order resolves the tie: it is no longer open but decided.
        $this->tournament->update(['lot_orders' => json_encode(['group:A' => [$this->tpid[4], $this->tpid[1]]])]);
        $this->app->forgetScopedInstances();

        $this->actingAs($this->admin())->get(route('tournament.admin'))
            ->assertOk()
            ->assertDontSee('Losentscheid offen: Gruppe A')
            ->assertSee('Entschieden:')
            ->assertSee('Gruppe A: <strong>Demo Spieler 04 vor Demo Spieler 01</strong>', false);
    }

    #[Test]
    public function no_tie_says_no_lot_is_needed(): void
    {
        $this->championship();

        $this->actingAs($this->admin())->get(route('tournament.admin'))
            ->assertOk()
            ->assertSee('Derzeit ist kein Losentscheid nötig.');
    }

    #[Test]
    public function test_tools_hide_simulate_results_for_real_tournaments(): void
    {
        $this->championship();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('tournament.admin'))
            ->assertOk()
            ->assertDontSee('Gruppenphase durchrechnen')
            ->assertSee('Das ist das echte Turnier.');

        $test = Tournament::factory()->test()->create(['title' => 'TEST Demo-Meisterschaft', 'format' => $this->tournament->format]);

        $this->actingAs($admin)
            ->withSession([TestModeSession::KEY => ['tid' => (int) $test->tid, 'mode' => null, 'tpid' => null]])
            ->get(route('tournament.admin'))
            ->assertOk()
            ->assertSee('TEST Demo-Meisterschaft')
            ->assertSee('Testturnier.')
            ->assertSee('Gruppenphase durchrechnen')
            ->assertSee('In diesem Testturnier werden Ziehungen dabei gelöscht.')
            ->assertDontSee('Das ist das echte Turnier.');
    }

    #[Test]
    public function sidebar_shows_tournament_entries_by_permission(): void
    {
        $this->championship();

        $this->actingAs($this->admin())->get(route('tournament.admin'))
            ->assertOk()
            ->assertSee('href="'.route('tournament.admin').'" class="ds-admin-sidebar__link" aria-current="page"', false)
            ->assertSee('href="'.route('draw.index').'" class="ds-admin-sidebar__link"', false);

        $assist = User::factory()->status('assist')->create();
        $assist->setMeta('allow.admin.draw', 'true');

        $this->actingAs($assist->fresh())->get(route('draw.index'))
            ->assertOk()
            ->assertSee('href="'.route('draw.index').'"', false)
            ->assertDontSee('href="'.route('tournament.admin').'"', false);
    }

    #[Test]
    public function couples_are_listed_with_dangling_references(): void
    {
        $this->championship();
        TournamentPlayer::query()->whereKey($this->tpid[3])->update(['partner_tpid' => $this->tpid[4]]);
        TournamentPlayer::query()->whereKey($this->tpid[4])->update(['partner_tpid' => $this->tpid[3]]);
        TournamentPlayer::query()->whereKey($this->tpid[6])->update(['partner_tpid' => 999999]);

        $this->actingAs($this->admin())->get(route('tournament.admin'))
            ->assertOk()
            ->assertSee('Demo Spieler 03 –', false)
            ->assertSee('Verweis ins Leere')
            ->assertSee('(tpid 999999 gibt es nicht mehr)')
            ->assertDontSee('Noch kein Paar erfasst.');
    }

    #[Test]
    public function every_post_form_carries_csrf(): void
    {
        $this->championship();
        $tid = $this->tournament->tid;
        Draw::factory()->token(4, 'AC', ['A2', 'B2', 'A3', 'B3'])->executed()->create(['tid' => $tid]);
        Draw::factory()->token(4, 'AC', ['A2', 'B2', 'A3', 'B3'])->create(['tid' => $tid]);
        Draw::factory()->token(4, 'AC', ['A2', 'B2', 'A3', 'B3'])->discarded()->create(['tid' => $tid]);
        Draw::factory()->executed()->create(['tid' => $tid]);

        $html = (string) $this->actingAs($this->admin())->get(route('tournament.admin'))->assertOk()->getContent();

        preg_match_all('/<form\b[^>]*method="POST"[^>]*>(.*?)<\/form>/is', $html, $forms);
        $this->assertGreaterThanOrEqual(4, count($forms[0]));

        foreach ($forms[1] as $body) {
            $this->assertStringContainsString('name="_token"', $body);
        }

        $this->assertStringNotContainsString('method="GET"', $this->main($html));
    }

    #[Test]
    public function page_passes_the_ui_linter(): void
    {
        $this->assertSame([], (new UiLinter(app_path('Modules/Tournament/resources/views'), UiRules::all()))->run());

        $this->championship();
        $html = (string) $this->actingAs($this->admin())->get(route('tournament.admin'))->assertOk()->getContent();

        $main = $this->main($html);
        $this->assertStringNotContainsString('style="', $main);
        $this->assertStringNotContainsString('onclick=', $main);
        $this->assertStringNotContainsString('onsubmit=', $main);
    }

    #[Test]
    public function the_admin_tab_appears_on_the_championship_pages(): void
    {
        $this->championship();

        $this->actingAs($this->admin())->get(route('tournament.index'))
            ->assertOk()
            ->assertSee('href="'.route('tournament.admin').'"', false);

        $this->actingAs($this->member())->get(route('tournament.index'))
            ->assertOk()
            ->assertDontSee('href="'.route('tournament.admin').'"', false);
    }

    private function beat(int $winnerSeed, int $loserSeed): void
    {
        $a = $this->tpid[$winnerSeed];
        $b = $this->tpid[$loserSeed];
        $match = TournamentMatch::query()->where('tid', $this->tournament->tid)
            ->where(fn ($q) => $q->where(['tpid_a' => $a, 'tpid_b' => $b])->orWhere(fn ($q) => $q->where(['tpid_a' => $b, 'tpid_b' => $a])))
            ->firstOrFail();
        $aWins = (int) $match->tpid_a === $a;

        $match->update([
            'played' => '2027-05-03', 'played_time' => '17:00:00',
            'score' => $aWins ? '6:4 6:3' : '4:6 3:6',
            's1a' => $aWins ? 6 : 4, 's1b' => $aWins ? 4 : 6, 's2a' => $aWins ? 6 : 3, 's2b' => $aWins ? 3 : 6,
            'sets_a' => $aWins ? 2 : 0, 'sets_b' => $aWins ? 0 : 2, 'games_a' => $aWins ? 12 : 7, 'games_b' => $aWins ? 7 : 12,
            'winner' => $a,
        ]);
        $this->app->forgetScopedInstances();
    }

    private function section(string $html, string $heading): string
    {
        $start = strpos($html, $heading);
        $this->assertNotFalse($start, $heading);

        return substr($html, $start, 4000);
    }

    private function main(string $html): string
    {
        $start = strpos($html, '<main');

        return $start === false ? '' : substr($html, $start);
    }
}
