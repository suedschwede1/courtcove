<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\TrustedDeviceAdded;
use App\Models\LoginHistory;
use App\Models\User;
use App\Services\LoginBookkeeping;
use App\Services\LoginVerificationService;
use App\Services\TrustedDeviceService;
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
 * LoginVerificationController — the code-entry step for sign-ins that need an e-mail code (risk flags, a device
 * that is not trusted yet, a password reset; see App\Services\LoginVerificationService). The pending state lives
 * in the session under 'login.pending_verification'; the attempt counter lives server-side per account.
 *
 * A wrong, expired or used-up code never locks anything for good: "send a new code" is always possible, limited
 * only by waiting times, and a new code voids the old one and restarts the counter.
 */
final class LoginVerificationController extends Controller
{
    public function __construct(
        private readonly LoginBookkeeping $bookkeeping,
        private readonly TrustedDeviceService $devices,
        private readonly LoginVerificationService $verification,
    ) {}

    public function show(Request $request): RedirectResponse|View
    {
        $pending = $request->session()->get('login.pending_verification');

        if (! is_array($pending)) {
            return redirect('/login');
        }

        return view('auth.login-verify', ['newDevice' => ($pending['trust_device'] ?? false) === true]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('login.pending_verification');

        if (! is_array($pending)) {
            return redirect('/login');
        }

        $data = $request->validate(['code' => ['required', 'string']]);

        $user = User::find($pending['user_id']);

        // The account may have changed since the password step (disabled, removed, trust reset): start over.
        if (! $user || ! $user->isEnabled() || $this->verification->isVoided($pending)) {
            $request->session()->forget('login.pending_verification');

            return redirect('/login')->withErrors(['code' => __('booking.auth.verify.expired')]);
        }

        if (Carbon::parse($pending['expires_at'])->isPast()) {
            return $this->back(__('booking.auth.verify.expired_new'));
        }

        // Count first, then check: parallel guesses cannot all pass a "still below the limit" test.
        $failKey = $this->verification->failKey($user);
        $tries = RateLimiter::hit($failKey, (int) config('booking.login_verification.code_ttl_minutes') * 60);

        if ($tries > (int) config('booking.login_verification.max_attempts')) {
            return $this->back(__('booking.auth.verify.too_many_attempts'));
        }

        if (! Hash::check($data['code'], $pending['code_hash'])) {
            return $this->back(__('booking.auth.verify.invalid'));
        }

        RateLimiter::clear($failKey);

        $ip = (string) ($pending['ip'] ?? $request->ip());
        $countryCode = isset($pending['country_code']) ? (string) $pending['country_code'] : null;
        $riskFlags = is_array($pending['risk_flags'] ?? null)
            ? array_values(array_filter($pending['risk_flags'], 'is_string'))
            : [];

        // "Confirm this device" (admin page) runs for an account that is signed in already: no second login record.
        $alreadySignedIn = ($pending['force_trust'] ?? false) === true && (int) Auth::id() === (int) $user->getKey();

        if (! $alreadySignedIn) {
            LoginHistory::record($user->getKey(), $ip, $countryCode, 'success', $riskFlags);
        }

        Auth::loginUsingId($user->getKey(), (bool) $pending['remember']);
        $request->session()->regenerate();
        $request->session()->forget('login.pending_verification');

        // The code proved access to the account's mailbox: this browser becomes a trusted device (also when the scope
        // does not require one yet, if the admin asked for it on the page "Sicherheit": force_trust).
        if (($pending['trust_device'] ?? false) === true && ($this->devices->isRequiredFor($user) || ($pending['force_trust'] ?? false) === true)) {
            $device = $this->devices->issue($request, $user, $countryCode);

            try {
                Mail::to($user->email)->send(new TrustedDeviceAdded($user, $device));
            } catch (\Throwable $e) {
                Log::warning('Failed to send trusted device notice', ['user_id' => $user->getKey(), 'exception' => $e->getMessage()]);
            }
        }

        if (! $alreadySignedIn) {
            $this->bookkeeping->syncLegacyLoginTracking($user, true, $ip, 600);
            $this->bookkeeping->logAttempt('succeeded', $request, $user, [
                'email' => (string) $user->email,
                'verified' => true,
            ]);
        }

        return redirect()->to($pending['redirect_to']);
    }

    /** "Send a new code": always allowed, limited by waiting times only (see LoginVerificationService). */
    public function resend(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('login.pending_verification');

        if (! is_array($pending)) {
            return redirect('/login');
        }

        $user = User::find($pending['user_id']);

        if (! $user || ! $user->isEnabled() || $this->verification->isVoided($pending)) {
            $request->session()->forget('login.pending_verification');

            return redirect('/login')->withErrors(['code' => __('booking.auth.verify.expired')]);
        }

        $result = $this->verification->resend($request, $user, $pending);
        $ip = (string) ($pending['ip'] ?? $request->ip());

        return match ($result) {
            LoginVerificationService::SENT => redirect()->route('login.verify.show')->with('status', __('booking.auth.verify.resent')),
            LoginVerificationService::PAUSE => $this->back(__('booking.auth.verify.resend_wait', ['seconds' => $this->verification->waitSeconds($user, $ip)])),
            LoginVerificationService::CAP => $this->back(__('booking.auth.verify.too_many_codes', ['minutes' => (int) ceil($this->verification->waitSeconds($user, $ip) / 60)])),
            default => $this->back(__('booking.auth.verify.send_failed')),
        };
    }

    private function back(string $message): RedirectResponse
    {
        return redirect()->route('login.verify.show')->withErrors(['code' => $message]);
    }
}
