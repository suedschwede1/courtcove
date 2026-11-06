<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\User;
use App\Support\AdminMode;

/**
 * Admin mode in feature tests.
 *
 * Every test request carries the cookie `admin_mode=1` by default (set in TestCase::setUp()), so the
 * many existing tests of admin features keep running in admin mode; the cookie has no effect for
 * accounts without a privilege. Tests about the mode itself call withoutAdminMode() (or send their
 * own `admin_mode` cookie value). actingAsAdminMode() makes the intent explicit in new tests.
 */
trait InteractsWithAdminMode
{
    protected function withAdminMode(): static
    {
        // withCredentials(): JSON test requests send cookies too, like a same-origin fetch() in the browser.
        return $this->withCredentials()->withUnencryptedCookie(AdminMode::COOKIE, '1');
    }

    protected function withoutAdminMode(): static
    {
        unset($this->unencryptedCookies[AdminMode::COOKIE], $this->defaultCookies[AdminMode::COOKIE]);

        return $this;
    }

    protected function actingAsAdminMode(User $user): static
    {
        return $this->actingAs($user)->withAdminMode();
    }

    protected function actingAsWithoutAdminMode(User $user): static
    {
        return $this->actingAs($user)->withoutAdminMode();
    }
}
