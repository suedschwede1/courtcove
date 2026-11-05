<?php

namespace Tests\Unit\Css;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CssBlocks;
use Tests\TestCase;

/** The light header paints ink text and a clay marker on the ground token: keep both legible. */
final class HeaderContrastTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function themes(): array
    {
        return ['light' => ['@theme static'], 'dark' => ['[data-theme="dark"]']];
    }

    #[Test]
    #[DataProvider('themes')]
    public function ink_on_ground_is_readable(string $block): void
    {
        $this->assertGreaterThanOrEqual(4.5, $this->ratio($this->token($block, 'ink'), $this->token($block, 'ground')));
        $this->assertGreaterThanOrEqual(4.5, $this->ratio($this->token($block, 'ink-2'), $this->token($block, 'ground')));
    }

    #[Test]
    #[DataProvider('themes')]
    public function clay_marker_on_ground_is_visible(string $block): void
    {
        $this->assertGreaterThanOrEqual(3.0, $this->ratio($this->token($block, 'clay'), $this->token($block, 'ground')));
    }

    private function token(string $opener, string $name): string
    {
        $body = CssBlocks::body((string) file_get_contents(resource_path('css/app.css')), $opener);
        $this->assertMatchesRegularExpression('/--color-'.preg_quote($name, '/').'\s*:\s*(#[0-9a-f]{6})\s*;/i', $body, $name);
        preg_match('/--color-'.preg_quote($name, '/').'\s*:\s*(#[0-9a-f]{6})\s*;/i', $body, $m);

        return $m[1];
    }

    private function ratio(string $a, string $b): float
    {
        [$hi, $lo] = [max($this->luminance($a), $this->luminance($b)), min($this->luminance($a), $this->luminance($b))];

        return ($hi + 0.05) / ($lo + 0.05);
    }

    private function luminance(string $hex): float
    {
        $channels = array_map(function (string $pair): float {
            $c = hexdec($pair) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
