<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

/**
 * Toggles whether the calendar shows the acting admin the admin booking
 * forms or the normal member forms (see CalendarController::index()).
 * Route access is already restricted to admin.booking holders via
 * middleware, so no further permission check happens here.
 */
final class AdminModeController extends Controller
{
    private const COOKIE_MINUTES = 60 * 24 * 365;

    public function set(string $state): RedirectResponse
    {
        return redirect()->back()->withCookie(
            cookie('admin_mode', $state === 'on' ? '1' : '0', self::COOKIE_MINUTES)
        );
    }
}
