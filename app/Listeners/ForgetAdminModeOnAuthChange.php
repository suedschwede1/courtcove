<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Support\AdminMode;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Cookie;

/**
 * Deletes the admin mode cookie on every login and logout, so the next account on the same
 * browser never starts in admin mode (and old long-lived cookies from before 2026-11-06 die
 * with the next login).
 */
final class ForgetAdminModeOnAuthChange
{
    public function handle(Login|Logout $event): void
    {
        Cookie::queue(AdminMode::forgetCookie());
    }
}
