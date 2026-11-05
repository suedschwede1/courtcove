<?php

namespace Tests\Unit\Css;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LegacyCalendarCssTest extends TestCase
{
    #[Test]
    public function booking_css_has_no_calendar_layout_rules(): void
    {
        $css = (string) file_get_contents(public_path('css/booking.css'));

        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w-])\.(?:calendar-(?!cell)|booking-grid\b|day-header|square-head|time-(?:cell|main|sub|spacer|side-head)|cal-extra-day|has-calendar-layout|footer-date-row)/',
            $css,
        );
    }

    #[Test]
    public function legacy_calendar_nav_rules_are_gone(): void
    {
        $this->assertStringNotContainsString('.ui-calendar-', (string) file_get_contents(resource_path('css/app.css')));
        $this->assertStringNotContainsString('.ds-subbar', (string) file_get_contents(resource_path('css/ds.css')));
    }
}
