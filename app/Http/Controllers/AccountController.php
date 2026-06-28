<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Member-facing account pages: own bookings ("Meine Buchungen") and
 * profile/password management ("Mein Konto"). All actions operate on the
 * currently authenticated user only.
 */
final class AccountController extends Controller
{
    /** Profile fields stored in bs_users_meta. */
    private const PROFILE_FIELDS = ['firstname', 'lastname', 'phone', 'street', 'zip', 'city', 'gender'];

    /** List the authenticated user's own active bookings, newest first. */
    public function bookings(): View
    {
        /** @var User $user */
        $user = auth()->user();

        $bookings = $user->bookings()
            ->whereIn('status', Booking::ACTIVE_STATUSES)
            ->with(['square', 'reservations'])
            ->orderByDesc('bid')
            ->get();

        return view('account.bookings', compact('bookings'));
    }

    /** Show the profile + password forms for the authenticated user. */
    public function edit(): View
    {
        /** @var User $user */
        $user = auth()->user();

        $user->load('meta');
        $profile = [];
        foreach (self::PROFILE_FIELDS as $field) {
            $profile[$field] = $user->getMeta($field);
        }

        return view('account.edit', compact('user', 'profile'));
    }

    /** Update alias, email and profile meta for the authenticated user. */
    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $data = $request->validate([
            'alias' => ['required', 'string', 'max:128'],
            'email' => ['nullable', 'email', 'max:128', 'unique:bs_users,email,'.$user->uid.',uid'],
            'firstname' => ['nullable', 'string', 'max:128'],
            'lastname' => ['nullable', 'string', 'max:128'],
            'phone' => ['nullable', 'string', 'max:128'],
            'street' => ['nullable', 'string', 'max:128'],
            'zip' => ['nullable', 'string', 'max:128'],
            'city' => ['nullable', 'string', 'max:128'],
            'gender' => ['nullable', 'string', 'max:128'],
        ]);

        $user->update([
            'alias' => $data['alias'],
            'email' => $data['email'] ?? null,
        ]);

        foreach (self::PROFILE_FIELDS as $field) {
            $value = $data[$field] ?? null;
            $user->setMeta($field, $value === '' ? null : $value);
        }

        return redirect()->route('account.edit')->with('success', __('booking.messages.profile_saved'));
    }

    /** Change the authenticated user's password after verifying the current one. */
    public function password(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], (string) $user->pw)) {
            throw ValidationException::withMessages([
                'current_password' => __('booking.validation.current_password_incorrect'),
            ]);
        }

        $user->update(['pw' => Hash::make($data['password'])]);

        return redirect()->route('account.edit')->with('success', __('booking.messages.password_changed'));
    }
}
