<?php

namespace Tests\Unit\Css;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BuildFreshnessTest extends TestCase
{
    #[Test]
    public function every_component_class_is_built(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
        $css = (string) file_get_contents(public_path('build/'.$manifest['resources/css/app.css']['file']));

        $files = array_merge(
            glob(resource_path('views/components/ui/*.blade.php')) ?: [],
            glob(resource_path('views/components/layout/*.blade.php')) ?: [],
            [resource_path('views/components/calendar/modals.blade.php')],
            glob(resource_path('views/bookings/*.blade.php')) ?: [],
            [resource_path('views/layouts/app.blade.php')],
        );

        $missing = [];
        foreach ($files as $file) {
            $src = (string) file_get_contents($file);
            $strings = [];
            preg_match_all('/class="([^"]*)"/', $src, $m1);
            preg_match_all('/->class\(([^)]*)\)/', $src, $m2);
            preg_match_all('/\'(ds-[a-z0-9_-]+)\'/', $src, $m3);
            array_push($strings, ...$m1[1], ...$m2[1], ...$m3[1]);

            foreach ($strings as $s) {
                preg_match_all('/\bds-[a-z0-9_-]+/', $s, $t);
                foreach ($t[0] as $token) {
                    if (! preg_match('/\.'.preg_quote($token, '/').'(?![\w-])/', $css)) {
                        $missing[$token] = basename($file);
                    }
                }
            }
        }

        $this->assertSame([], $missing, 'Classes missing in the built CSS; run npm run build.');
    }
}
