<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * LoginController — handles login form display and credential authentication.
 *
 * Routes: GET /login (showForm), POST /login (login)
 * Auth: guest only
 */
final class LoginController extends Controller
{
    public function showForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if ($user?->status === UserStatus::Disabled) {
            return redirect('/login')->withErrors(['email' => 'Konto ist deaktiviert.']);
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended('/calendar');
        }

        return redirect('/login')->withErrors(['email' => 'Ungültige Anmeldedaten.']);
    }
}
