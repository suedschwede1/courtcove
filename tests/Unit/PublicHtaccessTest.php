<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

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
            ['^(tournament|mixed-tournament|pyramide|draw|service)(/|$)', '^user/(login|logout|password(-reset)?)(/|$)'],
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
            'tournament', 'tournament/standings', 'mixed-tournament', 'mixed-tournament/groups', 'pyramide', 'draw/1',
            'service/help', 'service/info', 'user/login', 'user/logout', 'user/password', 'user/password-reset',
            'user/login/',
        ] as $path) {
            $this->assertTrue($matches($path), "$path must go to ZF2");
        }

        foreach ([
            'calendar', 'square', 'square/booking', 'backend', 'backend/user', 'bookings', 'bookings/create', 'my-bookings',
            'admin', 'login', 'register', 'user/bookings', 'user/settings', 'user/registration', 'user/activation/abc',
            'tournaments', 'drawing', 'services', 'user/loginx', 'user/password-resetx', 'zf2-bridge.php', '',
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
}
