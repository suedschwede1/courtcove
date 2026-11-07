<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\TournamentBase;

use App\Models\User;
use App\Modules\Draw\Database\Seeders\DrawDemoSeeder;
use App\Modules\MixedTournament\Database\Seeders\MixedDemoSeeder;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedRound;
use App\Modules\Pyramid\Database\Seeders\PyramidDemoSeeder;
use App\Modules\Tournament\Database\Seeders\TournamentDemoSeeder;
use App\Services\MainNavigation;
use App\Services\UiLinter;
use App\Services\UiRules;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The tournament sidebar (area shell) on every page of Pyramid, championship, draws and Mixed. Each tournament is
 * standalone: a page lists only its own module's group (the draws sit in the championship's). Groups and
 * entries by permission (admin, member, guest with the login requirement off), exactly one active entry per GET
 * route, the phone slide-in markup, no module tab rows left, print views without the shell.
 */
class TournamentSidebarTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** GET routes without parameters => label of the entry that must be the current page (admin viewer). */
    private const ACTIVE = [
        'pyramide.index' => 'Rangliste',
        'pyramide.rules' => 'Regeln',
        'pyramide.challenges' => 'Forderungen',
        'pyramide.admin.index' => 'Verwaltung',
        'tournament.index' => 'Übersicht',
        'tournament.groups' => 'Gruppen',
        'tournament.bracket' => 'KO-Raster',
        'tournament.schedule' => 'Spielplan',
        'tournament.mine' => 'Meine Spiele',
        'tournament.players' => 'Teilnehmer',
        'tournament.mode' => 'Modus',
        'tournament.admin' => 'Leitung',
        'draw.index' => 'Auslosungen verwalten',
        // The demo has a default event: /mixed-tournament opens it, its "Teilnehmer" page is current.
        'mixed.index' => 'Teilnehmer',
        'mixed.info' => 'Info',
        'mixed.admin.index' => 'Verwaltung',
        'mixed.admin.import-members' => 'Mitglieder importieren',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
    }

    private function seedModules(): void
    {
        $this->seed(PyramidDemoSeeder::class);
        $this->seed(TournamentDemoSeeder::class);
        $this->seed(DrawDemoSeeder::class);
        $this->seed(MixedDemoSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->status('admin')->create();
    }

    /**
     * Every GET page of the tournament modules without a route parameter (redirect-only routes left out).
     *
     * @return list<string>
     */
    private function parameterlessGetRoutes(): array
    {
        $names = [];
        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            if (! preg_match('/^(pyramide|tournament|draw|mixed)\./', $name) || ! in_array('GET', $route->methods(), true)) {
                continue;
            }
            if (preg_match('/\{[^}?]+\}/', $route->uri()) || str_ends_with($name, 'redirect') || $name === 'pyramide.admin.create') {
                continue;
            }
            $names[] = $name;
        }
        sort($names);

        return $names;
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($dom);
    }

    /**
     * @return array<string, list<string>> group heading => entry labels (top level)
     */
    private function sidebar(string $html): array
    {
        $xp = $this->xpath($html);
        $groups = [];
        foreach ($xp->query('//aside[@id="admin-nav"]//div[@class="ds-admin-sidebar__section"]') as $section) {
            $heading = trim((string) $xp->query('./p', $section)->item(0)?->textContent);
            $labels = [];
            foreach ($xp->query('./ul/li/a', $section) as $a) {
                $labels[] = trim($a->textContent);
            }
            $groups[$heading] = $labels;
        }

        return $groups;
    }

    #[Test]
    public function the_parameterless_get_routes_are_the_known_ones(): void
    {
        $this->assertEqualsCanonicalizing(array_keys(self::ACTIVE), $this->parameterlessGetRoutes());
    }

    #[Test]
    public function every_page_marks_exactly_its_entry_and_carries_the_slide_in(): void
    {
        $this->seedModules();
        $admin = $this->admin();

        foreach (self::ACTIVE as $name => $label) {
            $response = $this->actingAs($admin)->followingRedirects()->get(route($name));
            $this->assertSame(200, $response->getStatusCode(), $name);
            $html = (string) $response->getContent();
            $xp = $this->xpath($html);

            $current = $xp->query('//aside[@id="admin-nav"]//a[@aria-current="page"]');
            $this->assertSame(1, $current->length, $name);
            $this->assertSame($label, trim($current->item(0)->textContent), $name);
            $this->assertSame(1, substr_count($html, 'aria-current="page"'), $name);

            // Slide-in: the menu button controls the sidebar, the content is inert while it is open (focus trap).
            $button = $xp->query('//button[@aria-controls="admin-nav"][@aria-expanded="false"][@x-ref="navButton"]');
            $this->assertSame(1, $button->length, $name);
            $this->assertStringContainsString('<main id="content" class="ds-admin-main" x-bind:inert="navOpen">', $html, $name);
            $this->assertStringContainsString('x-on:keydown.escape.window="if (navOpen)', $html, $name);
            $this->assertSame(1, $xp->query('//aside[@id="admin-nav"]//button[@x-ref="navClose"]')->length, $name);

            // No module tab row is the navigation any more.
            $this->assertStringNotContainsString('ds-tabs', $html, $name);
            $this->assertStringNotContainsString('<header class="ds-header', $html, $name);
        }
    }

    #[Test]
    public function an_admin_sees_only_the_sidebar_of_the_current_tournament(): void
    {
        $this->seedModules();
        $admin = $this->actingAs($this->admin());

        $groups = $this->sidebar((string) $admin->get(route('pyramide.index'))->getContent());
        $this->assertSame([
            'Forderungspyramide' => ['Rangliste', 'Forderungen', 'Regeln'],
            'Einstellungen' => ['Verwaltung'],
        ], $groups);

        $groups = $this->sidebar((string) $admin->get(route('tournament.index'))->getContent());
        $this->assertSame(['Vereinsmeisterschaft', 'Einstellungen'], array_keys($groups));
        $this->assertSame(['Übersicht', 'Gruppen', 'KO-Raster', 'Spielplan', 'Meine Spiele', 'Teilnehmer', 'Modus'],
            array_slice($groups['Vereinsmeisterschaft'], 0, 7));
        $this->assertSame(['Leitung', 'Auslosungen verwalten'], $groups['Einstellungen']);

        // The draw admin belongs to the championship.
        $this->assertSame(['Vereinsmeisterschaft', 'Einstellungen'], array_keys($this->sidebar((string) $admin->get(route('draw.index'))->getContent())));

        $groups = $this->sidebar((string) $admin->get(route('mixed.admin.index'))->getContent());
        $this->assertSame(['Mixed-/Fun-Turnier', 'Einstellungen'], array_keys($groups));
        $this->assertContains('Wertung', $groups['Mixed-/Fun-Turnier']);
        $this->assertNotContains('Veranstaltungen', $groups['Mixed-/Fun-Turnier']); // a default event exists
        $this->assertSame(['Verwaltung', 'Mitglieder importieren'], $groups['Einstellungen']);
    }

    #[Test]
    public function a_member_sees_no_administration_entries(): void
    {
        $this->seedModules();
        $member = User::factory()->status('enabled')->create();

        foreach (['pyramide.index', 'tournament.index', 'mixed.index'] as $name) {
            $html = (string) $this->actingAs($member)->followingRedirects()->get(route($name))->assertOk()->getContent();
            $nav = (string) strstr((string) strstr($html, '<aside id="admin-nav"'), '</aside>', true);

            foreach (['pyramide.admin.index', 'tournament.admin', 'draw.index', 'mixed.admin.index', 'mixed.admin.import-members'] as $admin) {
                $this->assertStringNotContainsString('href="'.route($admin).'"', $nav, $name.' / '.$admin);
            }
            $own = ['pyramide.index' => 'Rangliste', 'tournament.index' => 'Übersicht', 'mixed.index' => 'Teilnehmer'][$name];
            $this->assertStringContainsString('>'.$own.'</a>', $nav, $name);
        }
    }

    #[Test]
    public function guests_see_only_public_entries_when_the_login_requirement_is_off(): void
    {
        config(['tournament-base.require_login' => false]);
        $this->seedModules();

        $groups = $this->sidebar((string) $this->get(route('pyramide.index'))->assertOk()->getContent());
        $this->assertSame(['Forderungspyramide' => ['Rangliste', 'Forderungen', 'Regeln']], $groups);

        // On a record page the guest gets that record only (slugs are not listed to guests).
        $html = (string) $this->get(route('draw.record', ['slug' => 'demo-direct']))->assertOk()->getContent();
        $groups = $this->sidebar($html);
        $this->assertSame(['Vereinsmeisterschaft' => ['Losprotokoll']], $groups);
        $this->assertStringNotContainsString('demo-committed', $html);
        $this->assertStringContainsString('aria-current="page">Losprotokoll</a>', $html);
        // The guest's sidebar foot and phone bar offer the login.
        $this->assertStringContainsString('ds-admin-sidebar__login', $html);
    }

    #[Test]
    public function the_current_mixed_event_is_a_sub_group_with_its_pages(): void
    {
        $event = MixedEvent::factory()->create(['title' => 'Beispiel-Mixed']);
        MixedRound::factory()->create(['event_id' => $event->id, 'round_no' => 1, 'status' => 'completed']);
        $open = MixedRound::factory()->create(['event_id' => $event->id, 'round_no' => 2, 'status' => 'confirmed']);

        $html = (string) $this->actingAs($this->admin())->get(route('mixed.event', ['event' => $event->id]))->assertOk()->getContent();
        $xp = $this->xpath($html);

        $groups = $this->sidebar($html);
        $this->assertSame(['Aktuelle Runde', 'Wertung', 'Veranstaltungen', 'Beispiel-Mixed', 'Info'], $groups['Mixed-/Fun-Turnier']);
        $this->assertSame(['Verwaltung', 'Mitglieder importieren'], $groups['Einstellungen']);
        $this->assertSame(route('mixed.round', ['round' => $open->id]), $xp->query('//aside//a[normalize-space()="Aktuelle Runde"]')->item(0)?->getAttribute('href'));
        $parent = $xp->query('//aside//li[a[normalize-space()="Beispiel-Mixed"]]')->item(0);
        $this->assertSame('true', $xp->query('./a', $parent)->item(0)->getAttribute('aria-current'));
        $sub = [];
        foreach ($xp->query('./ul[contains(@class,"ds-admin-sidebar__list--sub")]/li/a', $parent) as $a) {
            $sub[] = trim(preg_replace('/\s+/', ' ', $a->textContent));
        }
        $this->assertSame(['Teilnehmer', 'Runde 1 (abgeschlossen)', 'Runde 2', '+ Neue Runde', 'Durchmischung', 'Konfiguration'], $sub);
        $this->assertSame('page', $xp->query('./ul/li/a[normalize-space()="Teilnehmer"]', $parent)->item(0)->getAttribute('aria-current'));
    }

    #[Test]
    public function the_info_flash_is_shown_once_by_the_shell(): void
    {
        $this->seed(PyramidDemoSeeder::class);

        $html = (string) $this->actingAs($this->admin())->withSession(['info' => 'Gespeichert'])->get(route('pyramide.index'))->getContent();

        $this->assertSame(1, substr_count($html, 'Gespeichert'));
    }

    #[Test]
    public function print_views_stay_without_the_shell(): void
    {
        $event = MixedEvent::factory()->create();

        $html = (string) $this->actingAs($this->admin())->get(route('mixed.scorecard-blank', ['event' => $event->id]))->assertOk()->getContent();

        $this->assertStringNotContainsString('admin-nav', $html);
        $this->assertStringContainsString('sc-page', $html);
    }

    #[Test]
    public function the_header_tournaments_entry_is_current_on_every_tournament_page(): void
    {
        $admin = $this->admin();
        $nav = app(MainNavigation::class);

        foreach (['/draw/record/abcd1234', '/pyramide', '/mixed-tournament', '/tournament'] as $url) {
            $request = Request::create($url);
            $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));

            $bar = $nav->bar($nav->items($admin, $request), $request);
            $tournaments = array_values(array_filter($bar, fn ($item) => in_array($item->key, ['tournaments', 'pyramid', 'mixed', 'championship'], true)));
            $this->assertNotSame([], $tournaments, $url);
            $this->assertTrue($tournaments[0]->active, $url);
        }
    }

    #[Test]
    public function navigation_views_pass_the_ui_linter(): void
    {
        $this->assertSame([], (new UiLinter(resource_path('views/components/layout'), UiRules::all()))->run());
    }
}
