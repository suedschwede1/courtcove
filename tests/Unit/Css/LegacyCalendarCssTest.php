<?php

namespace Tests\Unit\Css;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LegacyCalendarCssTest extends TestCase
{
    private function bookingCss(): string
    {
        return (string) file_get_contents(public_path('css/booking.css'));
    }

    #[Test]
    public function booking_css_has_no_calendar_layout_rules(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w-])\.(?:calendar-(?!cell)|booking-grid\b|day-header|square-head|time-(?:cell|main|sub|spacer|side-head)|cal-extra-day|has-calendar-layout|footer-date-row)/',
            $this->bookingCss(),
        );
    }

    #[Test]
    public function booking_css_has_no_calendar_cell_rules(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w-])\.(?:calendar-cell|cc-|slot-cell|event-(?:cell|label)|guest-login-cell)/',
            $this->bookingCss(),
        );
    }

    #[Test]
    public function hook_classes_are_styled_in_the_vite_css(): void
    {
        $css = (string) file_get_contents(resource_path('css/calendar-grid.css'));

        foreach (['.cc-free', '.cc-own', '.cc-single-future', '.cc-series', '.cc-turnier', '.slot-cell--past', '.event-cell--start', '.event-cell--end', '.cal-extra-day'] as $selector) {
            $this->assertStringContainsString($selector, $css, $selector);
        }
    }

    #[Test]
    public function legacy_calendar_nav_rules_are_gone(): void
    {
        $this->assertStringNotContainsString('.ui-calendar-', (string) file_get_contents(resource_path('css/app.css')));
        $this->assertStringNotContainsString('.ds-subbar', (string) file_get_contents(resource_path('css/ds.css')));
    }
}
