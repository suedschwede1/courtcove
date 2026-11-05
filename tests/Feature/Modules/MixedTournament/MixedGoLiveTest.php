<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\MixedTournament;

use App\Http\Middleware\StripFourByteCharacters;
use App\Services\TournamentLinks;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\HtaccessRouter;
use Tests\TestCase;

/**
 * Go-live state of module 3: /mixed-tournament is served by Laravel, the header link keeps its URL and the
 * 4-byte stripping still applies (other module paths are still bridged). A rollback (re-adding the prefix to the
 * bridge rule) turns the first test red by design.
 */
class MixedGoLiveTest extends TestCase
{
    #[Test]
    public function mixed_prefix_is_no_longer_bridged(): void
    {
        $router = HtaccessRouter::fromFile(base_path('public/.htaccess'));

        foreach (['mixed-tournament', 'mixed-tournament/admin', 'mixed-tournament/event/1/standings', 'mixed-tournament/round/1/scorecard'] as $path) {
            $this->assertSame('laravel', $router->route($path), $path);
        }

        foreach (['mixed-tournament/', 'mixed-tournament/admin/'] as $path) {
            $this->assertNotSame('zf2', $router->route($path), $path);
        }

        // The still-bridged prefixes keep answering from ZF2.
        $this->assertSame('zf2', $router->route('tournament'));
        $this->assertSame('zf2', $router->route('draw/tokens/1'));
    }

    #[Test]
    public function header_link_still_points_to_the_same_url(): void
    {
        $this->assertSame(url('/mixed-tournament'), app(TournamentLinks::class)->mixedUrl());
    }

    #[Test]
    public function no_mixed_route_is_in_the_stripping_opt_out(): void
    {
        foreach ([...StripFourByteCharacters::EXEMPT_ROUTE_NAMES, ...StripFourByteCharacters::EXEMPT_PATHS] as $pattern) {
            $this->assertStringNotContainsString('mixed', $pattern);
        }
    }
}
