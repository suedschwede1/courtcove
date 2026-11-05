<?php

namespace Tests\Unit\Css;

use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CalendarCssTest extends TestCase
{
    private function grid(): string
    {
        return (string) file_get_contents(resource_path('css/calendar-grid.css'));
    }

    private function toolbar(): string
    {
        return (string) file_get_contents(resource_path('css/calendar-toolbar.css'));
    }

    #[Test]
    public function calendar_css_uses_tokens_only(): void
    {
        foreach (['calendar-grid.css' => $this->grid(), 'calendar-toolbar.css' => $this->toolbar()] as $name => $css) {
            $this->assertDoesNotMatchRegularExpression('/(?<![\w&])#(?:[0-9a-f]{6}|[0-9a-f]{3})\b/i', $css, $name);
            $this->assertStringNotContainsString('Red Hat', $css, $name);
            $this->assertStringNotContainsString('!important', $css, $name);
        }
    }

    #[Test]
    public function cell_sizes_come_from_tokens(): void
    {
        $css = $this->grid();

        $this->assertStringContainsString('var(--spacing-cell-h)', $css);
        $this->assertStringContainsString('var(--spacing-cell-h-mobile)', $css);
        $this->assertStringContainsString('@media (width < 48rem)', $css);
        $this->assertStringContainsString('grid-auto-flow: column', $css);
    }

    #[Test]
    public function motion_respects_reduced_motion(): void
    {
        $this->assertStringContainsString('prefers-reduced-motion', $this->grid());
    }

    #[Test]
    public function calendar_page_drops_the_layout_inset(): void
    {
        // The layout's page-shell padding and #content margin would inset the
        // calendar page; it brings its own page padding (and is full-bleed on phones).
        $css = $this->toolbar();

        // Only the inline inset goes, and the header gets it back, so it sits where
        // it does on every other page (booking.css .page-shell: 8px 12px 16px).
        $this->assertMatchesRegularExpression('/\.page-shell:has\(\.cal-page\)\s*\{[^}]*padding-inline:\s*0;/', $css);
        $this->assertDoesNotMatchRegularExpression('/\.page-shell:has\(\.cal-page\)\s*\{[^}]*padding:/', $css);
        $this->assertMatchesRegularExpression('/\.page-shell:has\(\.cal-page\) > \.ds-header\s*\{[^}]*margin-inline:\s*12px;/', $css);
        $this->assertMatchesRegularExpression('/#content:has\(\.cal-page\)\s*\{[^}]*margin-top:\s*0;/', $css);
        $this->assertStringNotContainsString(':has(> .cal-page)', $css);
    }

    #[Test]
    public function past_cells_are_hatched(): void
    {
        $this->assertStringContainsString(
            'repeating-linear-gradient(135deg, var(--cal-past-bg) 0 4px, var(--cal-past-bg-alt) 4px 8px)',
            $this->grid(),
        );
    }

    #[Test]
    public function free_cells_have_a_dashed_border(): void
    {
        $this->assertStringContainsString('border: 1px dashed var(--cal-free-line)', $this->grid());
    }

    #[Test]
    public function every_exported_cal_variable_is_used(): void
    {
        $css = $this->grid().$this->toolbar();
        $keys = array_keys(Arr::dot((array) config('calendar.colors')));
        $this->assertNotEmpty($keys);

        foreach ($keys as $key) {
            $variable = '--cal-'.str_replace(['_', '.'], '-', $key);
            $this->assertStringContainsString('var('.$variable.')', $css, $variable);
        }
    }
}
