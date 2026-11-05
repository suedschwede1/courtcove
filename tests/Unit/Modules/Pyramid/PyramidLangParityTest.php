<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Pyramid;

use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PyramidLangParityTest extends TestCase
{
    #[Test]
    public function every_german_file_has_an_english_twin_with_the_same_keys(): void
    {
        $base = app_path('Modules/Pyramid/resources/lang');
        $files = glob($base.'/de/*.php') ?: [];
        $this->assertNotEmpty($files);

        foreach ($files as $de) {
            $en = $base.'/en/'.basename($de);
            $this->assertFileExists($en);

            $deKeys = array_keys(Arr::dot(require $de));
            $enKeys = array_keys(Arr::dot(require $en));
            sort($deKeys);
            sort($enKeys);

            $this->assertSame($deKeys, $enKeys, basename($de));
        }
    }
}
