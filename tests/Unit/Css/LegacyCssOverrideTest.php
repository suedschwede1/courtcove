<?php

namespace Tests\Unit\Css;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CssBlocks;
use Tests\TestCase;

final class LegacyCssOverrideTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function aliases(): array
    {
        return [
            'orange' => ['orange', 'clay'], 'orange-hover' => ['orange-hover', 'clay-hover'],
            'orange-soft' => ['orange-soft', 'clay-soft'], 'text' => ['text', 'ink'],
            'text-secondary' => ['text-secondary', 'ink-2'], 'text-muted' => ['text-muted', 'ink-3'],
            'bg' => ['bg', 'ground'], 'surface' => ['surface', 'surface'],
            'border' => ['border', 'line'], 'sidebar' => ['sidebar', 'chrome'],
        ];
    }

    private function app(): string
    {
        return (string) file_get_contents(resource_path('css/app.css'));
    }

    private function booking(): string
    {
        return (string) file_get_contents(public_path('css/booking.css'));
    }

    #[Test]
    #[DataProvider('aliases')]
    public function alias_layer_points_at_tokens(string $old, string $new): void
    {
        $this->assertMatchesRegularExpression('/--ui-'.preg_quote($old, '/').'\s*:\s*var\(--color-'.preg_quote($new, '/').'\)\s*;/', $this->app());
    }

    #[Test]
    public function shadow_alias_is_flat(): void
    {
        $this->assertMatchesRegularExpression('/--ui-shadow\s*:\s*none\s*;/', $this->app());
    }

    #[Test]
    public function app_css_has_hex_only_in_token_blocks(): void
    {
        $css = CssBlocks::without(CssBlocks::without($this->app(), '@theme static'), '[data-theme="dark"]');
        $this->assertDoesNotMatchRegularExpression('/(?<![\w&])#[0-9a-f]{3}(?:[0-9a-f]{3})?\b/i', $css);
    }

    #[Test]
    public function legacy_css_uses_token_fonts_and_base(): void
    {
        $css = $this->booking();
        preg_match_all('/font-family\s*:\s*([^;}]*)/i', $css, $m);
        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $value) {
            $this->assertContains(trim($value), ['var(--font-body)', 'var(--font-display)']);
        }
        $this->assertSame(1, preg_match('/(?:^|\n)body\s*\{([^}]*)\}/', $css, $b));
        $this->assertMatchesRegularExpression('/background\s*:\s*var\(--color-ground\)/', $b[1]);
        $this->assertMatchesRegularExpression('/(?<![-\w])color\s*:\s*var\(--color-ink\)/', $b[1]);
        $this->assertMatchesRegularExpression('/font-family\s*:\s*var\(--font-body\)/', $b[1]);
        $this->assertSame(1, preg_match('/(?:^|\n)a:not\(\[class\*="ds-"\]\)\s*\{([^}]*)\}/', $css, $a));
        $this->assertMatchesRegularExpression('/(?<![-\w])color\s*:\s*var\(--color-clay\)/', $a[1]);
        $this->assertDoesNotMatchRegularExpression('/outline[^;]*!important/', $css);
    }

    #[Test]
    public function legacy_element_rules_never_hit_ds_components(): void
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $this->booking());
        preg_match_all('/(?:^|[;{}])\s*([^;{}@]+)\{/', $css, $m);
        $bare = '/^(?:a|button|input(?:\[[^\]]*\])?|select|textarea|label|table|th|td|tr|h[1-6]|p|ul|ol|li|img|hr)(?::[\w-]+)*$/';
        $offenders = [];
        foreach ($m[1] as $list) {
            foreach (explode(',', $list) as $selector) {
                $selector = trim($selector);
                if (preg_match($bare, $selector)) {
                    $offenders[] = $selector;
                }
            }
        }
        $this->assertSame([], $offenders, 'Bare element selectors in booking.css must carry :not([class*="ds-"]).');
    }

    #[Test]
    public function focus_rule_is_unlayered_and_important(): void
    {
        $css = CssBlocks::withoutLayers($this->app());
        $this->assertMatchesRegularExpression('/(?:^|\n|\})\s*:focus-visible\s*\{[^}]*outline:\s*2px solid var\(--color-ink\) !important;[^}]*outline-offset:\s*2px !important/', $css);
        $this->assertMatchesRegularExpression('/\.ds-on-chrome :focus-visible\s*\{[^}]*outline-color:\s*var\(--color-chrome-ink\) !important/', $css);
    }
}
