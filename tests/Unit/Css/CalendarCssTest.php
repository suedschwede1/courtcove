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

        // Only the inline inset goes. The header sits outside the shell (full width),
        // so it needs no inset of its own here.
        $this->assertMatchesRegularExpression('/\.page-shell:has\(\.cal-page\)\s*\{[^}]*padding-inline:\s*0;/', $css);
        $this->assertDoesNotMatchRegularExpression('/\.page-shell:has\(\.cal-page\)\s*\{[^}]*padding:/', $css);
        $this->assertStringNotContainsString('.ds-header', $css);
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
    public function past_bookings_keep_the_hatch_on_hover_and_without_a_series_stripe(): void
    {
        $css = $this->grid();

        // Hover/focus and series rules outrank the past rule, so they must skip past slots.
        $this->assertDoesNotMatchRegularExpression('/\.cal-row > a\.cc-(?:own|single-future):(?:hover|focus-visible)/', $css);
        foreach (['own', 'single-future'] as $state) {
            $this->assertStringContainsString('.cal-row > a.cc-'.$state.':not(.slot-cell--past):hover', $css);
            $this->assertStringContainsString('.cal-row > a.cc-'.$state.':not(.slot-cell--past):focus-visible', $css);
        }
        $this->assertDoesNotMatchRegularExpression('/\.cal-row > \.cc-(?:own\.cc-)?series\s*[{,]/', $css);
        $this->assertStringContainsString('.cal-row > .cc-series:not(.slot-cell--past)', $css);
        $this->assertStringContainsString('.cal-row > .cc-own.cc-series:not(.slot-cell--past)', $css);
    }

    #[Test]
    public function event_links_draw_their_focus_ring_inside_the_cell(): void
    {
        // Event cells clip their overflow, so the global outside ring would be cut off.
        $this->assertMatchesRegularExpression(
            '/\.cal-row \.event-label:focus-visible\s*\{[^}]*outline-offset:\s*-2px/',
            (string) file_get_contents(resource_path('css/app.css')),
        );
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

    #[Test]
    public function calendar_page_shares_the_header_gutter(): void
    {
        // Header content and calendar share one frame: full width, same side gutter,
        // so their left and right edges line up at every width.
        preg_match('/(?<![\w-])\.cal-page\s*\{([^}]*)\}/', $this->toolbar(), $page);
        $this->assertStringNotContainsString('max-width', $page[1]);
        $this->assertMatchesRegularExpression('/padding:\s*16px var\(--spacing-page-gutter\) 32px;/', $page[1]);

        preg_match('/\.ds-header__bar\s*\{([^}]*)\}/', (string) file_get_contents(resource_path('css/ds.css')), $bar);
        $this->assertStringNotContainsString('max-width', $bar[1]);
        $this->assertStringContainsString('var(--spacing-page-gutter)', $bar[1]);

        $this->assertMatchesRegularExpression('/--spacing-page-gutter:\s*24px;/', (string) file_get_contents(resource_path('css/app.css')));
    }
}
