<?php

declare(strict_types=1);

namespace Tests\Browser\Support;

use App\Support\AdminMode;
use Laravel\Dusk\Browser;

/**
 * Dusk browser that starts every loginAs() session in admin mode, like the feature tests
 * (Tests\Concerns\InteractsWithAdminMode). The cookie has no effect for accounts without a
 * privilege. Call withoutAdminMode() to test the switched-off state.
 */
final class AdminModeBrowser extends Browser
{
    public function loginAs($userId, $guard = null)
    {
        parent::loginAs($userId, $guard);

        return $this->plainCookie(AdminMode::COOKIE, '1');
    }

    public function withoutAdminMode(): static
    {
        $this->deleteCookie(AdminMode::COOKIE);

        return $this;
    }
}
