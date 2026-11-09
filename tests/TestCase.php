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

        // Tests that sign in a privileged account through the real form are about something else than the
        // trusted-device check; the check is covered by TrustedDeviceTest, which switches it back on.
        config(['booking.security.trusted_device_scope' => 'off']);

        // The /api address restriction is covered by ApiIpAllowlistTest; every other test calls from the test client address.
        config(['booking.api.allowed_ips' => ['127.0.0.1']]);

        $geoipPath = (string) config('location.maxmind.local.path');

        if ($geoipPath !== '' && ! file_exists($geoipPath)) {
            @mkdir(dirname($geoipPath), 0755, true);
            touch($geoipPath);
        }
    }
}
