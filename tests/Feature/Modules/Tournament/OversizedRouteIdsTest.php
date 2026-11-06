<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Models\User;
use App\Modules\Draw\Models\Draw;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Tournament\Concerns\BuildsChampionship;
use Tests\TestCase;

/**
 * Every tournament.* and draw.* route with a path parameter, called with an oversized id (20 digits; also
 * zero) by a guest, a member and an admin, for each of its verbs: the answer is a redirect, 403 or 404,
 * never a 500 (an `int` controller parameter used to throw a TypeError).
 */
class OversizedRouteIdsTest extends TestCase
{
    use BuildsChampionship;
    use LazilyRefreshDatabase;

    private const OVERSIZED = '99999999999999999999';

    /** @return list<Route> */
    private function routesWithParameters(): array
    {
        $routes = [];
        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();
            // Id parameters: everything the routes constrain to digits (tmid, tpid, did, ...) plus the record slug.
            $ids = array_filter($route->parameterNames(), fn (string $p): bool => ($route->wheres[$p] ?? '') === '[0-9]+' || $p === 'slug');
            if ((str_starts_with($name, 'tournament.') || str_starts_with($name, 'draw.')) && $ids !== []) {
                $routes[] = $route;
            }
        }

        return $routes;
    }

    /** The oversized id where it fits the route constraint, otherwise a value the constraint accepts. */
    private function value(Route $route, string $parameter, string $id): string
    {
        $pattern = $route->wheres[$parameter] ?? null;

        if ($pattern === null || preg_match('/^(?:'.$pattern.')$/', $id) === 1) {
            return $id;
        }

        if (str_contains($pattern, '|')) {
            return explode('|', trim($pattern, '()'))[0];
        }

        return 'A';
    }

    #[Test]
    public function oversized_and_zero_ids_never_answer_500(): void
    {
        $member = User::factory()->create(['status' => 'enabled']);
        $admin = User::factory()->create(['status' => 'admin']);
        $this->championship([1 => $member]);
        Draw::factory()->create(['tid' => $this->tournament->tid]);

        $routes = $this->routesWithParameters();
        $this->assertGreaterThanOrEqual(14, count($routes), 'route enumeration');

        $checked = 0;
        foreach ($routes as $route) {
            foreach ([self::OVERSIZED, '0'] as $id) {
                $uri = '/'.ltrim((string) preg_replace_callback('/\{(\w+)\??\}/', fn (array $m): string => $this->value($route, $m[1], $id), $route->uri()), '/');

                foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                    foreach (['guest' => null, 'member' => $member, 'admin' => $admin] as $who => $user) {
                        $this->app->forgetScopedInstances();
                        $this->app['auth']->forgetGuards();
                        $request = $user === null ? $this : $this->actingAs($user);
                        $response = $request->call($method, $uri);

                        $this->assertContains(
                            $response->getStatusCode(),
                            [301, 302, 403, 404],
                            "{$method} {$uri} as {$who} ({$route->getName()}): ".$response->getStatusCode()
                                .($response->exception ? ' '.$response->exception->getMessage() : ''),
                        );
                        $checked++;
                    }
                }
            }
        }

        $this->assertGreaterThan(80, $checked);
    }

    #[Test]
    public function an_oversized_match_id_is_a_404_for_a_member(): void
    {
        $member = User::factory()->create(['status' => 'enabled']);
        $this->championship([1 => $member]);

        $this->actingAs($member)->get('/tournament/match/'.self::OVERSIZED)->assertNotFound();
        $this->actingAs($member)->post('/tournament/match/'.self::OVERSIZED.'/save')->assertNotFound();
        $this->actingAs($member)->post('/tournament/match/'.self::OVERSIZED.'/confirm')->assertNotFound();
        $this->actingAs($member)->post('/tournament/match/'.self::OVERSIZED.'/delete')->assertNotFound();
        $this->actingAs($member)->get('/tournament/match/'.self::OVERSIZED.'/save')->assertNotFound();
        $this->actingAs($member)->get('/tournament/match/0')->assertNotFound();
    }
}
