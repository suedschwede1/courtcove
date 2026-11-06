<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Draw;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Every literal `draw::` translation key used in the module code and views exists in German and English,
 * and both locales define the same keys. Runtime-built keys are not allowed in this module.
 */
class DrawLangKeysTest extends TestCase
{
    #[Test]
    public function every_used_key_exists_in_de_and_en(): void
    {
        $missing = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Modules/Draw')));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || str_contains($file->getPathname(), '/lang/')) {
                continue;
            }

            preg_match_all("/'(draw::[a-z_]+(?:\.[a-z0-9_]+)*\.?)'/", (string) file_get_contents($file->getPathname()), $m);

            foreach (array_unique($m[1]) as $key) {
                if (view()->exists($key)) {
                    continue;
                }
                $this->assertStringEndsNotWith('.', $key, 'runtime-built key in '.basename($file->getPathname()));

                foreach (['de', 'en'] as $locale) {
                    if (! Lang::hasForLocale($key, $locale)) {
                        $missing[] = $locale.': '.$key.' ('.basename($file->getPathname()).')';
                    }
                }
            }
        }

        $this->assertSame([], $missing);
    }

    #[Test]
    public function de_and_en_define_the_same_keys(): void
    {
        foreach (glob(app_path('Modules/Draw/resources/lang/de/*.php')) ?: [] as $deFile) {
            $enFile = app_path('Modules/Draw/resources/lang/en/'.basename($deFile));
            $this->assertFileExists($enFile);

            $de = array_keys(Arr::dot(require $deFile));
            $en = array_keys(Arr::dot(require $enFile));
            sort($de);
            sort($en);

            $this->assertSame($de, $en, basename($deFile));
        }
    }
}
