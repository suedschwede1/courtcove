<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordReset;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * ForgotPasswordController — request a password reset link by email.
 *
 * Routes: GET /password/forgot (showForm), POST /password/forgot (sendResetLink)
 * Auth: guest only
 */
final class ForgotPasswordController extends Controller
{
    public function showForm(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Always responds with the same generic message, regardless of whether the
     * email exists or the account is enabled — this prevents account enumeration.
     */
    public function sendResetLink(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user && $user->isEnabled()) {
            $token = Password::broker()->createToken($user);
            $resetUrl = url(route('password.reset', ['token' => $token, 'email' => $user->email], false));

            Mail::to($user->email)->queue(new PasswordReset($user, $resetUrl));
        }

        return redirect()->route('password.forgot')
            ->with('status', __('booking.password_reset.forgot_sent'));
    }
}
