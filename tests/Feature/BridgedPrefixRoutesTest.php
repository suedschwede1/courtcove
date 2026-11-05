<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\HtaccessRouter;
use Tests\TestCase;

class BridgedPrefixRoutesTest extends TestCase
{
    #[Test]
    public function every_laravel_route_under_a_bridged_prefix_is_carved_out(): void
    {
        $router = HtaccessRouter::fromFile(base_path('public/.htaccess'));
        $checked = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();

            if (preg_match('#^(tournament|mixed-tournament|pyramide|draw|service)(/|$)#', $uri) !== 1) {
                continue;
            }

            $path = (string) preg_replace('#\{[^}]+\}#', 'abcd1234', $uri);
            $this->assertSame('laravel', $router->route($path), "$uri ($path) is still answered by the ZF2 bridge");
            $checked[] = $uri;
        }

        $this->assertContains('draw/record/{slug}', $checked);
        $this->assertContains('draw/record/{slug}/json', $checked);
    }
}
