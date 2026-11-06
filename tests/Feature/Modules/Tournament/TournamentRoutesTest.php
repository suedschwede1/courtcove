<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Models\User;
use App\Modules\Tournament\Models\TournamentMatch;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Feature\Modules\Tournament\Concerns\BuildsChampionship;
use Tests\TestCase;

/**
 * Route matrix of the championship (port of the old routes.php / module.config.php): paths, parameter
 * patterns, names, login and CSRF protection of every `tournament/*` route. The admin routes have their own
 * matrix in TournamentAdminRoutesTest; the login and CSRF checks here cover them too.
 */
class TournamentRoutesTest extends TestCase
{
    use BuildsChampionship;
    use LazilyRefreshDatabase;

    /** @return array<string, array{string, string, string}> */
    public static function routeTable(): array
    {
        return [
            'index' => ['GET', 'tournament', 'tournament.index'],
            'groups' => ['GET', 'tournament/groups', 'tournament.groups'],
            'groups/A' => ['GET', 'tournament/groups/A', 'tournament.groups'],
            'schedule' => ['GET', 'tournament/schedule', 'tournament.schedule'],
            'schedule/open' => ['GET', 'tournament/schedule/open', 'tournament.schedule'],
            'bracket' => ['GET', 'tournament/bracket', 'tournament.bracket'],
            'bracket/KO_A' => ['GET', 'tournament/bracket/KO_A', 'tournament.bracket'],
            'players' => ['GET', 'tournament/players', 'tournament.players'],
            'mine' => ['GET', 'tournament/mine', 'tournament.mine'],
            'modus' => ['GET', 'tournament/modus', 'tournament.mode'],
            'match/1' => ['GET', 'tournament/match/1', 'tournament.match'],
            'match/1/save' => ['POST', 'tournament/match/1/save', 'tournament.match.save'],
            'match/1/confirm' => ['POST', 'tournament/match/1/confirm', 'tournament.match.confirm'],
            'match/1/delete' => ['POST', 'tournament/match/1/delete', 'tournament.match.delete'],
            'GET match/1/save' => ['GET', 'tournament/match/1/save', 'tournament.match.redirect'],
        ];
    }

    #[Test]
    #[DataProvider('routeTable')]
    public function public_routes_and_patterns(string $method, string $uri, string $name): void
    {
        $this->assertSame($name, $this->resolve($uri, $method)->getName());
    }

    /** @return array<string, array{string}> */
    public static function nonMatching(): array
    {
        return [
            'groups/AB' => ['tournament/groups/AB'],
            'groups/1' => ['tournament/groups/1'],
            'groups/a' => ['tournament/groups/a'],
            'schedule/Open' => ['tournament/schedule/Open'],
            'schedule/1' => ['tournament/schedule/1'],
            'bracket/KO-A' => ['tournament/bracket/KO-A'],
            'match/x' => ['tournament/match/x'],
            'match/1x' => ['tournament/match/1x'],
            'match/x/save' => ['tournament/match/x/save'],
            'match/1/other' => ['tournament/match/1/other'],
        ];
    }

    #[Test]
    #[DataProvider('nonMatching')]
    public function invalid_parameters_do_not_match(string $uri): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->resolve($uri);
    }

    #[Test]
    public function the_table_covers_every_named_tournament_route(): void
    {
        $registered = collect(Route::getRoutes()->getRoutes())
            ->map(fn (RoutingRoute $r) => (string) $r->getName())
            ->filter(fn (string $name) => str_starts_with($name, 'tournament.') && ! str_starts_with($name, 'tournament.admin'))
            ->unique()->sort()->values()->all();
        $tabled = collect(self::routeTable())->map(fn (array $row) => $row[2])->unique()->sort()->values()->all();

        $this->assertSame($tabled, $registered);
    }

    /**
     * Every route of the old Tournament and Draw module.config.php (spec commit), keyed by its ZF2 route name, with a
     * sample URL. ZF2 accepted any verb; pages are GET, and the actions that checked isPost() are POST in Laravel,
     * where a GET on them redirects as ZF2 did (second row "<name> GET").
     *
     * @return array<string, array{string, string, string}> method, uri, Laravel route name
     */
    public static function zf2RouteTable(): array
    {
        $rows = [
            'tournament' => ['GET', 'tournament', 'tournament.index'],
            'tournament/groups' => ['GET', 'tournament/groups', 'tournament.groups'],
            'tournament/groups :group' => ['GET', 'tournament/groups/C', 'tournament.groups'],
            'tournament/schedule' => ['GET', 'tournament/schedule', 'tournament.schedule'],
            'tournament/schedule :filter' => ['GET', 'tournament/schedule/offen', 'tournament.schedule'],
            'tournament/bracket' => ['GET', 'tournament/bracket', 'tournament.bracket'],
            'tournament/bracket :phase' => ['GET', 'tournament/bracket/KO_B', 'tournament.bracket'],
            'tournament/players' => ['GET', 'tournament/players', 'tournament.players'],
            'tournament/mine' => ['GET', 'tournament/mine', 'tournament.mine'],
            'tournament/mode' => ['GET', 'tournament/modus', 'tournament.mode'],
            'tournament/match' => ['GET', 'tournament/match/12', 'tournament.match'],
            'tournament/admin' => ['GET', 'tournament/admin', 'tournament.admin'],
            'draw' => ['GET', 'draw', 'draw.index'],
            'draw/tokens' => ['GET', 'draw/tokens/4', 'draw.tokens'],
            'draw/record' => ['GET', 'draw/record/abcd', 'draw.record'],
            'draw/record-json' => ['GET', 'draw/record/abcd_EF-12/json', 'draw.record.json'],
        ];

        $post = [
            'tournament/match-confirm' => ['tournament/match/12/confirm', 'tournament.match.confirm', 'tournament.match.redirect'],
            'tournament/match-save' => ['tournament/match/12/save', 'tournament.match.save', 'tournament.match.redirect'],
            'tournament/match-delete' => ['tournament/match/12/delete', 'tournament.match.delete', 'tournament.match.redirect'],
            'tournament/admin/comment' => ['tournament/admin/comment/7', 'tournament.admin.comment', 'tournament.admin.comment.redirect'],
            'tournament/admin/apply-draw' => ['tournament/admin/apply-draw/3', 'tournament.admin.apply-draw', 'tournament.admin.apply-draw.redirect'],
            'draw/commit' => ['draw/commit', 'draw.commit', 'draw.commit.redirect'],
            'draw/open' => ['draw/open/3', 'draw.open', 'draw.action.redirect'],
            'draw/close' => ['draw/close/3', 'draw.close', 'draw.action.redirect'],
            'draw/delete' => ['draw/delete/3', 'draw.delete', 'draw.action.redirect'],
            'draw/execute' => ['draw/execute/3', 'draw.execute', 'draw.action.redirect'],
            'draw/tokens-pick' => ['draw/tokens/4/pick', 'draw.tokens.pick', 'draw.tokens.pick.redirect'],
        ];

        foreach ([
            'generate' => 'generate', 'lot' => 'lot', 'simulate' => 'simulate', 'prepare-draw' => 'prepare-draw',
            'prepare-bracket-draw' => 'prepare-bracket-draw', 'select-tournament' => 'select', 'clone-test' => 'clone-test',
            'reset' => 'reset', 'refresh-bracket' => 'refresh-bracket', 'create' => 'create', 'activate' => 'activate',
            'partner' => 'partner', 'simulate-results' => 'simulate-results',
        ] as $path => $name) {
            $post['tournament/admin/'.$path] = ['tournament/admin/'.$path, 'tournament.admin.'.$name, 'tournament.admin.redirect'];
        }

        foreach ($post as $zf2 => [$uri, $name, $redirect]) {
            $rows[$zf2] = ['POST', $uri, $name];
            $rows[$zf2.' GET'] = ['GET', $uri, $redirect];
        }

        return $rows;
    }

    #[Test]
    #[DataProvider('zf2RouteTable')]
    public function every_zf2_url_resolves_in_laravel(string $method, string $uri, string $name): void
    {
        $this->assertSame($name, $this->resolve($uri, $method)->getName());
    }

    #[Test]
    public function the_zf2_table_lists_all_37_old_routes(): void
    {
        // 27 Tournament routes (11 pages and match actions, admin index and 15 admin actions) and 10 Draw routes.
        $zf2Names = array_unique(array_map(
            fn (string $key) => explode(' ', $key)[0],
            array_keys(self::zf2RouteTable()),
        ));

        $this->assertCount(37, $zf2Names);
    }

    #[Test]
    public function write_routes_are_post_only_and_a_get_redirects_to_the_match(): void
    {
        foreach (['save', 'confirm', 'delete'] as $action) {
            $route = $this->resolve('tournament/match/1/'.$action);
            $this->assertSame('tournament.match.redirect', $route->getName());
            $this->assertSame('redirectToMatch', $route->getActionMethod());

            try {
                $this->resolve('tournament/match/1/'.$action, 'PUT');
                $this->fail('PUT must not match');
            } catch (MethodNotAllowedHttpException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function routes_honour_the_base_path(): void
    {
        $this->withServerVariables([
            'SCRIPT_NAME' => '/booking/index.php',
            'SCRIPT_FILENAME' => base_path('public/index.php'),
            'PHP_SELF' => '/booking/index.php',
        ]);
        $this->championship();

        // a request under the base path makes the URL generator use it
        $this->actingAs($this->member())->get('/booking/tournament')->assertOk();

        $this->assertSame(url('/tournament/groups/A'), route('tournament.groups', 'A'));
        $this->assertStringEndsWith('/booking/tournament/groups/A', route('tournament.groups', 'A'));
        $this->assertStringEndsWith('/booking/tournament/match/7/save', route('tournament.match.save', ['tmid' => 7]));
        $this->assertStringEndsWith('/booking/tournament/schedule/offen', route('tournament.schedule', ['filter' => 'offen']));
    }

    #[Test]
    public function every_tournament_route_requires_login(): void
    {
        $routes = $this->tournamentRoutes();
        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();
            $login = array_filter($middleware, fn ($m) => is_string($m) && str_starts_with($m, 'tournament-base.login:'));

            $this->assertCount(1, $login, $route->uri());
            $this->assertSame('tournament-base.login:tournament::common.login_required', array_values($login)[0], $route->uri());
            // No plain 'auth' next to it: middleware priority would run it first and drop the module
            // message and the redirect target.
            $this->assertNotContains('auth', $middleware, $route->uri());
            $this->assertContains('web', $middleware, $route->uri());
        }
    }

    #[Test]
    public function guests_are_sent_to_login_on_every_route(): void
    {
        foreach ($this->tournamentRoutes() as $route) {
            $uri = '/'.str_replace(['{group?}', '{filter?}', '{phase?}', '{tmid}', '{action}', '{tpid}', '{did}', '{postAction}'], ['A', 'alle', 'KO_A', '1', 'save', '1', '1', 'create'], $route->uri());
            $method = in_array('POST', $route->methods(), true) ? 'post' : 'get';

            $this->{$method}($uri)
                ->assertRedirect(route('login', ['redirect_to' => $uri]))
                ->assertSessionHas('error', 'Für den Turnierbereich musst du angemeldet sein.');
        }
    }

    #[Test]
    public function a_really_logged_in_user_gets_through(): void
    {
        $user = User::factory()->create(['email' => 'spieler01@example.test', 'pw' => bcrypt('geheim-123'), 'status' => 'enabled']);
        $this->championship([1 => $user]);
        $match = TournamentMatch::query()->where('tid', $this->tournament->tid)
            ->where(fn ($q) => $q->where('tpid_a', $this->tpid[1])->orWhere('tpid_b', $this->tpid[1]))->firstOrFail();

        $this->post('/login', ['email' => 'spieler01@example.test', 'password' => 'geheim-123']);
        $this->assertAuthenticatedAs($user);

        foreach (['/tournament', '/tournament/groups/A', '/tournament/schedule', '/tournament/bracket', '/tournament/players', '/tournament/mine', '/tournament/modus', '/tournament/match/'.$match->tmid] as $uri) {
            $this->get($uri)->assertOk();
        }

        $this->post('/tournament/match/'.$match->tmid.'/save', ['date' => '2027-05-01', 'time' => '18:00'])
            ->assertRedirect(route('tournament.match', ['tmid' => $match->tmid]));
        $this->assertSame('2027-05-01', $match->fresh()->played);
    }

    #[Test]
    public function every_post_route_is_csrf_protected(): void
    {
        $web = app('router')->getMiddlewareGroups()['web'];
        $csrf = array_values(array_filter($web, fn ($m) => is_string($m) && class_exists($m) && is_a($m, PreventRequestForgery::class, true)));
        $this->assertNotEmpty($csrf, 'the web group verifies CSRF tokens');

        $excluded = app($csrf[0])->getExcludedPaths();
        $posts = array_filter($this->tournamentRoutes(), fn (RoutingRoute $r) => in_array('POST', $r->methods(), true));
        $this->assertNotEmpty($posts);

        foreach ($posts as $route) {
            $this->assertContains('web', $route->gatherMiddleware(), $route->uri());
            $this->assertNotContains(PreventRequestForgery::class, $route->excludedMiddleware(), $route->uri());
            foreach ($excluded as $pattern) {
                $this->assertFalse(Request::create('/'.$route->uri(), 'POST')->is($pattern === '/' ? $pattern : trim((string) $pattern, '/')), $route->uri().' is excluded by '.$pattern);
            }
        }

        // Behaviour: with CSRF verification active, a post without a token is refused (flash + redirect).
        $user = User::factory()->create(['status' => 'enabled']);
        $this->championship([1 => $user]);
        $match = TournamentMatch::query()->where('tid', $this->tournament->tid)->where('tpid_a', $this->tpid[1])->firstOrFail();
        $this->withMiddleware();
        $this->app['env'] = 'local'; // CSRF verification is skipped while the env is "testing"

        $this->actingAs($user)->from(route('tournament.match', ['tmid' => $match->tmid]))
            ->post(route('tournament.match.save', ['tmid' => $match->tmid]), ['date' => '2027-05-01', 'time' => '18:00'])
            ->assertRedirect(route('tournament.match', ['tmid' => $match->tmid]))
            ->assertSessionHas('error', 'Ungültiger oder abgelaufener Formularschlüssel. Bitte Seite neu laden.');
        $this->assertNull($match->fresh()->played);
    }

    private function resolve(string $uri, string $method = 'GET'): RoutingRoute
    {
        return app('router')->getRoutes()->match(Request::create('/'.$uri, $method));
    }

    /** @return list<RoutingRoute> */
    private function tournamentRoutes(): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (RoutingRoute $r) => preg_match('#^tournament(/|$)#', $r->uri()) === 1,
        ));
    }
}
