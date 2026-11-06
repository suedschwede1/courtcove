<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\TournamentBase;

use App\Http\Middleware\StripFourByteCharacters;
use App\Modules\TournamentBase\Routing\ModuleRoutes;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModuleRoutesTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function module_routes_use_the_web_group(): void
    {
        $this->assertSame(['web'], ModuleRoutes::middleware());
    }

    #[Test]
    public function global_stripping_middleware_exists(): void
    {
        $this->assertTrue(class_exists(StripFourByteCharacters::class));
    }

    #[Test]
    public function draw_routes_go_through_the_global_stripping_middleware(): void
    {
        $route = Route::getRoutes()->getByName('draw.record.json');
        $this->assertNotNull($route);
        $this->assertContains(StripFourByteCharacters::class, app('router')->gatherRouteMiddleware($route));

        $captured = null;
        Event::listen(RequestHandled::class, function (RequestHandled $event) use (&$captured): void {
            $captured = $event->request->query('q');
        });
        config(['tournament-base.require_login' => false]); // the public record JSON
        $this->get('draw/record/zzzz9999/json?q=Ball%20%F0%9F%8E%BE%20ok');

        $this->assertSame('Ball  ok', $captured);
    }
}
