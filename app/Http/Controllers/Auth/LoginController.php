<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\LoginVerificationCode;
use App\Models\LoginHistory;
use App\Models\User;
use App\Services\GeoLookup;
use App\Services\LoginBookkeeping;
use App\Services\LoginRiskEvaluator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

    public function __construct(
        private readonly LoginBookkeeping $bookkeeping,
        private readonly GeoLookup $geoLookup,
        private readonly LoginRiskEvaluator $riskEvaluator,
    ) {}

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
     * On success, either completes the login or (if the attempt looks risky) redirects to
     * the pending email-verification step. Default redirect target: /calendar.
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
        $countryCode = $this->geoLookup->resolveCountry($ip);

        if ($this->isLoginBlockedByCountry($countryCode)) {
            Log::warning('Login blocked by country', [
                'email' => $email,
                'ip' => $ip,
            ]);

            return redirect('/login')->withErrors([
                'email' => __('booking.auth.invalid'),
            ])->withInput($request->except('password'));
        }

        $user = User::where('email', $credentials['email'])->first();
        $throttleKeys = $this->throttleKeys($email, $ip, $user);

        if ($seconds = $this->lockoutSeconds($throttleKeys)) {
            $this->bookkeeping->logAttempt('throttled', $request, $user, [
                'email' => $email,
                'lockout_seconds' => $seconds,
            ]);
            LoginHistory::record($user?->getKey(), $ip, $countryCode, 'throttled');

            return redirect('/login')->withErrors([
                'email' => trans('booking.auth.throttled', ['seconds' => $seconds]),
            ])->withInput($request->except('password'));
        }

        if (! $user || ! $user->isEnabled() || ! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], $request->boolean('remember') && ! $user->isPrivileged())) {
            $this->hitThrottleKeys($throttleKeys);
            $this->bookkeeping->syncLegacyLoginTracking($user, false, $ip, self::LOCKOUT_SECONDS);
            $this->bookkeeping->logAttempt('failed', $request, $user, [
                'email' => $email,
                'disabled_account' => $user?->isEnabled() === false,
            ]);
            LoginHistory::record($user?->getKey(), $ip, $countryCode, 'failed');

            return redirect('/login')->withErrors(['email' => __('booking.auth.invalid')])
                ->withInput($request->except('password'));
        }

        $this->clearThrottleKeys($throttleKeys);

        $wasKnownIp = $this->wasKnownIp($user, $ip);
        $rememberApplied = $request->boolean('remember') && ! $user->isPrivileged();
        $redirectTarget = $this->normalizeRedirectTarget($credentials['redirect_to'] ?? null);
        $riskFlags = $this->riskEvaluator->triggeredFlags($user->getKey(), $countryCode);

        LoginHistory::record($user->getKey(), $ip, $countryCode, 'success', $riskFlags);

        if ($riskFlags !== []) {
            Auth::logout();
            $request->session()->regenerate();

            if (! $this->beginPendingVerification($request, $user, $rememberApplied, $redirectTarget)) {
                return redirect()->route('login.verify.show')->withErrors([
                    'code' => __('booking.auth.verify.send_failed'),
                ]);
            }

            Log::notice('Login requires verification', [
                'user_id' => $user->getKey(),
                'email' => $email,
                'ip' => $ip,
                'risk_flags' => $riskFlags,
            ]);

            return redirect()->route('login.verify.show');
        }

        $request->session()->regenerate();

        $this->bookkeeping->syncLegacyLoginTracking($user, true, $ip, self::LOCKOUT_SECONDS);

        $this->bookkeeping->logAttempt('succeeded', $request, $user, [
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

        return redirect()->to($redirectTarget);
    }

    private function beginPendingVerification(Request $request, User $user, bool $remember, string $redirectTo): bool
    {
        $code = (string) random_int(100000, 999999);

        $request->session()->put('login.pending_verification', [
            'user_id' => $user->getKey(),
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes((int) config('booking.login_verification.code_ttl_minutes'))->toIso8601String(),
            'attempts' => 0,
            'remember' => $remember,
            'redirect_to' => $redirectTo,
        ]);

        try {
            Mail::to($user->email)->send(new LoginVerificationCode($code));
        } catch (\Throwable $e) {
            Log::error('Failed to send login verification code', [
                'user_id' => $user->getKey(),
                'exception' => $e->getMessage(),
            ]);

            return false;
        }

        return true;
    }

    private function isLoginBlockedByCountry(?string $countryCode): bool
    {
        $blockedCountries = config('booking.blocked_login_countries', []);

        if ($blockedCountries === [] || $countryCode === null) {
            return false;
        }

        return in_array($countryCode, $blockedCountries, true);
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
