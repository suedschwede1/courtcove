<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\HtaccessRouter;
use Tests\TestCase;

/**
 * Every Laravel route under a prefix that was (or still is) handed to the ZF2 bridge must be answered by Laravel.
 * All four tournament modules are live (module 4 go-live), so no exclusion is pending any more. A rollback
 * (re-adding a prefix to the bridge rule) turns this test red by design; it then needs a NOT_YET_LIVE exclusion
 * again (module 4: "#^(tournament(/|$)|draw(/(?!record/)|$))#", asserting that those URIs are answered by ZF2).
 */
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

        foreach ([
            'draw/record/{slug}', 'draw/record/{slug}/json', 'pyramide', 'pyramide/{ladderId}', 'mixed-tournament',
            'mixed-tournament/admin/round/{round}/score', 'tournament', 'tournament/admin', 'tournament/match/{tmid}/save',
            'draw', 'draw/commit', 'draw/tokens/{did}', 'draw/tokens/{did}/pick', 'draw/close/{did}',
        ] as $uri) {
            $this->assertContains($uri, $checked);
        }
    }

    #[Test]
    public function no_pending_exclusions_remain(): void
    {
        $this->assertFalse(defined(self::class.'::NOT_YET_LIVE'), 'no module is waiting for its go-live any more');
    }
}
