<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No test may reach a real endpoint (e.g. the drand beacon); tests fake what they need.
        Http::preventStrayRequests();

        $geoipPath = (string) config('location.maxmind.local.path');

        if ($geoipPath !== '' && ! file_exists($geoipPath)) {
            @mkdir(dirname($geoipPath), 0755, true);
            touch($geoipPath);
        }
    }
}
