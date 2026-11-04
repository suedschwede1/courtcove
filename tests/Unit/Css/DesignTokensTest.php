<?php

namespace Tests\Unit\Css;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CssBlocks;
use Tests\TestCase;

final class DesignTokensTest extends TestCase
{
    /** @return array<string, array{string, string, string}> name, light, dark */
    public static function colorTokens(): array
    {
        return [
            'ground' => ['ground', '#F5F1EA', '#171513'], 'surface' => ['surface', '#FFFFFF', '#211E1B'],
            'surface-sunk' => ['surface-sunk', '#FAF8F4', '#1B1917'], 'sand' => ['sand', '#EFE9DF', '#2C2824'],
            'line' => ['line', '#E3DCD0', '#3A3631'], 'line-soft' => ['line-soft', '#F1ECE3', '#2C2824'],
            'line-strong' => ['line-strong', '#BDB2A2', '#6A6259'], 'ink' => ['ink', '#1D1B18', '#F3EEE6'],
            'ink-2' => ['ink-2', '#4A443D', '#CFC6B8'], 'ink-3' => ['ink-3', '#6B6359', '#A79D8F'],
            'chrome' => ['chrome', '#1D1B18', '#0F0E0D'], 'chrome-ink' => ['chrome-ink', '#E7E0D5', '#E7E0D5'],
            'chrome-muted' => ['chrome-muted', '#CFC6B8', '#A79D8F'], 'chrome-line' => ['chrome-line', '#3A3631', '#2C2824'],
            'clay' => ['clay', '#B5441A', '#E07A4F'], 'clay-hover' => ['clay-hover', '#8F3412', '#F0936C'],
            'on-clay' => ['on-clay', '#FFFFFF', '#171513'], 'clay-soft' => ['clay-soft', '#FBF1EB', '#3A2419'],
            'court' => ['court', '#1F5A44', '#5FAE87'], 'on-court' => ['on-court', '#FFFFFF', '#171513'],
            'court-soft' => ['court-soft', '#E3F0E8', '#1E3329'], 'court-ink' => ['court-ink', '#1F5A44', '#8FD0AE'],
            'warn-soft' => ['warn-soft', '#FBEFD5', '#3A2E12'], 'warn-ink' => ['warn-ink', '#7A4E00', '#F0C46B'],
            'danger' => ['danger', '#9A2B17', '#F08A72'], 'danger-soft' => ['danger-soft', '#F8E1DC', '#3D1C15'],
            'cell-free-line' => ['cell-free-line', '#CFC5B5', '#4A443D'], 'cell-booked' => ['cell-booked', '#E6DFD3', '#34302B'],
            'cell-past-a' => ['cell-past-a', '#EDE8DF', '#1F1C19'], 'cell-past-b' => ['cell-past-b', '#F7F4EE', '#252220'],
        ];
    }

    /** @return array<string, array{string, string}> property, value */
    public static function otherTokens(): array
    {
        return [
            'font-display' => ['--font-display', '"Barlow Condensed", "Arial Narrow", sans-serif'],
            'font-body' => ['--font-body', 'Barlow, system-ui, sans-serif'],
            'font-mono' => ['--font-mono', 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace'],
            'radius-sm' => ['--radius-sm', '6px'], 'radius-md' => ['--radius-md', '8px'], 'radius-lg' => ['--radius-lg', '12px'],
            'radius-xl' => ['--radius-xl', '18px'], 'radius-pill' => ['--radius-pill', '999px'],
            'touch-min' => ['--spacing-touch-min', '44px'], 'control-h' => ['--spacing-control-h', '46px'],
            'control-h-mobile' => ['--spacing-control-h-mobile', '52px'], 'cell-h' => ['--spacing-cell-h', '36px'],
            'cell-h-mobile' => ['--spacing-cell-h-mobile', '48px'],
            'shadow-dialog' => ['--shadow-dialog', '0 20px 50px rgba(0, 0, 0, 0.25)'],
            'shadow-sheet' => ['--shadow-sheet', '0 -10px 30px rgba(0, 0, 0, 0.2)'],
            'text-display-xl' => ['--text-display-xl', '40px'], 'text-display-l' => ['--text-display-l', '32px'],
            'text-display-m' => ['--text-display-m', '24px'], 'text-display-s' => ['--text-display-s', '20px'],
            'text-numeral' => ['--text-numeral', '40px'], 'text-body-l' => ['--text-body-l', '17px'],
            'text-body' => ['--text-body', '15px'], 'text-body-strong' => ['--text-body-strong', '15px'],
            'text-body-s' => ['--text-body-s', '13px'], 'text-label' => ['--text-label', '12px'],
            'text-button' => ['--text-button', '15px'],
        ];
    }

    #[Test]
    #[DataProvider('colorTokens')]
    public function color_token_has_light_and_dark_value(string $name, string $light, string $dark): void
    {
        $this->assertDeclares($this->block('@theme static'), '--color-'.$name, $light);
        $this->assertDeclares($this->block('[data-theme="dark"]'), '--color-'.$name, $dark);
    }

    #[Test]
    #[DataProvider('otherTokens')]
    public function other_token_is_declared_in_theme(string $property, string $value): void
    {
        $this->assertDeclares($this->block('@theme static'), $property, $value);
    }

    #[Test]
    public function retired_tokens_are_gone(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));
        $this->assertStringNotContainsString('--color-accent-', $css);
        $this->assertStringNotContainsString('Red Hat', $css);
        $this->assertStringContainsString('src:hm-b26c8c89', $css); // watermark stays
    }

    private function block(string $opener): string
    {
        $body = CssBlocks::body((string) file_get_contents(resource_path('css/app.css')), $opener);
        if ($body === '') {
            $this->fail("Block '{$opener}' not found in app.css");
        }

        return $body;
    }

    private function assertDeclares(string $block, string $prop, string $value): void
    {
        $this->assertMatchesRegularExpression(
            '/'.preg_quote($prop, '/').'\s*:\s*'.preg_quote($value, '/').'\s*;/i',
            $block,
            "{$prop}: {$value} not declared"
        );
    }
}
