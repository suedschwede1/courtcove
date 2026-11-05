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
        $this->assertMatchesRegularExpression('/padding:\s*10px var\(--spacing-page-gutter\) 12px;/', $page[1]);

        preg_match('/\.ds-header__bar\s*\{([^}]*)\}/', (string) file_get_contents(resource_path('css/ds.css')), $bar);
        $this->assertStringNotContainsString('max-width', $bar[1]);
        $this->assertStringContainsString('var(--spacing-page-gutter)', $bar[1]);

        $this->assertMatchesRegularExpression('/--spacing-page-gutter:\s*24px;/', (string) file_get_contents(resource_path('css/app.css')));
    }

    /** The body of the first rule whose selector list is exactly $selector. */
    private function rule(string $css, string $selector): string
    {
        $this->assertMatchesRegularExpression('/(?<![\w.-])'.preg_quote($selector, '/').'\s*\{([^}]*)\}/', $css, $selector);
        preg_match('/(?<![\w.-])'.preg_quote($selector, '/').'\s*\{([^}]*)\}/', $css, $match);

        return $match[1];
    }

    #[Test]
    public function calendar_page_is_exactly_one_screen_high(): void
    {
        // Header plus calendar fill the viewport; the page shell, #content and
        // the calendar page pass the remaining height down to the board.
        $css = $this->toolbar();

        $body = $this->rule($css, 'body:has(.cal-page)');
        $this->assertMatchesRegularExpression('/display:\s*flex;/', $body);
        $this->assertMatchesRegularExpression('/flex-direction:\s*column;/', $body);
        $this->assertMatchesRegularExpression('/height:\s*100dvh;/', $body);

        foreach (['.page-shell:has(.cal-page)', '#content:has(.cal-page)'] as $selector) {
            $rule = $this->rule($css, $selector);
            $this->assertMatchesRegularExpression('/flex:\s*1 1 0;/', $rule, $selector);
            $this->assertMatchesRegularExpression('/min-height:\s*0;/', $rule, $selector);
        }

        // The calendar takes the rest; too low for the minimum rows, the board
        // scrolls (and #content, should an open panel leave too little room).
        $page = $this->rule($css, '.cal-page');
        $this->assertMatchesRegularExpression('/flex:\s*1 0 0;/', $page);
        $this->assertMatchesRegularExpression('/grid-template-rows:\s*auto minmax\(0, 1fr\);/', $page);
        $this->assertMatchesRegularExpression('/overflow-y:\s*auto;/', $this->rule($css, '#content:has(.cal-page)'));
        $board = $this->rule($this->grid(), '.cal-page__board');
        $this->assertMatchesRegularExpression('/overflow:\s*auto;/', $board);
        // The ring padding is cancelled inline, so card edges stay on the gutter (= toolbar edges).
        $this->assertMatchesRegularExpression('/padding:\s*2px;\s*margin-inline:\s*-2px;/', $board);
    }

    #[Test]
    public function hour_rows_share_the_height_with_a_minimum(): void
    {
        $css = $this->grid();
        $rows = 'grid-template-rows: 30px 34px repeat(12, minmax(var(--cal-cell), 1fr));';

        // Day cards and the time column use the same row template, so the hours line up.
        $this->assertStringContainsString($rows, $this->rule($css, '.cal-day'));
        $this->assertStringContainsString($rows, $this->rule($css, '.cal-times'));
        $this->assertMatchesRegularExpression('/height:\s*100%;/', $this->rule($css, '.cal-board'));

        // Cells follow the row instead of a fixed height.
        $cell = $this->rule($css, '.cal-row > .calendar-cell');
        $this->assertMatchesRegularExpression('/height:\s*100%;/', $cell);
        $this->assertStringNotContainsString('var(--cal-cell)', $cell);
        $this->assertDoesNotMatchRegularExpression('/(?<![\w-])height:/', $this->rule($css, '.cal-times__row'));

        // The minimum: 42px on desktop, 44px (tap target) on phones.
        $this->assertMatchesRegularExpression('/--cal-cell:\s*var\(--spacing-cell-h\);/', $this->rule($css, '.cal-board'));
        $this->assertMatchesRegularExpression('/@media \(width < 48rem\)\s*\{.*--cal-cell:\s*var\(--spacing-cell-h-mobile\);/s', $css);
        $tokens = (string) file_get_contents(resource_path('css/app.css'));
        $this->assertMatchesRegularExpression('/--spacing-cell-h:\s*42px;/', $tokens);
        $this->assertMatchesRegularExpression('/--spacing-cell-h-mobile:\s*44px;/', $tokens);
    }

    #[Test]
    public function phone_rows_fill_the_screen_below_the_toolbar(): void
    {
        preg_match('/@media \(width < 48rem\)\s*\{(.*)\}\s*$/s', $this->grid(), $phone);
        $this->assertNotEmpty($phone);

        $this->assertStringContainsString(
            'grid-template-rows: auto repeat(12, minmax(var(--cal-cell), 1fr));',
            $this->rule($phone[1], '.cal-day'),
        );
    }

    #[Test]
    public function multi_hour_events_stay_one_block_at_any_row_height(): void
    {
        // The first and middle hours reach over the gap into the next row by
        // a share of their own (growing) row, not by a fixed cell height.
        $css = $this->grid();
        $join = $this->rule($css, '.cal-row > .event-cell--start,
.cal-row > .event-cell--middle');

        $this->assertStringContainsString('height: calc(100% + var(--cal-gap));', $join);
        $this->assertStringContainsString('margin-bottom: calc(-1 * var(--cal-gap));', $join);
        $this->assertStringNotContainsString('calc(var(--cal-cell) + var(--cal-gap))', $css);
    }

    #[Test]
    public function desktop_toolbar_hides_the_date_heading_and_keeps_the_controls_right(): void
    {
        $css = $this->toolbar();
        preg_match('/@media \(width >= 48rem\)\s*\{(.*?)\n\}/s', $css, $desktop);
        $this->assertNotEmpty($desktop);

        // One row of date controls on the right (then the panel toggles), no wrapping.
        $bar = $this->rule($desktop[1], '.cal-toolbar');
        $this->assertMatchesRegularExpression('/flex-wrap:\s*nowrap;/', $bar);
        $this->assertMatchesRegularExpression('/justify-content:\s*flex-end;/', $bar);
        $this->assertMatchesRegularExpression('/margin-left:\s*auto;/', $this->rule($desktop[1], '.cal-toolbar__nav'));

        // The day cards name the dates: the h1 stays for screen readers only (visually hidden, not display:none).
        $title = $this->rule($desktop[1], '.cal-toolbar__title');
        $this->assertMatchesRegularExpression('/position:\s*absolute;/', $title);
        $this->assertMatchesRegularExpression('/clip:\s*rect\(0 0 0 0\);/', $title);
        $this->assertMatchesRegularExpression('/width:\s*1px;/', $title);
        $this->assertStringNotContainsString('display: none', $title);
        $this->assertMatchesRegularExpression('/"toolbar panels"\s*"board board";/', $css);

        // The legend row is gone from the page grid (it sits in the Infos panel).
        $this->assertStringNotContainsString('legend', $this->rule($css, '.cal-page'));
    }

    #[Test]
    public function today_card_has_a_clay_ring(): void
    {
        $css = $this->grid();

        $this->assertMatchesRegularExpression('/box-shadow:\s*0 0 0 2px var\(--color-clay\);/', $this->rule($css, '.cal-day--today'));
        $this->assertMatchesRegularExpression('/color:\s*var\(--color-clay\);/', $this->rule($css, '.cal-day--today .cal-day__name'));
        $pill = $this->rule($css, '.cal-day__today');
        $this->assertMatchesRegularExpression('/background:\s*var\(--color-clay\);/', $pill);
        $this->assertMatchesRegularExpression('/color:\s*var\(--color-on-clay\);/', $pill);
    }
}
