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
}
