<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

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
    /** Render the login form. */
    public function showForm(Request $request): View
    {
        return view('auth.login', [
            'redirectTo' => $this->normalizeRedirectTarget($request->query('redirect_to')),
        ]);
    }

    /**
     * Validate credentials and authenticate the user.
     *
     * Redirects to /login with errors if the account is disabled or credentials are invalid.
     * On success, regenerates the session and redirects to the intended URL (default: /calendar).
     *
     * @param Request $request Must contain email and password fields
     * @return RedirectResponse
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'redirect_to' => ['nullable', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if ($user && !$user->isEnabled()) {
            return redirect('/login')->withErrors(['email' => 'Konto ist deaktiviert.']);
        }

        if (Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->to($this->normalizeRedirectTarget($credentials['redirect_to'] ?? null));
        }

        return redirect('/login')->withErrors(['email' => 'Ungültige Anmeldedaten.']);
    }

    private function normalizeRedirectTarget(?string $redirectTo): string
    {
        if (!is_string($redirectTo)) {
            return route('calendar.index');
        }

        $target = trim($redirectTo);
        if (str_starts_with($target, '/')
            && !str_starts_with($target, '//')
            && !str_contains($target, '\\')
            && !preg_match('/[[:cntrl:]]/', $target)) {
            return $target;
        }

        return route('calendar.index');
    }
}
