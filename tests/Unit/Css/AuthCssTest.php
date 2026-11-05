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

    #[Test]
    public function long_club_names_are_contained_on_the_panel(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression('/\.ds-auth__club\s*\{[^}]*-webkit-line-clamp:\s*2/', $css);
        $this->assertMatchesRegularExpression('/\.ds-auth__club\s*\{[^}]*text-overflow:\s*ellipsis/', $css);
    }

    #[Test]
    public function panel_stays_viewport_high_beside_tall_forms(): void
    {
        $this->assertMatchesRegularExpression('/@media \(width >= 56rem\)\s*\{\s*\.ds-auth__panel\s*\{[^}]*position:\s*sticky[^}]*min-height:\s*100vh/', $this->css());
    }

    #[Test]
    public function side_by_side_layout_never_wraps_and_phone_hides_only_the_flagged_intro(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression('/@media \(width >= 56rem\)\s*\{.*?\.ds-auth\s*\{\s*flex-wrap:\s*nowrap/s', $css);
        $this->assertStringContainsString('.ds-auth__intro--desktop', $css);
        $this->assertStringNotContainsString('.ds-auth__head .ds-auth__intro', $css);
    }
}
