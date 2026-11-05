<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Tournament;

use Illuminate\Support\Facades\Lang;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Every literal `tournament::` / `tournament-base::` translation key used in the module code and views exists
 * in German and English (keys built at runtime end with a dot and are checked by their page tests).
 */
class TournamentLangKeysTest extends TestCase
{
    #[Test]
    public function every_used_key_exists_in_de_and_en(): void
    {
        $missing = [];

        foreach (['Modules/Tournament', 'Modules/TournamentBase'] as $dir) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path($dir)));

            foreach ($files as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php' || str_contains($file->getPathname(), '/lang/')) {
                    continue;
                }

                preg_match_all("/'(tournament(?:-base)?::[a-z_]+(?:\.[a-z0-9_]+)*\.?)'/", (string) file_get_contents($file->getPathname()), $m);

                foreach (array_unique($m[1]) as $key) {
                    if (str_ends_with($key, '.') || view()->exists($key)) {
                        continue; // runtime-built key, or a view name
                    }

                    foreach (['de', 'en'] as $locale) {
                        if (! Lang::hasForLocale($key, $locale)) {
                            $missing[] = $locale.': '.$key.' ('.basename($file->getPathname()).')';
                        }
                    }
                }
            }
        }

        $this->assertSame([], $missing);
    }
}
