<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Http\Middleware\StripFourByteCharacters;
use App\Modules\Tournament\Models\Tournament;
use App\Services\TournamentLinks;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\HtaccessRouter;
use Tests\TestCase;

/**
 * Go-live state of module 4: /tournament and all of /draw are served by Laravel, the header link keeps its URL
 * and the 4-byte stripping still applies (the ZF2 service and login paths are still bridged). A rollback
 * (re-adding "tournament|draw|" to the bridge rule) turns the first test red by design.
 */
class TournamentGoLiveTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function tournament_and_draw_prefixes_are_no_longer_bridged(): void
    {
        $router = HtaccessRouter::fromFile(base_path('public/.htaccess'));

        foreach ([
            'tournament', 'tournament/groups/A', 'tournament/match/1', 'tournament/match/1/save', 'tournament/admin',
            'tournament/admin/apply-draw/3', 'draw', 'draw/commit', 'draw/close/3', 'draw/tokens/1', 'draw/tokens/1/pick',
            'draw/record/abcd1234', 'draw/record/abcd1234/json',
        ] as $path) {
            $this->assertSame('laravel', $router->route($path), $path);
        }

        foreach (['tournament/', 'tournament/admin/', 'draw/', 'draw/tokens/1/'] as $path) {
            $this->assertNotSame('zf2', $router->route($path), $path);
        }

        // Only the ZF2 service pages and ZF2's own login paths remain on the bridge.
        foreach (['service/help', 'user/login', 'user/logout', 'user/password', 'user/password-reset'] as $path) {
            $this->assertSame('zf2', $router->route($path), $path);
        }
    }

    #[Test]
    public function header_link_still_points_to_the_same_url(): void
    {
        Tournament::factory()->create(['title' => 'Demo-Meisterschaft', 'is_test' => 0, 'status' => 'setup', 'active' => 1]);

        $link = app(TournamentLinks::class)->currentChampionship();

        $this->assertNotNull($link);
        $this->assertSame(url('/tournament'), $link['url']);
        $this->assertSame(route('tournament.index'), $link['url']);
        $this->assertSame(url('/draw'), route('draw.index'));
    }

    #[Test]
    public function no_tournament_or_draw_route_is_in_the_stripping_opt_out(): void
    {
        foreach ([...StripFourByteCharacters::EXEMPT_ROUTE_NAMES, ...StripFourByteCharacters::EXEMPT_PATHS] as $pattern) {
            $this->assertStringNotContainsString('tournament', $pattern);
            $this->assertStringNotContainsString('draw', $pattern);
        }
    }
}
