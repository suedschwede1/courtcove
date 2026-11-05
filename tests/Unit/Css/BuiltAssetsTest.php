<?php

namespace Tests\Unit\Css;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BuiltAssetsTest extends TestCase
{
    private function builtCss(): string
    {
        $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true, flags: JSON_THROW_ON_ERROR);

        return (string) file_get_contents(public_path('build/'.$manifest['resources/css/app.css']['file']));
    }

    #[Test]
    public function fonts_are_self_hosted_in_the_build(): void
    {
        $css = $this->builtCss();
        $this->assertStringNotContainsString('fonts.googleapis', $css);
        foreach (['barlow-latin-400-normal', 'barlow-latin-500-normal', 'barlow-latin-600-normal', 'barlow-latin-700-normal',
            'barlow-latin-ext-400-normal', 'barlow-condensed-latin-600-normal', 'barlow-condensed-latin-700-normal',
            'barlow-condensed-latin-ext-700-normal'] as $file) {
            $this->assertMatchesRegularExpression('/'.preg_quote($file, '/').'-[\w-]+\.woff2/', $css, $file);
            $this->assertNotEmpty(glob(public_path('build/assets/'.$file.'-*.woff2')), $file.' missing in public/build/assets');
        }
    }

    #[Test]
    public function css_asset_urls_are_relative(): void
    {
        $this->assertDoesNotMatchRegularExpression('#url\(\s*["\']?/#', $this->builtCss());
    }

    #[Test]
    public function every_theme_token_survives_the_build(): void
    {
        $css = $this->builtCss();
        foreach (DesignTokensTest::colorTokens() as [$name]) {
            $this->assertStringContainsString('--color-'.$name.':', $css, $name);
        }
        foreach (['--font-display:', '--font-body:', '--shadow-sheet:', '--spacing-cell-h-mobile:', '--radius-pill:'] as $p) {
            $this->assertStringContainsString($p, $css);
        }
    }
}
