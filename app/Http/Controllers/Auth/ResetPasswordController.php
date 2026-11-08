<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
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
        $devices = app(TrustedDeviceService::class);
        $devices->revokeAll($user);

        // Accounts that need a trusted device sign in normally afterwards (password, then e-mail code).
        if ($devices->isRequiredFor($user)) {
            return redirect()->route('login')->with('success', __('booking.password_reset.reset_success_signin'));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('calendar.index')
            ->with('success', __('booking.password_reset.reset_success'));
    }
}
