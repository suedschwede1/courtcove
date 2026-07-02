<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
    private const EMAIL_IP_MAX_ATTEMPTS = 5;

    private const ACCOUNT_MAX_ATTEMPTS = 8;

    private const IP_MAX_ATTEMPTS = 25;

    private const LOCKOUT_SECONDS = 600;

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

        $email = Str::lower(trim($credentials['email']));
        $ip = (string) $request->ip();
        $user = User::where('email', $credentials['email'])->first();
        $throttleKeys = $this->throttleKeys($email, $ip, $user);

        if ($seconds = $this->lockoutSeconds($throttleKeys)) {
            $this->logAttempt('throttled', $request, $user, [
                'email' => $email,
                'lockout_seconds' => $seconds,
            ]);

            return redirect('/login')->withErrors([
                'email' => trans('booking.auth.throttled', ['seconds' => $seconds]),
            ])->withInput($request->except('password'));
        }

        if (! $user || ! $user->isEnabled() || ! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], $request->boolean('remember') && ! $user->isPrivileged())) {
            $this->hitThrottleKeys($throttleKeys);
            $this->syncLegacyLoginTracking($user, false, $ip);
            $this->logAttempt('failed', $request, $user, [
                'email' => $email,
                'disabled_account' => $user?->isEnabled() === false,
            ]);

            return redirect('/login')->withErrors(['email' => __('booking.auth.invalid')])
                ->withInput($request->except('password'));
        }

        $request->session()->regenerate();
        $this->clearThrottleKeys($throttleKeys);

        $wasKnownIp = $this->wasKnownIp($user, $ip);
        $rememberApplied = $request->boolean('remember') && ! $user->isPrivileged();
        $this->syncLegacyLoginTracking($user, true, $ip);

        $this->logAttempt('succeeded', $request, $user, [
            'email' => $email,
            'known_ip' => $wasKnownIp,
            'privileged_user' => $user->isPrivileged(),
            'remember_requested' => $request->boolean('remember'),
            'remember_applied' => $rememberApplied,
        ]);

        if (! $wasKnownIp) {
            Log::notice('Login from new IP address', [
                'user_id' => $user->getKey(),
                'email' => $email,
                'ip' => $ip,
            ]);
        }

        return redirect()->to($this->normalizeRedirectTarget($credentials['redirect_to'] ?? null));
    }

    /** @return array<string, int> */
    private function throttleKeys(string $email, string $ip, ?User $user): array
    {
        return [
            $this->emailIpThrottleKey($email, $ip) => self::EMAIL_IP_MAX_ATTEMPTS,
            $this->ipThrottleKey($ip) => self::IP_MAX_ATTEMPTS,
            $this->accountThrottleKey($user?->getKey(), $email) => self::ACCOUNT_MAX_ATTEMPTS,
        ];
    }

    private function lockoutSeconds(array $throttleKeys): int
    {
        $seconds = 0;

        foreach ($throttleKeys as $key => $maxAttempts) {
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                $seconds = max($seconds, RateLimiter::availableIn($key));
            }
        }

        return $seconds;
    }

    private function hitThrottleKeys(array $throttleKeys): void
    {
        foreach (array_keys($throttleKeys) as $key) {
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);
        }
    }

    private function clearThrottleKeys(array $throttleKeys): void
    {
        foreach (array_keys($throttleKeys) as $key) {
            RateLimiter::clear($key);
        }
    }

    private function emailIpThrottleKey(string $email, string $ip): string
    {
        return 'login:email-ip:'.Str::transliterate($email).'|'.$ip;
    }

    private function ipThrottleKey(string $ip): string
    {
        return 'login:ip:'.$ip;
    }

    private function accountThrottleKey(mixed $userId, string $email): string
    {
        return 'login:account:'.($userId !== null ? (string) $userId : Str::transliterate($email));
    }

    private function wasKnownIp(User $user, string $ip): bool
    {
        $knownIp = trim((string) $user->last_ip);

        return $knownIp !== '' && hash_equals($knownIp, $ip);
    }

    private function syncLegacyLoginTracking(?User $user, bool $successful, string $ip): void
    {
        if (! $user) {
            return;
        }

        if ($successful) {
            $user->forceFill([
                'login_attempts' => 0,
                'login_detent' => null,
                'last_activity' => now(),
                'last_ip' => $ip,
            ])->save();

            return;
        }

        $attempts = (int) ($user->login_attempts ?? 0) + 1;

        $user->forceFill([
            'login_attempts' => min($attempts, 255),
            'login_detent' => now()->addSeconds(self::LOCKOUT_SECONDS),
            'last_ip' => $ip,
        ])->save();
    }

    private function logAttempt(string $event, Request $request, ?User $user, array $context = []): void
    {
        $payload = array_merge($context, [
            'event' => $event,
            'user_id' => $user?->getKey(),
            'status' => $user?->status,
            'ip' => (string) $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255),
        ]);

        match ($event) {
            'succeeded' => Log::info('Login succeeded', $payload),
            'throttled' => Log::warning('Login throttled', $payload),
            default => Log::warning('Login failed', $payload),
        };
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
