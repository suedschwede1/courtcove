<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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
     * @param  Request  $request  Must contain email and password fields
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'redirect_to' => ['nullable', 'string'],
        ]);

        $throttleKey = $this->throttleKey($request->string('email')->value(), (string) $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return redirect('/login')->withErrors([
                'email' => trans('booking.auth.throttled', ['seconds' => $seconds]),
            ]);
        }

        $user = User::where('email', $credentials['email'])->first();

        if ($user && ! $user->isEnabled()) {
            RateLimiter::hit($throttleKey, 600);

            return redirect('/login')->withErrors(['email' => __('booking.auth.disabled')]);
        }

        if (Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], $request->boolean('remember'))) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            return redirect()->to($this->normalizeRedirectTarget($credentials['redirect_to'] ?? null));
        }

        RateLimiter::hit($throttleKey, 600);

        return redirect('/login')->withErrors(['email' => __('booking.auth.invalid')]);
    }


    private function throttleKey(string $email, string $ip): string
    {
        return Str::transliterate(Str::lower(trim($email))).'|'.$ip;
    }

    private function normalizeRedirectTarget(?string $redirectTo): string
    {
        if (! is_string($redirectTo)) {
            return route('calendar.index');
        }

        $target = trim($redirectTo);
        if (str_starts_with($target, '/')
            && ! str_starts_with($target, '//')
            && ! str_contains($target, '\\')
            && ! preg_match('/[[:cntrl:]]/', $target)) {
            return $target;
        }

        return route('calendar.index');
    }
}
