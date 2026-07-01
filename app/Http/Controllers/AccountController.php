<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Square;
use App\Models\User;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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

    /** List the authenticated user's bookings only after an explicit search. */
    public function bookings(Request $request, BookingService $bookingService): View
    {
        /** @var User $user */
        $user = auth()->user();

        $searched = $request->boolean('searched');
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'sid' => (string) $request->query('sid', ''),
            'status' => (string) $request->query('status', 'active'),
            'date_from' => (string) $request->query('date_from', ''),
            'date_to' => (string) $request->query('date_to', ''),
        ];

        $squares = Square::with(['meta' => fn ($query) => $query->where('key', 'alias')])
            ->orderBy('priority')
            ->orderBy('sid')
            ->get();

        $bookings = collect();
        $cancellableBookingIds = [];

        if ($searched) {
            $query = $user->bookings()
                ->with(['square.meta', 'reservations', 'meta', 'user'])
                ->orderByDesc('bid');

            if ($filters['sid'] !== '') {
                $query->where('sid', (int) $filters['sid']);
            }

            match ($filters['status']) {
                'single', 'subscription', 'cancelled' => $query->where('status', $filters['status']),
                'all' => null,
                default => $query->whereIn('status', Booking::ACTIVE_STATUSES),
            };

            if ($filters['date_from'] !== '') {
                $query->whereHas('reservations', fn ($reservationQuery) => $reservationQuery->where('date', '>=', $filters['date_from']));
            }

            if ($filters['date_to'] !== '') {
                $query->whereHas('reservations', fn ($reservationQuery) => $reservationQuery->where('date', '<=', $filters['date_to']));
            }

            $bookings = $query->get();

            if ($filters['q'] !== '') {
                $needle = Str::lower($filters['q']);
                $bookings = $bookings->filter(function (Booking $booking) use ($needle): bool {
                    $haystack = collect([
                        $booking->square?->name,
                        $booking->square?->display_name,
                        $booking->owner_label,
                        $booking->player_names_label,
                    ])->filter()->map(fn (string $value): string => Str::lower($value))->implode(' ');

                    return Str::contains($haystack, $needle);
                })->values();
            }

            $bookings = $bookings
                ->sortByDesc(function (Booking $booking): string {
                    $reservation = $booking->reservations
                        ->sortBy(fn ($reservation): string => (string) $reservation->date.' '.(string) $reservation->time_start)
                        ->first();

                    return (string) $reservation?->date.' '.(string) $reservation?->time_start;
                })
                ->values();
            $cancellableBookingIds = $bookings
                ->filter(function (Booking $booking) use ($bookingService, $user): bool {
                    $reservation = $booking->reservations
                        ->sortBy(fn ($reservation): string => (string) $reservation->date.' '.(string) $reservation->time_start)
                        ->first();

                    if (! $reservation || ! Carbon::parse($reservation->date.' '.$reservation->time_start)->isFuture()) {
                        return false;
                    }

                    return $bookingService->canUserCancelSingle($user, $booking);
                })
                ->pluck('bid')
                ->all();
        }

        return view('account.bookings', compact('bookings', 'cancellableBookingIds', 'filters', 'searched', 'squares'));
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
