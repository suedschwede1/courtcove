<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\HtaccessRouter;
use Tests\TestCase;

class BridgedPrefixRoutesTest extends TestCase
{
    /**
     * Route URIs whose Laravel routes already exist but which .htaccess still hands to the ZF2 bridge
     * (the switch happens in the module's go-live task). They are not carved out yet, so the check below
     * must see the bridge answer them. The championship and the interactive draw (everything under draw/
     * except the public record): removed at go-live (Task 39).
     */
    private const NOT_YET_LIVE = '#^(tournament(/|$)|draw(/(?!record/)|$))#';

    #[Test]
    public function every_laravel_route_under_a_bridged_prefix_is_carved_out(): void
    {
        $router = HtaccessRouter::fromFile(base_path('public/.htaccess'));
        $checked = [];
        $pending = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();

            if (preg_match('#^(tournament|mixed-tournament|pyramide|draw|service)(/|$)#', $uri, $prefix) !== 1) {
                continue;
            }

            $path = (string) preg_replace('#\{[^}]+\}#', 'abcd1234', $uri);

            if (preg_match(self::NOT_YET_LIVE, $uri) === 1) {
                $this->assertSame('zf2', $router->route($path), "$uri ($path) is marked not yet live but is no longer answered by the ZF2 bridge");
                $pending[] = $uri;

                continue;
            }

            $this->assertSame('laravel', $router->route($path), "$uri ($path) is still answered by the ZF2 bridge");
            $checked[] = $uri;
        }

        $this->assertContains('draw/record/{slug}', $checked);
        $this->assertContains('draw/record/{slug}/json', $checked);
        $this->assertContains('pyramide', $checked);
        $this->assertContains('pyramide/{ladderId}', $checked);
        $this->assertContains('mixed-tournament', $checked);
        $this->assertContains('mixed-tournament/admin/round/{round}/score', $checked);
        $this->assertContains('tournament', $pending);
    }
}
