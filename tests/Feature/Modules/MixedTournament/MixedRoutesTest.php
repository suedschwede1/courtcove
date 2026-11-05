<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\MixedTournament;

use App\Http\Middleware\StripFourByteCharacters;
use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Tests\TestCase;

class MixedRoutesTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, array{string, string, string}> */
    public static function routeTable(): array
    {
        $rows = [
            ['GET', '', 'mixed.index'],
            ['GET', '/info', 'mixed.info'],
            ['GET', '/event/12', 'mixed.event'],
            ['GET', '/round/12', 'mixed.round'],
            ['GET', '/round/12/scorecard', 'mixed.scorecard'],
            ['GET', '/event/12/scorecard-blank', 'mixed.scorecard-blank'],
            ['GET', '/event/12/durchmischung', 'mixed.durchmischung'],
            ['GET', '/event/12/standings', 'mixed.standings'],
            ['GET', '/admin', 'mixed.admin.index'],
            ['POST', '/admin/create-event', 'mixed.admin.create-event'],
            ['POST', '/admin/event/12/set-default', 'mixed.admin.set-default'],
            ['POST', '/admin/event/12/set-test-mode', 'mixed.admin.set-test-mode'],
            ['POST', '/admin/event/12/grant', 'mixed.admin.grant'],
            ['POST', '/admin/event/12/revoke', 'mixed.admin.revoke'],
            ['GET', '/admin/import-members', 'mixed.admin.import-members'],
            ['POST', '/admin/import-members', 'mixed.admin.import-members.store'],
            ['POST', '/admin/event/12/set-court-count', 'mixed.admin.set-court-count'],
            ['GET', '/admin/event/12', 'mixed.admin.event'],
            ['GET', '/admin/event/12/config', 'mixed.admin.config'],
            ['GET', '/admin/event/12/members', 'mixed.admin.member-search'],
            ['POST', '/admin/event/12/match-members', 'mixed.admin.match-members'],
            ['POST', '/admin/event/12/pause-all-participants', 'mixed.admin.pause-all'],
            ['POST', '/admin/event/12/add-participant', 'mixed.admin.add-participant'],
            ['POST', '/admin/participant/12/update', 'mixed.admin.update-participant'],
            ['POST', '/admin/participant/12/status', 'mixed.admin.participant-status'],
            ['POST', '/admin/participant/12/remove', 'mixed.admin.remove-participant'],
            ['POST', '/admin/event/12/add-avoid-pair', 'mixed.admin.add-avoid-pair'],
            ['POST', '/admin/event/12/remove-avoid-pair', 'mixed.admin.remove-avoid-pair'],
            ['GET', '/admin/event/12/new-round', 'mixed.admin.new-round'],
            ['POST', '/admin/event/12/create-round', 'mixed.admin.create-round'],
            ['GET', '/admin/round/12', 'mixed.admin.round'],
            ['POST', '/admin/round/12/update-config', 'mixed.admin.update-round-config'],
            ['POST', '/admin/round/12/generate', 'mixed.admin.generate'],
            ['POST', '/admin/round/12/swap', 'mixed.admin.swap'],
            ['POST', '/admin/round/12/swap-sitter', 'mixed.admin.swap-sitter'],
            ['POST', '/admin/round/12/confirm', 'mixed.admin.confirm'],
            ['POST', '/admin/round/12/complete', 'mixed.admin.complete'],
            ['POST', '/admin/round/12/reopen', 'mixed.admin.reopen'],
            ['POST', '/admin/round/12/delete', 'mixed.admin.delete-round'],
            ['POST', '/admin/round/12/score', 'mixed.admin.score'],
            ['GET', '/admin/event/12/durchmischung', 'mixed.admin.durchmischung'],
            ['GET', '/admin/event/12/standings', 'mixed.admin.standings'],
        ];

        $cases = [];
        foreach ($rows as [$method, $path, $name]) {
            $cases[$name] = [$method, 'mixed-tournament'.$path, $name];
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('routeTable')]
    public function every_old_url_resolves_to_its_route(string $method, string $uri, string $name): void
    {
        $route = app('router')->getRoutes()->match(Request::create('/'.$uri, $method));

        $this->assertSame($name, $route->getName());
    }

    #[Test]
    public function the_table_covers_every_registered_mixed_route(): void
    {
        $registered = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with((string) $r->getName(), 'mixed.'))
            ->map(fn ($r) => $r->getName())
            ->sort()->values()->all();
        $tabled = collect(self::routeTable())->map(fn ($c) => $c[2])->sort()->values()->all();

        $this->assertSame($tabled, $registered);
    }

    #[Test]
    public function write_routes_are_post_only(): void
    {
        $this->expectException(MethodNotAllowedHttpException::class);

        app('router')->getRoutes()->match(Request::create('/mixed-tournament/admin/round/12/score', 'GET'));
    }

    #[Test]
    public function non_numeric_ids_do_not_match(): void
    {
        foreach (['/mixed-tournament/event/abc', '/mixed-tournament/admin/round/x/score', '/mixed-tournament/admin/participant/1a/update'] as $uri) {
            $this->actingAs(User::factory()->create())->get($uri)->assertNotFound();
        }
    }

    #[Test]
    public function guests_are_redirected_to_login_with_the_module_text(): void
    {
        $this->get('/mixed-tournament/event/5?x=1')
            ->assertRedirect(route('login', ['redirect_to' => '/mixed-tournament/event/5?x=1']))
            ->assertSessionHas('error', 'Für den Mixed-Turnier-Bereich musst du angemeldet sein.');

        $this->post('/mixed-tournament/admin/create-event')
            ->assertRedirect(route('login', ['redirect_to' => '/mixed-tournament/admin/create-event']));
    }

    #[Test]
    public function expired_csrf_redirects_back_with_a_flash_and_never_to_a_foreign_host(): void
    {
        $text = 'Ungültiger oder abgelaufener Formularschlüssel. Bitte Seite neu laden.';
        $render = function (string $uri, ?string $referer) {
            $request = Request::create($uri, 'POST', server: $referer === null ? [] : ['HTTP_REFERER' => $referer]);
            $request->setLaravelSession($session = app('session.store'));
            $session->start();
            $this->app->instance('request', $request);
            $request->setRouteResolver(fn () => str_starts_with($uri, '/mixed-tournament') ? app('router')->getRoutes()->match($request) : null);

            return [app(ExceptionHandler::class)->render($request, new TokenMismatchException), $session];
        };

        [$response, $session] = $render('/mixed-tournament/admin/create-event', 'http://localhost/mixed-tournament/admin');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('http://localhost/mixed-tournament/admin', $response->headers->get('Location'));
        $this->assertSame($text, $session->get('error'));

        foreach (['http://evil.example.test/x', null] as $referer) {
            [$response, $session] = $render('/mixed-tournament/admin/create-event', $referer);
            $this->assertSame(302, $response->getStatusCode());
            $this->assertSame(route('mixed.index'), $response->headers->get('Location'));
            $this->assertSame($text, $session->get('error'));
        }

        // Another area keeps the framework 419.
        [$response] = $render('/calendar', 'http://localhost/calendar');
        $this->assertNotSame(302, $response->getStatusCode());
    }

    #[Test]
    public function unknown_event_round_and_participant_redirect_with_their_own_flash(): void
    {
        foreach ([
            ['get', '/mixed-tournament/event/999999', 'Event nicht gefunden.'],
            ['get', '/mixed-tournament/round/999999', 'Runde nicht gefunden.'],
            ['post', '/mixed-tournament/admin/participant/999999/update', 'Teilnehmer nicht gefunden.'],
        ] as [$method, $uri, $text]) {
            $this->actingAs(User::factory()->create())
                ->{$method}($uri)
                ->assertRedirect(route('mixed.index'))
                ->assertSessionHas('error', $text);
        }
    }

    #[Test]
    public function global_routes_need_the_global_right(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/mixed-tournament/admin/create-event')
            ->assertForbidden();
    }

    #[Test]
    public function routes_honour_the_booking_base_path(): void
    {
        $this->withServerVariables([
            'SCRIPT_NAME' => '/booking/index.php',
            'SCRIPT_FILENAME' => base_path('public/index.php'),
            'PHP_SELF' => '/booking/index.php',
        ]);

        $this->get('/booking/mixed-tournament');

        $this->assertStringEndsWith('/booking/mixed-tournament/admin/round/5', route('mixed.admin.round', 5));
    }

    #[Test]
    public function mixed_routes_go_through_the_global_stripping_middleware(): void
    {
        $route = Route::getRoutes()->getByName('mixed.admin.add-participant');

        $this->assertNotNull($route);
        $this->assertContains(StripFourByteCharacters::class, app('router')->gatherRouteMiddleware($route));
    }
}
