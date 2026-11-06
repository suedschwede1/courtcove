<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\TournamentBase;

use App\Models\User;
use App\Modules\TournamentBase\Support\ActionRefused;
use App\Modules\TournamentBase\Support\ModuleFlash;
use App\Services\UiLinter;
use App\Services\UiRules;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The shared module shell as used by the championship (Plan D Task 12). Plans B/C already built the login
 * middleware (`tournament-base.login`) and the 419 redirect (CsrfExpiredRedirect); this pins them for the
 * `tournament.*` routes and adds the flash list and the banners. The module tabs became the tournament sidebar.
 */
class ModuleShellTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'tournament-base.login:tournament::common.login_required'])->group(function (): void {
            Route::get('tournament/_probe', fn () => 'ok')->name('tournament.probe');
            Route::post('tournament/_probe', fn () => 'ok')->name('tournament.probe.post');
            Route::get('tournament/_flash', function () {
                ModuleFlash::success('Erzeugt: 0 Gruppenköpfe.');
                ModuleFlash::info('Gruppe A: offen');
                ModuleFlash::info('Gruppe B: offen');

                return redirect('/tournament/_show');
            })->name('tournament.flash');
            Route::get('tournament/_show', fn () => view('tournament-base::partials.flash')->render())->name('tournament.show');
        });
        Route::middleware('web')->post('elsewhere/_probe', fn () => 'ok')->name('elsewhere.probe');
    }

    private function expiredCsrf(): void
    {
        $this->withMiddleware();
        $this->app['env'] = 'local'; // CSRF verification is skipped while the env is "testing"
    }

    #[Test]
    public function guest_is_redirected_to_login_with_the_module_message(): void
    {
        $response = $this->get('/tournament/_probe?tab=1');

        $response->assertRedirect(route('login', ['redirect_to' => '/tournament/_probe?tab=1']));
        $response->assertSessionHas('error', 'Für den Turnierbereich musst du angemeldet sein.');
    }

    #[Test]
    public function a_really_logged_in_user_passes_the_module_login(): void
    {
        $user = User::factory()->create(['status' => 'enabled', 'email' => 'spieler01@example.test', 'pw' => bcrypt('geheim-123')]);

        $this->get('/tournament/_probe')->assertRedirect(route('login', ['redirect_to' => '/tournament/_probe']));
        $this->post('/login', ['email' => 'spieler01@example.test', 'password' => 'geheim-123']);

        $this->assertAuthenticatedAs($user);
        $this->get('/tournament/_probe')->assertOk()->assertSee('ok');
    }

    #[Test]
    public function csrf_mismatch_on_module_routes_flashes_and_redirects_back(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'enabled']));
        $this->expiredCsrf();

        $response = $this->from(url('/tournament/_probe'))->post('/tournament/_probe');

        $response->assertRedirect(url('/tournament/_probe'));
        $response->assertSessionHas('error', 'Ungültiger oder abgelaufener Formularschlüssel. Bitte Seite neu laden.');
    }

    #[Test]
    public function csrf_mismatch_without_referer_falls_back_to_the_module_index(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'enabled']));
        $this->expiredCsrf();

        $this->post('/tournament/_probe')->assertRedirect(route('tournament.index'));
    }

    #[Test]
    public function csrf_mismatch_elsewhere_keeps_the_default_419(): void
    {
        $this->expiredCsrf();

        $this->from('/tournament/_probe')->post('/elsewhere/_probe')->assertStatus(419);
    }

    #[Test]
    public function flash_keeps_several_messages(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'enabled']));

        $this->get('/tournament/_flash')->assertRedirect('/tournament/_show');
        $this->assertSame([
            ['type' => 'success', 'text' => 'Erzeugt: 0 Gruppenköpfe.'],
            ['type' => 'info', 'text' => 'Gruppe A: offen'],
            ['type' => 'info', 'text' => 'Gruppe B: offen'],
        ], ModuleFlash::all());

        $html = $this->get('/tournament/_show')->assertOk()->getContent();
        $this->assertSame(3, substr_count((string) $html, 'class="ds-notice '));
        $this->assertStringContainsString('ds-notice--good', (string) $html);
        $this->assertStringContainsString('Gruppe B: offen', (string) $html);

        // flashed once: the next request shows nothing
        $this->assertSame(0, substr_count((string) $this->get('/tournament/_show')->getContent(), 'class="ds-notice '));
    }

    #[Test]
    public function flash_does_not_carry_old_messages_into_a_new_list(): void
    {
        ModuleFlash::error('alt');
        $this->app['session.store']->ageFlashData(); // next request: "alt" is old flash data
        ModuleFlash::error('neu');

        $this->assertSame([['type' => 'error', 'text' => 'neu']], ModuleFlash::all());
    }

    #[Test]
    public function action_refused_carries_a_translation_key(): void
    {
        $e = new ActionRefused('tournament::common.login_required', ['x' => 1]);

        $this->assertSame('tournament::common.login_required', $e->key);
        $this->assertSame(['x' => 1], $e->replace);
        $this->assertSame('tournament::common.login_required', $e->getMessage());
    }

    #[Test]
    public function test_banner_shows_the_simulated_view_and_a_post_stop_button(): void
    {
        $html = view('tournament-base::partials.test-banner', [
            'simulation' => ['mode' => 'player', 'label' => 'Demo Spieler 07', 'tpid' => 7, 'realLevel' => 3],
            'stopUrl' => '/tournament/admin/simulate',
        ])->render();

        $this->assertStringContainsString('Testmodus', $html);
        $this->assertStringContainsString('Ansicht von Demo Spieler 07', $html);
        $this->assertStringContainsString('nur lesend – Eintragen ist gesperrt', $html);
        $this->assertMatchesRegularExpression('#<form method="post" action="/tournament/admin/simulate"#', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('name="mode" value="off"', $html);
        $this->assertStringContainsString('Testmodus beenden', $html);

        $this->assertSame('', trim(view('tournament-base::partials.test-banner', ['simulation' => null, 'stopUrl' => '/x'])->render()));
    }

    #[Test]
    public function test_tournament_bar_names_the_test_copy(): void
    {
        $html = view('tournament-base::partials.test-banner', ['simulation' => null, 'stopUrl' => '/x', 'testTournament' => 'TEST Demo-Meisterschaft'])->render();

        $this->assertStringContainsString('Testturnier', $html);
        $this->assertStringContainsString('TEST Demo-Meisterschaft', $html);
        $this->assertStringContainsString('Mitglieder sehen das echte Turnier', $html);
    }

    #[Test]
    public function module_layout_renders_title_sidebar_and_content(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'enabled']));
        view()->share('errors', new ViewErrorBag);
        ModuleFlash::info('Hinweis eins');

        $html = Blade::render(<<<'BLADE'
            @extends('tournament-base::layouts.module')
            @section('title', 'Demo-Meisterschaft')
            @section('eyebrow', 'Vereinsmeisterschaft 2027')
            @section('module-content')
                <p>Inhalt der Seite</p>
            @endsection
            BLADE);

        $this->assertStringContainsString('Demo-Meisterschaft', $html);
        $this->assertStringContainsString('Vereinsmeisterschaft 2027', $html);
        $this->assertStringContainsString('<aside id="admin-nav"', $html);
        $this->assertStringNotContainsString('ds-tabs', $html);
        $this->assertStringContainsString('Hinweis eins', $html);
        $this->assertStringContainsString('Inhalt der Seite', $html);
    }

    #[Test]
    public function views_pass_the_ui_linter(): void
    {
        $this->assertSame([], (new UiLinter(app_path('Modules/TournamentBase/resources/views'), UiRules::all()))->run());
    }

    #[Test]
    public function de_and_en_keys_match(): void
    {
        $dir = app_path('Modules/TournamentBase/resources/lang');
        foreach (array_map('basename', glob($dir.'/de/*.php') ?: []) as $file) {
            $de = array_keys(Arr::dot(require "$dir/de/$file"));
            $en = array_keys(Arr::dot(require "$dir/en/$file"));
            $this->assertSame([], array_merge(array_diff($de, $en), array_diff($en, $de)), $file);
        }
    }
}
