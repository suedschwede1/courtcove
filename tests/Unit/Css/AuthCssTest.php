<?php

namespace Tests\Unit\Css;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AuthCssTest extends TestCase
{
    private function css(): string
    {
        return (string) file_get_contents(resource_path('css/ds-auth.css'));
    }

    #[Test]
    public function every_auth_class_is_defined_in_the_source(): void
    {
        $files = array_merge(
            [resource_path('views/components/layout/auth-shell.blade.php')],
            glob(resource_path('views/auth/*.blade.php')) ?: [],
        );
        $tokens = [];
        foreach ($files as $file) {
            preg_match_all('/\bds-auth[a-z0-9_-]*/', (string) file_get_contents($file), $m);
            $tokens = array_merge($tokens, $m[0]);
        }
        $tokens = array_unique($tokens);
        $this->assertNotEmpty($tokens);

        $css = $this->css();
        foreach ($tokens as $token) {
            $this->assertMatchesRegularExpression('/\.'.preg_quote($token, '/').'(?![\w-])/', $css, $token.' is not defined in ds-auth.css');
        }
    }

    #[Test]
    public function auth_css_is_imported_after_ds_css(): void
    {
        $app = (string) file_get_contents(resource_path('css/app.css'));

        $this->assertNotFalse(strpos($app, "@import './ds-auth.css';"));
        $this->assertGreaterThan(strpos($app, "@import './ds.css';"), strpos($app, "@import './ds-auth.css';"));
    }

    #[Test]
    public function auth_css_uses_tokens_only(): void
    {
        $css = $this->css();

        $this->assertDoesNotMatchRegularExpression('/#[0-9a-f]{3,6}\b/i', $css);
        $this->assertStringNotContainsString('rgb(', $css);
        $this->assertStringNotContainsString('!important', $css);
        $this->assertStringContainsString('@layer components', $css);
    }
}
