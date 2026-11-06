<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawEligible;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\TournamentBase\Support\TestModeSession;
use App\Services\UiLinter;
use App\Services\UiRules;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Tournament\Concerns\BuildsChampionship;
use Tests\TestCase;

class OverviewPageTest extends TestCase
{
    use BuildsChampionship;
    use LazilyRefreshDatabase;

    /** Routes of later tasks (draw admin, token page, tournament admin) the tabs and hints link to. */
    private function stubLaterRoutes(): void
    {
        Route::middleware('web')->group(function (): void {
            Route::get('draw', fn () => 'draw')->name('draw.index');
            Route::get('draw/tokens/{did}', fn () => 'tokens')->name('draw.tokens');
            Route::get('tournament/admin', fn () => 'admin')->name('tournament.admin');
            Route::post('tournament/admin/simulate', fn () => 'sim')->name('tournament.admin.simulate');
        });
        Route::getRoutes()->refreshNameLookups();
    }

    #[Test]
    public function guest_is_sent_to_login(): void
    {
        $this->championship();

        $this->get('/tournament')
            ->assertRedirect(route('login', ['redirect_to' => '/tournament']))
            ->assertSessionHas('error', 'Für den Turnierbereich musst du angemeldet sein.');
        $this->get('/tournament/modus')->assertRedirect(route('login', ['redirect_to' => '/tournament/modus']));
    }

    #[Test]
    public function a_really_logged_in_member_reaches_the_overview(): void
    {
        $this->championship();
        $this->member(['email' => 'spieler01@example.test', 'pw' => bcrypt('geheim-123')]);

        $this->post('/login', ['email' => 'spieler01@example.test', 'password' => 'geheim-123']);

        $this->get('/tournament')->assertOk()->assertSee('Übersicht');
    }

    #[Test]
    public function member_sees_the_overview_with_progress(): void
    {
        $this->championship();
        $winner = $this->play('A-1');
        $this->schedule('B-1');

        $html = $this->actingAs($this->member())->get('/tournament')->assertOk()->getContent();

        $this->assertStringContainsString('Übersicht', $html);
        $this->assertStringContainsString('Du bist nicht als Teilnehmer gemeldet.', $html);
        // KPI tiles: 1 of 6 played, 1 scheduled, 4 without a date
        $this->assertMatchesRegularExpression('#gespielt</p>\s*<p class="ds-kpi__value">1/6</p>#', $html);
        $this->assertMatchesRegularExpression('#geplant</p>\s*<p class="ds-kpi__value">1</p>#', $html);
        $this->assertMatchesRegularExpression('#ohne Termin</p>\s*<p class="ds-kpi__value">4</p>#', $html);
        // group progress with the leader (the winner of A-1 leads group A)
        $leader = sprintf('Demo Spieler %02d', array_search((int) $winner->winner, $this->tpid, true));
        $this->assertMatchesRegularExpression('#Gruppe A.*?1 von 3 gespielt.*?1\.\s*'.$leader.'#s', $html);
        $this->assertStringContainsString('Aufsteiger in den Hauptbewerb', $html);
        $this->assertStringContainsString('Gruppe B offen', $html);
    }

    #[Test]
    public function participant_sees_the_next_match_card(): void
    {
        $user = $this->member();
        $this->championship([1 => $user]);
        $this->tournament->players()->where('seed', 4)->update(['phone' => '0664 1234567']);

        $html = $this->actingAs($user)->get('/tournament')->assertOk()->getContent();

        $this->assertStringContainsString('Dein nächstes Spiel', $html);
        $this->assertStringContainsString('ds-card--highlight', $html);
        $this->assertStringContainsString('noch kein Termin vereinbart', $html);
        $this->assertStringContainsString('Termin eintragen', $html);
        $own = TournamentMatch::query()->where('tid', $this->tournament->tid)
            ->where(fn ($q) => $q->where('tpid_a', $this->tpid[1])->orWhere('tpid_b', $this->tpid[1]))
            ->orderBy('phase')->orderBy('grp')->orderBy('slot')->orderBy('tmid')->firstOrFail();
        $this->assertStringContainsString('href="'.route('tournament.match', ['tmid' => $own->tmid]).'"', $html);
        $this->assertStringContainsString('https://wa.me/436641234567', $html);
        $this->assertStringNotContainsString('Du bist nicht als Teilnehmer gemeldet.', $html);
    }

    #[Test]
    public function admin_sees_draw_and_admin_entries(): void
    {
        $this->stubLaterRoutes();
        $this->championship();

        $html = $this->actingAs($this->admin())->get('/tournament')->assertOk()->getContent();

        $this->assertStringContainsString('>Auslosungen verwalten</a>', $html);
        $this->assertStringContainsString('href="'.route('draw.index').'"', $html);
        $this->assertStringContainsString('>Leitung</a>', $html);
        $this->assertStringContainsString('href="'.route('tournament.admin').'"', $html);
    }

    #[Test]
    public function member_does_not(): void
    {
        $this->stubLaterRoutes();
        $this->championship();

        $html = $this->actingAs($this->member())->get('/tournament')->assertOk()->getContent();

        $this->assertStringNotContainsString('>Auslosungen verwalten</a>', $html);
        $this->assertStringNotContainsString('>Leitung</a>', $html);
        foreach (['Übersicht', 'Modus'] as $tab) {
            $this->assertStringContainsString('>'.$tab.'</a>', $html);
        }
    }

    #[Test]
    public function open_token_draw_shows_the_pick_hint(): void
    {
        $this->stubLaterRoutes();
        $user = $this->member();
        $this->championship([1 => $user]);
        $draw = Draw::factory()->token()->executed()->create(['tid' => $this->tournament->tid, 'pick_deadline' => '2027-04-20 18:00:00']);
        DrawEligible::query()->create(['did' => $draw->did, 'tpid' => $this->tpid[1]]);

        $html = $this->actingAs($user)->get('/tournament')->assertOk()->getContent();

        $this->assertStringContainsString('Du hast noch keinen Token gewählt.', $html);
        $this->assertStringContainsString('bis 20.04.2027 18:00 – danach wird zugeteilt', $html);
        $this->assertStringContainsString('href="'.route('draw.tokens', ['did' => $draw->did]).'"', $html);

        // a member who is not in the pot gets no hint
        $other = $this->member();
        $this->assertStringNotContainsString('Token wählen', (string) $this->actingAs($other)->get('/tournament')->getContent());
    }

    #[Test]
    public function no_tournament_shows_the_empty_page(): void
    {
        $this->actingAs($this->member())->get('/tournament')
            ->assertOk()
            ->assertSee('Derzeit ist keine Vereinsmeisterschaft angelegt.');
    }

    #[Test]
    public function test_banner_is_shown_in_simulation(): void
    {
        $this->stubLaterRoutes();
        $this->championship();
        $admin = $this->admin();
        $this->app->make(TestModeSession::class)->simulate($admin, 'player', $this->tpid[2]);

        $html = $this->actingAs($admin)->get('/tournament')->assertOk()->getContent();

        $this->assertStringContainsString('Testmodus', $html);
        $this->assertStringContainsString('Ansicht von Demo Spieler 02', $html);
        $this->assertStringContainsString('action="'.route('tournament.admin.simulate').'"', $html);
        // the simulated level hides the admin entry
        $this->assertStringNotContainsString('>Leitung</a>', $html);
    }

    #[Test]
    public function mode_page_renders(): void
    {
        $this->championship();

        $html = $this->actingAs($this->member())->get('/tournament/modus')->assertOk()->getContent();

        $this->assertStringContainsString('So läuft die Meisterschaft', $html);
        $this->assertStringContainsString('6 Teilnehmer', $html);
        $this->assertStringContainsString('Gruppenphase', $html);
        $this->assertStringContainsString('6 Spieler in 2 Gruppen · 6 Gruppenspiele', $html);
        $this->assertStringContainsString('Match-Tiebreak bis 10', $html);
        $this->assertStringContainsString('Die besten 4', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
    }

    #[Test]
    public function pages_pass_the_ui_linter_and_use_base_path(): void
    {
        $this->assertSame([], (new UiLinter(app_path('Modules/Tournament/resources/views'), UiRules::all()))->run());

        $this->withServerVariables([
            'SCRIPT_NAME' => '/booking/index.php',
            'SCRIPT_FILENAME' => base_path('public/index.php'),
            'PHP_SELF' => '/booking/index.php',
        ]);
        $this->championship();

        $html = $this->actingAs($this->member())->get('/booking/tournament')->assertOk()->getContent();

        $this->assertStringEndsWith('/booking/tournament', url('/tournament'));
        $this->assertStringContainsString('href="'.url('/tournament/modus').'"', $html);
        $this->assertStringNotContainsString('href="/tournament', $html);
    }
}
