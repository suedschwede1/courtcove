<?php

namespace Tests\Feature\Calendar;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Between phone and wide desktop the board shows two days instead of scrolling sideways: the board is a size
 * container and drops the third day below the three-day minimum width.
 */
final class CalendarTwoDayLayoutTest extends TestCase
{
    private function css(): string
    {
        return (string) file_get_contents(resource_path('css/calendar-grid.css'));
    }

    #[Test]
    public function the_three_day_minimum_and_the_container_breakpoint_agree(): void
    {
        $css = $this->css();

        preg_match('/\.cal-board \{[^}]*min-width: (\d+)px/s', $css, $min);
        preg_match('/@container cal-board \(width < (\d+)px\)/', $css, $break);

        $this->assertNotEmpty($min, 'three-day min-width');
        $this->assertNotEmpty($break, 'container breakpoint');
        $this->assertSame($min[1], $break[1], 'below the three-day minimum the board must switch to two days');
    }

    #[Test]
    public function below_the_breakpoint_two_columns_remain_and_the_third_day_is_hidden(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression('/\.cal-page__board \{\s*container: cal-board \/ inline-size;/', $css);
        $this->assertMatchesRegularExpression('/@container cal-board \(width < 980px\) \{[^@]*repeat\(2, minmax\(0, 1fr\)\)[^@]*\.cal-day\[data-day="3"\] \{\s*display: none;/s', $css);
        // Phones keep one day: the phone block comes after and resets the board.
        $this->assertGreaterThan(strpos($css, '@container cal-board'), strpos($css, '@media (width < 48rem)'));
    }
}
