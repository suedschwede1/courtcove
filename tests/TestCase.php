<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $geoipPath = (string) config('location.maxmind.local.path');

        if ($geoipPath !== '' && ! file_exists($geoipPath)) {
            @mkdir(dirname($geoipPath), 0755, true);
            touch($geoipPath);
        }
    }
}
