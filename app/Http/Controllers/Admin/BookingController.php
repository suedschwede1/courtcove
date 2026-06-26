<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookingService) {}

    public function index(Request $request): View
    {
        $query = Booking::with(['user', 'square', 'reservations', 'meta']);

        if ($request->filled('sid')) {
            $query->where('sid', (int) $request->input('sid'));
        }

        if ($request->filled('uid')) {
            $query->where('uid', (int) $request->input('uid'));
        }

        $bookings = $query->orderByDesc('bid')->paginate(50);
        $squares = Square::orderBy('priority')->get();

        return view('admin.bookings.index', compact('bookings', 'squares'));
    }

    public function show(Booking $booking): View
    {
        $booking->load(['user', 'square', 'reservations', 'meta']);

        return view('admin.bookings.show', compact('booking'));
    }

    public function edit(Booking $booking): View
    {
        $booking->load(['user', 'square', 'reservations', 'meta']);

        return view('admin.bookings.edit', [
            'booking' => $booking,
            'users' => User::where('status', '!=', 'deleted')->orderBy('alias')->get(),
            'squares' => Square::orderBy('priority')->orderBy('sid')->get(),
            'reservation' => $booking->reservations()->orderBy('date')->orderBy('time_start')->first(),
            'playerNames' => [
                2 => $booking->player_names[0] ?? '',
                3 => $booking->player_names[1] ?? '',
                4 => $booking->player_names[2] ?? '',
            ],
        ]);
    }

    public function update(Request $request, Booking $booking): RedirectResponse
    {
        $reservation = $booking->reservations()->orderBy('date')->orderBy('time_start')->first();

        if (!$reservation) {
            return back()->withErrors(['booking' => 'Zu dieser Buchung wurde keine Reservierung gefunden.']);
        }

        $data = $request->validate([
            'uid' => ['required', 'integer', 'exists:bs_users,uid'],
            'sid' => ['required', 'integer', 'exists:bs_squares,sid'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['required', 'date_format:H:i'],
            'quantity' => ['required', 'integer', 'in:2,4'],
            'status' => ['required', 'in:single,cancelled'],
            'player_name_2' => ['required_if:quantity,2,4', 'nullable', 'string', 'max:120'],
            'player_name_3' => ['required_if:quantity,4', 'nullable', 'string', 'max:120'],
            'player_name_4' => ['required_if:quantity,4', 'nullable', 'string', 'max:120'],
        ]);

        $dateStart = Carbon::createFromFormat('Y-m-d H:i', $data['date'] . ' ' . $data['time_start']);
        $dateEnd = Carbon::createFromFormat('Y-m-d H:i', $data['date'] . ' ' . $data['time_end']);

        if (!$dateEnd->greaterThan($dateStart)) {
            return back()->withErrors(['booking' => 'Die Endzeit muss nach der Startzeit liegen.'])->withInput();
        }

        if ((int) $data['quantity'] === 2) {
            $data['player_name_3'] = null;
            $data['player_name_4'] = null;
        }

        if ($data['status'] === 'single') {
            $conflict = Reservation::query()
                ->join('bs_bookings', 'bs_bookings.bid', '=', 'bs_reservations.bid')
                ->where('bs_bookings.sid', (int) $data['sid'])
                ->whereIn('bs_bookings.status', Booking::ACTIVE_STATUSES)
                ->where('bs_bookings.bid', '!=', $booking->bid)
                ->where('bs_reservations.date', $dateStart->toDateString())
                ->where('bs_reservations.time_start', '<', $dateEnd->format('H:i:s'))
                ->where('bs_reservations.time_end', '>', $dateStart->format('H:i:s'))
                ->exists();

            if ($conflict) {
                return back()->withErrors(['booking' => 'Zu dieser Zeit existiert bereits eine andere Buchung auf diesem Platz.'])->withInput();
            }

            $eventConflict = Event::query()
                ->where('status', 'enabled')
                ->where('sid', (int) $data['sid'])
                ->where('datetime_start', '<', $dateEnd->format('Y-m-d H:i:s'))
                ->where('datetime_end', '>', $dateStart->format('Y-m-d H:i:s'))
                ->exists();

            if ($eventConflict) {
                return back()->withErrors(['booking' => 'Zu dieser Zeit blockiert eine Veranstaltung den Platz.'])->withInput();
            }
        }

        $booking->update([
            'uid' => (int) $data['uid'],
            'sid' => (int) $data['sid'],
            'quantity' => (int) $data['quantity'],
            'status' => $data['status'],
            'status_billing' => $data['status'] === 'cancelled'
                ? 'cancelled'
                : ($booking->status_billing === 'paid' ? 'paid' : 'pending'),
        ]);

        $reservation->update([
            'date' => $dateStart->format('Y-m-d'),
            'time_start' => $dateStart->format('H:i:s'),
            'time_end' => $dateEnd->format('H:i:s'),
        ]);

        $this->bookingService->syncPlayerMeta($booking, [
            2 => $data['player_name_2'] ?? null,
            3 => $data['player_name_3'] ?? null,
            4 => $data['player_name_4'] ?? null,
        ]);

        return redirect()->route('admin.bookings.index')->with('success', 'Buchung aktualisiert.');
    }

    public function cancel(Booking $booking): RedirectResponse
    {
        $this->bookingService->cancelSingle($booking);

        return redirect()->route('admin.bookings.index')->with('success', 'Buchung storniert.');
    }

    public function destroy(Booking $booking): RedirectResponse
    {
        $this->bookingService->deleteSingle($booking);

        return redirect()->route('admin.bookings.index')->with('success', 'Buchung geloescht.');
    }
}
