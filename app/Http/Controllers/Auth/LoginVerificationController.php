<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\LoginVerificationCode;
use App\Models\LoginHistory;
use App\Models\User;
use App\Services\LoginBookkeeping;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * LoginVerificationController — the code-entry step for logins that
 * LoginController flagged as risky (see App\Services\LoginRiskEvaluator).
 * The pending state (hashed code, expiry, attempts, remember/redirect_to)
 * lives entirely in the session under 'login.pending_verification'.
 */
final class LoginVerificationController extends Controller
{
    public function __construct(
        private readonly LoginBookkeeping $bookkeeping,
    ) {}

    public function show(Request $request): RedirectResponse|View
    {
        if (! is_array($request->session()->get('login.pending_verification'))) {
            return redirect('/login');
        }

        return view('auth.login-verify');
    }

    public function verify(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('login.pending_verification');

        if (! is_array($pending)) {
            return redirect('/login');
        }

        $data = $request->validate(['code' => ['required', 'string']]);

        if (Carbon::parse($pending['expires_at'])->isPast()) {
            $request->session()->forget('login.pending_verification');

            return redirect('/login')->withErrors(['code' => __('booking.auth.verify.expired')]);
        }

        if (! Hash::check($data['code'], $pending['code_hash'])) {
            $pending['attempts']++;

            if ($pending['attempts'] >= (int) config('booking.login_verification.max_attempts')) {
                $request->session()->forget('login.pending_verification');

                return redirect('/login')->withErrors(['code' => __('booking.auth.verify.expired')]);
            }

            $request->session()->put('login.pending_verification', $pending);

            return redirect()->route('login.verify.show')->withErrors(['code' => __('booking.auth.verify.invalid')]);
        }

        $user = User::findOrFail($pending['user_id']);
        $ip = (string) ($pending['ip'] ?? $request->ip());
        $countryCode = isset($pending['country_code']) ? (string) $pending['country_code'] : null;
        $riskFlags = is_array($pending['risk_flags'] ?? null)
            ? array_values(array_filter($pending['risk_flags'], 'is_string'))
            : [];

        LoginHistory::record($user->getKey(), $ip, $countryCode, 'success', $riskFlags);

        Auth::loginUsingId($user->getKey(), (bool) $pending['remember']);
        $request->session()->regenerate();
        $request->session()->forget('login.pending_verification');

        $this->bookkeeping->syncLegacyLoginTracking($user, true, $ip, 600);
        $this->bookkeeping->logAttempt('succeeded', $request, $user, [
            'email' => (string) $user->email,
            'verified' => true,
        ]);

        return redirect()->to($pending['redirect_to']);
    }

    public function resend(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('login.pending_verification');

        if (! is_array($pending)) {
            return redirect('/login');
        }

        $key = 'login-verify-resend:'.$pending['user_id'];

        if (! RateLimiter::tooManyAttempts($key, 1)) {
            RateLimiter::hit($key, (int) config('booking.login_verification.resend_decay_seconds'));

            $code = (string) random_int(100000, 999999);
            $newPending = $pending;
            $newPending['code_hash'] = Hash::make($code);
            $newPending['expires_at'] = now()->addMinutes((int) config('booking.login_verification.code_ttl_minutes'))->toIso8601String();
            $newPending['attempts'] = 0;

            $user = User::findOrFail($pending['user_id']);

            try {
                Mail::to($user->email)->send(new LoginVerificationCode($code));
            } catch (\Throwable $e) {
                Log::error('Failed to resend login verification code', [
                    'user_id' => $user->getKey(),
                    'exception' => $e->getMessage(),
                ]);

                return redirect()->route('login.verify.show')->withErrors([
                    'code' => __('booking.auth.verify.send_failed'),
                ]);
            }

            $request->session()->put('login.pending_verification', $newPending);
        }

        return redirect()->route('login.verify.show');
    }
}
