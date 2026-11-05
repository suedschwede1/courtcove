<?php

namespace Tests\Unit\Css;

use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

final class BookingCssMigrationTest extends TestCase
{
    private const PREFIXES = ['booking-modal', 'booking-confirm', 'booking-mobile-dialog'];

    #[Test]
    public function migrated_booking_selectors_are_gone_everywhere(): void
    {
        $css = (string) file_get_contents(public_path('css/booking.css'));

        foreach (self::PREFIXES as $prefix) {
            $this->assertStringNotContainsString('.'.$prefix, $css, "booking.css still styles .{$prefix}");

            // Whole-token match: ids such as "admin-booking-modal" and words such as
            // "booking-confirmation" or "booking-confirmed" are not the old classes.
            $pattern = '/(?<![\w-])'.preg_quote($prefix, '/').'(?![a-z])/';
            $hits = [];
            foreach ([resource_path('views'), app_path(), public_path('js')] as $dir) {
                foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                    if ($file->isFile() && preg_match($pattern, (string) file_get_contents($file->getPathname()))) {
                        $hits[] = $file->getPathname();
                    }
                }
            }

            $this->assertSame([], $hits, "Files still reference {$prefix}");
        }
    }
}
