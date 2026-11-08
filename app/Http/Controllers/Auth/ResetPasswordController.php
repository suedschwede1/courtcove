<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GeoLookup;
use App\Services\LoginVerificationService;
use App\Services\TrustedDeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * ResetPasswordController — set a new password using a token from the emailed link.
 *
 * Routes: GET /password/reset/{token} (showForm), POST /password/reset (reset)
 * Auth: guest only
 */
final class ResetPasswordController extends Controller
{
    public function __construct(
        private readonly TrustedDeviceService $devices,
        private readonly LoginVerificationService $verification,
        private readonly GeoLookup $geoLookup,
    ) {}

    public function showForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! $user->isEnabled() || ! Password::broker()->tokenExists($user, $data['token'])) {
            return redirect()->route('password.forgot')
                ->withErrors(['email' => __('booking.password_reset.reset_invalid')]);
        }

        $user->update(['pw' => Hash::make($data['password'])]);
        Password::broker()->deleteToken($user);
        // A new password ends the trust in every device and session and voids open codes. The mailbox link just
        // proved access, so for accounts that need a trusted device this browser is confirmed right here: a code
        // goes to the mailbox, entering it signs in and trusts this browser (no second round trip via the form).
        $this->devices->revokeAll($user, true);

        if ($this->devices->isRequiredFor($user)) {
            $ip = (string) $request->ip();
            $this->verification->resetThrottles($user, $ip);
            $request->session()->regenerate();

            $started = $this->verification->begin(
                $request, $user, false, route('calendar.index'), $ip, $this->geoLookup->resolveCountry($ip), ['new_device'], true,
            );

            return match ($started) {
                LoginVerificationService::SENT => redirect()->route('login.verify.show')->with('status', __('booking.password_reset.reset_success_confirm')),
                LoginVerificationService::MAIL_FAILED => redirect()->route('login.verify.show')->withErrors(['code' => __('booking.auth.verify.send_failed')]),
                default => redirect()->route('login')->with('success', __('booking.password_reset.reset_success_signin')),
            };
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('calendar.index')
            ->with('success', __('booking.password_reset.reset_success'));
    }
}
