<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\LoginVerificationCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The e-mail code step of a sign-in (risk flags, new device, password reset): creates and re-issues the pending
 * state in the session ('login.pending_verification') and sends the code. All throttling lives here and is
 * server-side per account, never only in the session: a new code is always possible, limited only by waiting
 * times (pause between mails, hourly caps), never by a permanent lock.
 */
final class LoginVerificationService
{
    public const SENT = 'sent';

    public const MAIL_FAILED = 'mail_failed';

    /** Result when sending is throttled; the wait in seconds comes from waitSeconds(). */
    public const PAUSE = 'pause';

    public const CAP = 'cap';

    /**
     * Start the code step for a user whose password was just verified (or reset). Returns SENT, MAIL_FAILED
     * (the pending state exists, the user can ask for a new code) or CAP (hourly cap, no pending state).
     *
     * @param  array<int, string>  $riskFlags
     */
    public function begin(Request $request, User $user, bool $remember, string $redirectTo, string $ip, ?string $countryCode, array $riskFlags, bool $trustDevice): string
    {
        $blocked = $this->reserveSend($user, $ip, enforcePause: false);

        if ($blocked !== null) {
            return $blocked;
        }

        $code = (string) random_int(100000, 999999);

        $request->session()->put('login.pending_verification', [
            'user_id' => $user->getKey(),
            'ip' => $ip,
            'country_code' => $countryCode,
            'risk_flags' => $riskFlags,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes((int) config('booking.login_verification.code_ttl_minutes'))->toIso8601String(),
            'issued_at' => microtime(true),
            'remember' => $remember,
            'redirect_to' => $redirectTo,
            'trust_device' => $trustDevice,
        ]);
        RateLimiter::clear($this->failKey($user));

        return $this->sendCode($user, $code, $trustDevice) ? self::SENT : self::MAIL_FAILED;
    }

    /**
     * A fresh code for the pending state: always possible, the old code stops working and the attempt counter
     * restarts. Returns SENT, MAIL_FAILED, PAUSE or CAP.
     *
     * @param  array<string, mixed>  $pending
     */
    public function resend(Request $request, User $user, array $pending): string
    {
        $blocked = $this->reserveSend($user, (string) ($pending['ip'] ?? $request->ip()), enforcePause: true);

        if ($blocked !== null) {
            return $blocked;
        }

        $code = (string) random_int(100000, 999999);

        $pending['code_hash'] = Hash::make($code);
        $pending['expires_at'] = now()->addMinutes((int) config('booking.login_verification.code_ttl_minutes'))->toIso8601String();
        $pending['issued_at'] = microtime(true);
        $request->session()->put('login.pending_verification', $pending);
        RateLimiter::clear($this->failKey($user));

        return $this->sendCode($user, $code, ($pending['trust_device'] ?? false) === true) ? self::SENT : self::MAIL_FAILED;
    }

    /** Server-side failure counter of the code check for this account (cleared when a new code is issued). */
    public function failKey(User $user): string
    {
        return 'login-verify-fail:'.$user->getKey();
    }

    /** Seconds until the next code may be requested (after PAUSE or CAP). */
    public function waitSeconds(User $user, string $ip): int
    {
        return max(
            1,
            RateLimiter::availableIn($this->pauseKey($user)),
            ...array_map(
                fn (string $key): int => RateLimiter::tooManyAttempts($key, $this->capFor($key)) ? RateLimiter::availableIn($key) : 0,
                [$this->ipSendKey($user, $ip), $this->totalSendKey($user)],
            ),
        );
    }

    /** Codes issued before this moment are void (set when the account's trust is reset). */
    public function voidPendingCodes(User|int $user): void
    {
        $id = $user instanceof User ? (int) $user->getKey() : $user;

        Cache::forever($this->epochKey($id), microtime(true));
        RateLimiter::clear('login-verify-fail:'.$id);
    }

    /** After a password reset by mail link (proof of the mailbox): sending waits and caps start fresh. */
    public function resetThrottles(User $user, string $ip): void
    {
        foreach ([$this->ipSendKey($user, $ip), $this->totalSendKey($user), $this->pauseKey($user), $this->failKey($user)] as $key) {
            RateLimiter::clear($key);
        }
    }

    /** @param array<string, mixed> $pending */
    public function isVoided(array $pending): bool
    {
        $epoch = Cache::get($this->epochKey((int) ($pending['user_id'] ?? 0)));

        return $epoch !== null && (float) ($pending['issued_at'] ?? 0) < (float) $epoch;
    }

    /** Returns null when a code may be sent now (and counts it), else CAP or PAUSE. */
    private function reserveSend(User $user, string $ip, bool $enforcePause): ?string
    {
        $keys = [$this->ipSendKey($user, $ip), $this->totalSendKey($user)];

        foreach ($keys as $key) {
            if (RateLimiter::tooManyAttempts($key, $this->capFor($key))) {
                return self::CAP;
            }
        }

        if ($enforcePause && RateLimiter::tooManyAttempts($this->pauseKey($user), 1)) {
            return self::PAUSE;
        }

        foreach ($keys as $key) {
            RateLimiter::hit($key, 3600);
        }
        RateLimiter::hit($this->pauseKey($user), (int) config('booking.login_verification.resend_decay_seconds'));

        return null;
    }

    private function capFor(string $key): int
    {
        return str_contains($key, '|')
            ? (int) config('booking.login_verification.max_sends_per_hour')
            : (int) config('booking.login_verification.max_sends_per_hour_total');
    }

    private function ipSendKey(User $user, string $ip): string
    {
        return 'login-verify-send:'.$user->getKey().'|'.$ip;
    }

    private function totalSendKey(User $user): string
    {
        return 'login-verify-send:'.$user->getKey();
    }

    private function pauseKey(User $user): string
    {
        return 'login-verify-pause:'.$user->getKey();
    }

    private function epochKey(int $userId): string
    {
        return 'login-verify-epoch:'.$userId;
    }

    private function sendCode(User $user, string $code, bool $newDevice): bool
    {
        try {
            Mail::to($user->email)->send(new LoginVerificationCode($code, $newDevice));
        } catch (\Throwable $e) {
            Log::error('Failed to send login verification code', [
                'user_id' => $user->getKey(),
                'exception' => $e->getMessage(),
            ]);

            return false;
        }

        return true;
    }
}
