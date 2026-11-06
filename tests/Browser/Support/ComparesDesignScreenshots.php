<?php

declare(strict_types=1);

namespace Tests\Browser\Support;

use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert;

/**
 * Pixel comparison against reviewed references in tests/Browser/baselines.
 *
 * Locally a missing reference skips the test (references are created on purpose
 * with UPDATE_DUSK_SNAPSHOTS=1 and reviewed before committing). In CI a missing
 * reference or a missing gd extension fails: a skipped comparison is not a
 * successful design check, and CI never replaces references.
 */
trait ComparesDesignScreenshots
{
    private function assertScreenshotMatchesBaseline(Browser $browser, string $name, float $allowedMismatchRatio = 0.002): void
    {
        $inCi = filter_var(env('CI', false), FILTER_VALIDATE_BOOL);
        $update = filter_var(getenv('UPDATE_DUSK_SNAPSHOTS') ?: env('UPDATE_DUSK_SNAPSHOTS', false), FILTER_VALIDATE_BOOL);
        $baseline = base_path('tests/Browser/baselines/'.$name.'.png');

        if (! $update) {
            if (! is_file($baseline)) {
                Assert::assertFalse($inCi, 'Missing reviewed baseline: '.$name);
                $this->markTestSkipped("Missing visual baseline tests/Browser/baselines/{$name}.png. See tests/Browser/README.md to create and approve it.");
            }
            if (! extension_loaded('gd')) {
                Assert::assertFalse($inCi, 'Install PHP gd: a skipped comparison is not a successful design check.');
                $this->markTestSkipped('The PHP gd extension is required for visual screenshot comparison. See tests/Browser/README.md.');
            }
        }

        // Wait for web fonts and images so rasterization is stable, then drop focus rings and smooth scrolling.
        $browser->waitUsing(10, 100, fn (): bool => (bool) ($browser->script("return document.fonts.status === 'loaded' && Array.from(document.images).every(i => i.complete);")[0] ?? false));
        $browser->script("document.activeElement?.blur(); document.documentElement.style.scrollBehavior = 'auto';");
        $browser->pause(200)->screenshot($name);
        $actual = base_path('tests/Browser/screenshots/'.$name.'.png');

        if ($update) {
            Assert::assertFalse($inCi, 'CI must compare references, never replace them.');
            if (! is_dir(dirname($baseline))) {
                mkdir(dirname($baseline), 0755, true);
            }
            Assert::assertTrue(copy($actual, $baseline));

            return;
        }

        [$width, $height] = getimagesize($actual) ?: [0, 0];
        [$baseWidth, $baseHeight] = getimagesize($baseline) ?: [0, 0];
        Assert::assertSame([$baseWidth, $baseHeight], [$width, $height], 'Screenshot dimensions changed: '.$name);

        $actualImage = imagecreatefrompng($actual);
        $baselineImage = imagecreatefrompng($baseline);
        Assert::assertNotFalse($actualImage, 'Could not read actual screenshot: '.$name);
        Assert::assertNotFalse($baselineImage, 'Could not read baseline screenshot: '.$name);

        $changed = 0;
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $a = imagecolorat($actualImage, $x, $y);
                $b = imagecolorat($baselineImage, $x, $y);
                $distance = abs((($a >> 16) & 255) - (($b >> 16) & 255))
                    + abs((($a >> 8) & 255) - (($b >> 8) & 255)) + abs(($a & 255) - ($b & 255));
                $changed += $distance > 24 ? 1 : 0;
            }
        }
        imagedestroy($actualImage);
        imagedestroy($baselineImage);

        $ratio = $width * $height > 0 ? $changed / ($width * $height) : 1.0;
        Assert::assertLessThanOrEqual(
            $allowedMismatchRatio,
            $ratio,
            sprintf('Visual regression for %s: %.3f%% changed pixels.', $name, $ratio * 100),
        );
    }
}
