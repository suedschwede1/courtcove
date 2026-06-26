<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * LogoutController — invalidates the session and redirects to login.
 *
 * Routes: POST /logout
 * Auth: auth required
 */
final class LogoutController extends Controller
{
    /**
     * Log out the current user, invalidate the session, and redirect to login.
     *
     * @param Request $request Current HTTP request (needed to invalidate and regenerate the CSRF token)
     * @return RedirectResponse Redirect to /login
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
