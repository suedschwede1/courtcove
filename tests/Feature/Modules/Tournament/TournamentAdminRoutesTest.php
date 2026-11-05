<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Tournament\Concerns\BuildsChampionship;
use Tests\TestCase;

/**
 * Route matrix of the championship admin (old module.config.php child routes of tournament/admin): paths,
 * names, the admin gate after the module login, and the GET redirects of the POST-only actions.
 */
class TournamentAdminRoutesTest extends TestCase
{
    use BuildsChampionship;
    use LazilyRefreshDatabase;

    /** @return array<string, array{string, string, string}> */
    public static function routeTable(): array
    {
        return [
            'admin' => ['GET', 'tournament/admin', 'tournament.admin'],
        ];
    }

    #[Test]
    #[DataProvider('routeTable')]
    public function admin_routes_and_patterns(string $method, string $uri, string $name): void
    {
        $this->assertSame($name, app('router')->getRoutes()->match(Request::create('/'.$uri, $method))->getName());
    }

    #[Test]
    public function the_table_covers_every_admin_route(): void
    {
        $registered = collect(Route::getRoutes()->getRoutes())
            ->map(fn (RoutingRoute $r) => (string) $r->getName())
            ->filter(fn (string $name) => $name === 'tournament.admin' || str_starts_with($name, 'tournament.admin.'))
            ->unique()->sort()->values()->all();
        $tabled = collect(self::routeTable())->map(fn (array $row) => $row[2])->unique()->sort()->values()->all();

        $this->assertSame($tabled, $registered);
    }

    #[Test]
    public function every_admin_route_is_gated_after_the_login(): void
    {
        foreach ($this->adminRoutes() as $route) {
            $middleware = $route->gatherMiddleware();
            $login = array_search('tournament-base.login:tournament::common.login_required', $middleware, true);
            $gate = array_search('can:admin.tournament', $middleware, true);

            $this->assertNotFalse($login, $route->uri());
            $this->assertNotFalse($gate, $route->uri());
            $this->assertLessThan($gate, $login, $route->uri());
        }
    }

    #[Test]
    public function members_get_403_on_every_admin_route_before_any_id_is_resolved(): void
    {
        $this->championship();
        $member = $this->member();

        foreach ($this->adminRoutes() as $route) {
            $uri = '/'.str_replace(['{tpid}', '{did}'], ['999999', '999999'], $route->uri());
            $method = in_array('POST', $route->methods(), true) ? 'post' : 'get';

            $this->actingAs($member)->{$method}($uri, ['tid' => '999999', 'tpid_a' => '999999'])->assertForbidden();
        }

        $assist = User::factory()->status('assist')->create();
        $assist->setMeta('allow.admin.draw', 'true');
        $this->actingAs($assist->fresh())->get(route('tournament.admin'))->assertForbidden();
    }

    /** @return list<RoutingRoute> */
    private function adminRoutes(): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (RoutingRoute $r) => preg_match('#^tournament/admin(/|$)#', $r->uri()) === 1,
        ));
    }
}
