<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every tracked German translation file must have an English twin with the same
 * set of keys (and vice versa), so a missing key never silently falls back to German.
 */
class LangKeyParityTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function langFileProvider(): array
    {
        $base = dirname(__DIR__, 2);
        $files = glob($base.'/lang/de/booking/*.php') ?: [];
        $files = array_filter($files, static function (string $file): bool {
            $name = basename($file);

            return ! str_ends_with($name, '.example.php')
                && $name !== 'local.php'
                && ! str_starts_with($name, 'quote_groups')
                && ! str_starts_with($name, 'quotes');
        });
        $files = array_merge($files, glob($base.'/app/Modules/*/resources/lang/de/*.php') ?: []);

        $cases = [];
        foreach ($files as $de) {
            $de = str_replace('\\', '/', $de);
            $label = ltrim(substr($de, strlen(str_replace('\\', '/', $base))), '/');
            $cases[$label] = [$de, preg_replace('#/lang/de/#', '/lang/en/', $de, 1)];
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('langFileProvider')]
    public function de_and_en_files_have_the_same_keys(string $de, string $en): void
    {
        $this->assertFileExists($en, 'missing en counterpart');

        $deKeys = self::flatten(require $de);
        $enKeys = self::flatten(require $en);

        $this->assertSame([], array_values(array_diff($deKeys, $enKeys)), 'missing in en');
        $this->assertSame([], array_values(array_diff($enKeys, $deKeys)), 'missing in de');
    }

    /**
     * @param  array<array-key, mixed>  $array
     * @return list<string>
     */
    private static function flatten(array $array, string $prefix = ''): array
    {
        $keys = [];
        foreach ($array as $key => $value) {
            $path = $prefix.$key;
            if (is_array($value) && ! array_is_list($value)) {
                array_push($keys, ...self::flatten($value, $path.'.'));
            } else {
                $keys[] = $path;
            }
        }

        return $keys;
    }
}
