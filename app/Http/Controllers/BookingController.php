<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\BookingValidationException;
use App\Models\Booking;
use App\Models\Square;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * BookingController — handles HTTP requests for creating and cancelling court bookings.
 *
 * Routes:
 *   POST   /bookings           → store()   (auth required)
 *   DELETE /bookings/{booking} → destroy() (auth required, own booking only)
 * Auth: auth required
 */
final class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
    ) {}

    /**
     * Validate and create a single booking.
     *
     * On BookingValidationException, redirects back with error message.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sid'        => ['required', 'integer', 'exists:bs_squares,sid'],
            'date'       => ['required', 'date'],
            'time_start' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'time_end'   => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'quantity'   => ['required', 'integer', 'min:1', 'max:4'],
        ]);

        $square    = Square::findOrFail($data['sid']);
        $dateStart = Carbon::parse("{$data['date']} {$data['time_start']}");
        $dateEnd   = Carbon::parse("{$data['date']} {$data['time_end']}");

        try {
            $this->bookingService->createSingle(
                auth()->user(),
                $square,
                (int) $data['quantity'],
                $dateStart,
                $dateEnd,
            );
        } catch (BookingValidationException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('calendar.index', ['date' => $data['date']])
            ->with('success', 'Buchung erfolgreich gespeichert.');
    }

    /** Cancel own booking — returns 403 if booking belongs to another user. */
    public function destroy(Booking $booking): RedirectResponse
    {
        if ($booking->uid !== auth()->id()) {
            abort(403);
        }

        $this->bookingService->cancelSingle($booking);

        return redirect()->route('calendar.index')
            ->with('success', 'Buchung storniert.');
    }
}
