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
        $this->assertSame(1, preg_match('/(?:^|\n)\s*a:where\(:not\(\[class\^="ds-"\], \[class\*=" ds-"\]\)\)\s*\{([^}]*)\}/', $css, $a));
        $this->assertMatchesRegularExpression('/(?<![-\w])color\s*:\s*var\(--color-clay\)/', $a[1]);
        $this->assertDoesNotMatchRegularExpression('/outline[^;]*!important/', $css);
    }

    private const GUARD = ':where(:not([class^="ds-"], [class*=" ds-"]))';

    /** @return list<string> unguarded bare element selectors */
    private function unguardedBareSelectors(string $css): array
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        preg_match_all('/(?:^|[;{}])\s*([^;{}@]+)\{/', $css, $m);
        $offenders = [];
        foreach ($m[1] as $list) {
            foreach ($this->splitTopLevel($list) as $selector) {
                $selector = trim($selector);
                $plain = str_replace(self::GUARD, '', $selector);
                $isBare = preg_match('/^(?:\*|[a-z][a-z0-9-]*)(?:\[[^\]]*\])*(?::{1,2}[\w-]+(?:\([^)]*\))?)*$/i', $plain) === 1
                    && ! in_array(strtolower($plain), ['html', 'body'], true);
                if ($isBare && (! str_contains($selector, self::GUARD) || str_contains($plain, ':not('))) {
                    $offenders[] = $selector;
                }
            }
        }

        return $offenders;
    }

    /** @return list<string> */
    private function splitTopLevel(string $list): array
    {
        $parts = [];
        $depth = 0;
        $cur = '';
        foreach (str_split($list) as $ch) {
            $depth += $ch === '(' || $ch === '[' ? 1 : ($ch === ')' || $ch === ']' ? -1 : 0);
            if ($ch === ',' && $depth === 0) {
                $parts[] = $cur;
                $cur = '';
            } else {
                $cur .= $ch;
            }
        }
        $parts[] = $cur;

        return $parts;
    }

    #[Test]
    public function legacy_element_rules_never_hit_ds_components(): void
    {
        $this->assertSame([], $this->unguardedBareSelectors($this->booking()), 'Bare element selectors in booking.css need the zero-specificity :where(:not(ds-)) guard.');
        $this->assertSame([], $this->unguardedBareSelectors(CssBlocks::withoutLayers($this->app())), 'Unlayered bare element selectors in app.css need the guard.');
        $this->assertStringNotContainsString(':not([class*="ds-"])', $this->booking());
    }

    #[Test]
    public function guard_detection_rejects_specificity_adding_guards(): void
    {
        $this->assertNotSame([], $this->unguardedBareSelectors('a:not([class*="ds-"]) { color: red; }'));
        $this->assertNotSame([], $this->unguardedBareSelectors('input::placeholder { color: red; }'));
        $this->assertNotSame([], $this->unguardedBareSelectors('span, select[name="x"][required] { color: red; }'));
        $this->assertSame([], $this->unguardedBareSelectors('.x a { color: red; } a'.self::GUARD.' { color: red; }'));
    }

    #[Test]
    public function legacy_link_rule_sits_in_a_layer_below_the_tailwind_utilities(): void
    {
        $app = $this->app();
        $order = strpos($app, '@layer theme, base, legacy, components, utilities;');
        $this->assertNotFalse($order, 'app.css must declare the layer order with legacy below components and utilities.');
        $this->assertLessThan(strpos($app, "@import 'tailwindcss'"), $order, 'The layer order must be declared before the Tailwind import.');

        $css = $this->booking();
        $layer = CssBlocks::body($css, '@layer legacy');
        $this->assertMatchesRegularExpression('/a:where\(:not\(\[class\^="ds-"\], \[class\*=" ds-"\]\)\)\s*\{[^}]*color\s*:\s*var\(--color-clay\)/', $layer);
        // Unlayered, the rule would beat every Tailwind utility (text-ink, text-white) on plain links.
        $this->assertDoesNotMatchRegularExpression('/(?:^|\})\s*a:where\([^{]*\{[^}]*(?<![-\w])color\s*:/', CssBlocks::withoutLayers($css));
    }

    #[Test]
    public function focus_rule_is_unlayered_and_important(): void
    {
        $css = CssBlocks::withoutLayers($this->app());
        $this->assertMatchesRegularExpression('/(?:^|\n|\})\s*:focus-visible\s*\{[^}]*outline:\s*2px solid var\(--color-ink\) !important;[^}]*outline-offset:\s*2px !important/', $css);
        $this->assertMatchesRegularExpression('/\.ds-on-chrome :focus-visible\s*\{[^}]*outline-color:\s*var\(--color-chrome-ink\) !important/', $css);
    }
}
