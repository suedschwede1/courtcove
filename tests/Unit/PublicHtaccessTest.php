<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\HtaccessRouter;

class PublicHtaccessTest extends TestCase
{
    public function test_public_htaccess_exists_and_routes_to_front_controller(): void
    {
        $path = dirname(__DIR__, 2).'/public/.htaccess';

        $this->assertFileExists($path, 'public/.htaccess must be shipped, otherwise Apache has no front controller.');

        $contents = file_get_contents($path);
        $this->assertStringContainsString('RewriteRule ^ index.php [L]', $contents);
        $this->assertStringContainsString('RewriteCond %{REQUEST_FILENAME} !-f', $contents);
        $this->assertStringContainsString('HTTP_AUTHORIZATION', $contents);
    }

    public function test_public_htaccess_is_not_git_ignored_or_export_ignored(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (['.gitignore', '.gitattributes'] as $file) {
            foreach (file($root.'/'.$file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $this->assertDoesNotMatchRegularExpression('#htaccess#', $line, "$file must not exclude .htaccess");
            }
        }
    }

    /**
     * @return list<string> the pattern of every rule that targets the ZF2 bridge, in file order
     */
    private function bridgePatterns(string $contents): array
    {
        preg_match_all('#^\s*RewriteRule\s+(\S+)\s+zf2-bridge\.php\s+\[L\]\s*$#m', $contents, $m);

        return $m[1];
    }

    public function test_htaccess_routes_exactly_the_tournament_allowlist_to_the_zf2_bridge(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/public/.htaccess');

        $this->assertSame(
            ['^(tournament|mixed-tournament|draw|service)(/|$)', '^user/(login|logout|password(-reset)?)(/|$)'],
            $this->bridgePatterns($contents),
        );
    }

    public function test_allowlist_matches_tournament_paths_and_never_booking_paths(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/public/.htaccess');
        $patterns = $this->bridgePatterns($contents);
        $matches = fn (string $path): bool => array_filter(
            $patterns,
            fn (string $p): bool => preg_match('#'.str_replace('#', '\#', $p).'#', $path) === 1,
        ) !== [];

        foreach ([
            'tournament', 'tournament/standings', 'mixed-tournament', 'mixed-tournament/groups', 'draw/1',
            'service/help', 'service/info', 'user/login', 'user/logout', 'user/password', 'user/password-reset',
            'user/login/',
        ] as $path) {
            $this->assertTrue($matches($path), "$path must go to ZF2");
        }

        foreach ([
            'calendar', 'square', 'square/booking', 'backend', 'backend/user', 'bookings', 'bookings/create', 'my-bookings',
            'admin', 'login', 'register', 'user/bookings', 'user/settings', 'user/registration', 'user/activation/abc',
            'pyramide', 'pyramide/5', 'pyramide/admin', 'tournaments', 'drawing', 'services', 'user/loginx', 'user/password-resetx', 'zf2-bridge.php', '',
        ] as $path) {
            $this->assertFalse($matches($path), "$path must stay with Laravel");
        }
    }

    public function test_bridge_rules_come_before_the_front_controller_rewrite(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/public/.htaccess');

        $engine = strpos($contents, 'RewriteEngine On');
        $bridge = strpos($contents, 'zf2-bridge.php');
        $auth = strpos($contents, 'HTTP_AUTHORIZATION');
        $front = strpos($contents, 'RewriteRule ^ index.php [L]');

        $this->assertNotFalse($engine);
        $this->assertNotFalse($bridge);
        $this->assertGreaterThan($engine, $bridge);
        $this->assertLessThan($auth, $bridge, 'bridge rules go before the Authorization-header block');
        $this->assertLessThan($front, $bridge);
        $this->assertSame(strrpos($contents, 'zf2-bridge.php'), strrpos(substr($contents, 0, $front), 'zf2-bridge.php'));
    }

    public function test_trailing_slash_on_allowlisted_paths_is_redirected_before_the_bridge(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/public/.htaccess');

        $this->assertSame(1, preg_match('#^\s*RewriteRule\s+(\S+)\s+%1\s+\[L,R=301\]\s*$#m', $contents, $m));
        $this->assertLessThan(
            strpos($contents, 'RewriteRule ^(tournament|mixed-tournament|draw|service)(/|$) zf2-bridge.php'),
            strpos($contents, $m[0]),
        );
        $this->assertStringContainsString('RewriteCond %{REQUEST_URI} (.+)/$
'.$m[0], $contents);

        $matches = static fn (string $path): bool => preg_match('#'.str_replace('#', '\#', $m[1]).'#', $path) === 1;

        foreach (['tournament/', 'mixed-tournament/', 'draw/1/', 'service/help/', 'user/login/', 'user/password-reset/'] as $path) {
            $this->assertTrue($matches($path), "$path/ must be redirected to the slashless URL");
        }

        foreach (['tournament', 'pyramide/', 'calendar/', 'login/', 'bookings/', 'user/bookings/', 'square/', 'admin/', 'tournaments/', 'user/loginx/'] as $path) {
            $this->assertFalse($matches($path), "$path must not be touched");
        }
    }

    public function test_raw_request_line_guard_blocks_the_bridge_before_any_rewrite(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/public/.htaccess');

        $needle = 'RewriteCond %{THE_REQUEST} zf2-bridge [NC]
    RewriteRule ^ - [R=404,L]';
        $this->assertStringContainsString($needle, $contents);

        $guard = strpos($contents, $needle);
        $this->assertLessThan(strpos($contents, 'zf2-bridge.php [L]'), $guard);
        $this->assertLessThan(strpos($contents, '[L,R=301]'), $guard);

        $this->assertSame(1, preg_match('#zf2-bridge#i', 'GET //zf2-bridge.php/backend HTTP/1.1'));
        $this->assertSame(1, preg_match('#zf2-bridge#i', 'GET /booking/ZF2-Bridge.php/x HTTP/1.1'));
        $this->assertSame(0, preg_match('#zf2-bridge#i', 'GET /mixed-tournament?x=1 HTTP/1.1'));
        $this->assertSame(0, preg_match('#zf2-bridge#i', 'GET /mixed-tournament HTTP/1.1'));
    }

    public function test_draw_record_is_served_by_laravel_and_the_rest_of_draw_stays_bridged(): void
    {
        $r = HtaccessRouter::fromFile(dirname(__DIR__, 2).'/public/.htaccess');

        foreach (['draw/record', 'draw/record/x', 'draw/record/x/json', 'draw/record/0123456789ab', 'draw/record/0123456789ab/json'] as $p) {
            $this->assertSame('laravel', $r->route($p), $p);
        }

        foreach (['draw', 'draw/1', 'draw/tokens/1', 'draw/tokens/1/pick', 'draw/commit', 'draw/open/3', 'draw/close/3', 'draw/execute/3', 'draw/delete/3', 'draw/recordx', 'draw/records/x'] as $p) {
            $this->assertSame('zf2', $r->route($p), $p);
        }

        foreach (['draw/record/x/', 'draw/record/x/json/'] as $p) {
            $this->assertSame('redirect', $r->route($p), $p);
        }

        foreach (['calendar', 'members/dashboard', 'drawing'] as $p) {
            $this->assertSame('laravel', $r->route($p), $p);
        }
    }

    public function test_pyramid_is_served_by_laravel(): void
    {
        $r = HtaccessRouter::fromFile(dirname(__DIR__, 2).'/public/.htaccess');

        foreach (['pyramide', 'pyramide/5', 'pyramide/regeln', 'pyramide/forderung/7/ergebnis', 'pyramide/admin/ladder/3/mitglieder'] as $p) {
            $this->assertSame('laravel', $r->route($p), $p);
        }

        foreach (['pyramide/', 'pyramide/admin/'] as $p) {
            $this->assertSame('redirect', $r->route($p), $p);
        }

        foreach (['tournament', 'mixed-tournament/groups', 'draw', 'draw/tokens/1', 'service/help', 'user/login'] as $p) {
            $this->assertSame('zf2', $r->route($p), $p);
        }

        $this->assertSame('laravel', $r->route('draw/record/x/json'));
    }

    public function test_router_models_the_generic_trailing_slash_rule(): void
    {
        $r = HtaccessRouter::fromFile(dirname(__DIR__, 2).'/public/.htaccess');

        $this->assertSame('laravel', $r->route('calendar'));
        $this->assertSame('redirect', $r->route('calendar/'));
        $this->assertSame('laravel', $r->route(''));
    }

    public function test_router_reports_real_directories_and_files_as_static_not_as_redirect(): void
    {
        $r = HtaccessRouter::fromFile(dirname(__DIR__, 2).'/public/.htaccess');

        // public/css is a real directory: the generic slash rule has !-d and must not fire for it.
        $this->assertSame('static', $r->route('css/'));
        $this->assertSame('static', $r->route('css'));
        $this->assertSame('static', $r->route('index.php'));
        // A non-existing path with a trailing slash still redirects.
        $this->assertSame('redirect', $r->route('calendar/'));
        $this->assertSame('redirect', $r->route('css-nope/'));
    }

    public function test_router_refuses_conditions_with_flags(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'htaccess');

        foreach (['[OR]', '[NC]', '[NC,OR]'] as $flag) {
            file_put_contents($file, 'RewriteCond %{REQUEST_URI} (.+)/$ '.$flag."\nRewriteRule ^ %1 [L,R=301]\n");

            try {
                HtaccessRouter::fromFile($file);
                $this->fail('flags '.$flag.' must make the router throw');
            } catch (\LogicException $e) {
                $this->assertStringContainsString('flags', $e->getMessage());
            }
        }

        file_put_contents($file, "RewriteCond %{REQUEST_URI} (.+)/$\nRewriteRule ^ %1 [L,R=301]\n");
        $this->assertSame('redirect', HtaccessRouter::fromFile($file)->route('x/'));

        unlink($file);
    }

    public function test_carve_outs_sit_between_the_slash_redirect_and_the_bridge(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/public/.htaccess');

        $redirect = strpos($contents, '%1 [L,R=301]');
        $carve = strpos($contents, 'RewriteRule ^draw/record(/|$) index.php [L]');
        $bridge = strpos($contents, 'zf2-bridge.php [L]');

        $this->assertNotFalse($redirect);
        $this->assertNotFalse($carve);
        $this->assertLessThan($carve, $redirect);
        $this->assertLessThan($bridge, $carve);
    }
}
