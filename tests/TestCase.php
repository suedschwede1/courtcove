<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithAdminMode;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithAdminMode;

    protected function setUp(): void
    {
        parent::setUp();

        // No test may reach a real endpoint (e.g. the drand beacon); tests fake what they need.
        Http::preventStrayRequests();

        // Requests run in admin mode unless a test calls withoutAdminMode() (see InteractsWithAdminMode).
        $this->withAdminMode();

        $geoipPath = (string) config('location.maxmind.local.path');

        if ($geoipPath !== '' && ! file_exists($geoipPath)) {
            @mkdir(dirname($geoipPath), 0755, true);
            touch($geoipPath);
        }
    }
}
