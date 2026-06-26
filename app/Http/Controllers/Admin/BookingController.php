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
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class BookingController extends Controller
{
    private const REPEAT_OPTIONS = [
        'once' => 'Einmalig',
        'daily' => 'Täglich',
        'every_2_days' => 'Alle 2 Tage',
        'every_3_days' => 'Alle 3 Tage',
        'every_4_days' => 'Alle 4 Tage',
        'every_5_days' => 'Alle 5 Tage',
        'every_6_days' => 'Alle 6 Tage',
        'weekly' => 'Wöchentlich',
        'every_2_weeks' => 'Alle 2 Wochen',
        'monthly' => 'Monatlich',
        'custom' => 'Individuell',
    ];

    public function __construct(private readonly BookingService $bookingService) {}

    public function index(Request $request): View
    {
        $query = Booking::with(['user', 'square', 'reservations', 'meta'])
            ->whereIn('status', Booking::ACTIVE_STATUSES);

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
        $reservations = $booking->reservations()->orderBy('date')->orderBy('time_start')->get();
        $reservation = $reservations->first();
        $repeatType = $this->detectRepeatType($reservations->all());
        $repeatEndDate = $reservations->last()?->date ?? $reservation?->date;

        return view('admin.bookings.edit', [
            'booking' => $booking,
            'users' => User::where('status', '!=', 'deleted')->orderBy('alias')->get(),
            'squares' => Square::orderBy('priority')->orderBy('sid')->get(),
            'reservation' => $reservation,
            'playerNames' => [
                2 => $booking->player_names[0] ?? '',
                3 => $booking->player_names[1] ?? '',
                4 => $booking->player_names[2] ?? '',
            ],
            'adminNote' => $booking->meta->firstWhere('key', 'admin-note')?->value ?? '',
            'repeatOptions' => self::REPEAT_OPTIONS,
            'repeatType' => $repeatType,
            'repeatEndDate' => $repeatEndDate,
        ]);
    }

    public function update(Request $request, Booking $booking): RedirectResponse
    {
        $firstReservation = $booking->reservations()->orderBy('date')->orderBy('time_start')->first();

        if (!$firstReservation) {
            return back()->withErrors(['booking' => 'Zu dieser Buchung wurde keine Reservierung gefunden.']);
        }

        $data = $request->validate([
            'uid' => ['required', 'integer', 'exists:bs_users,uid'],
            'sid' => ['required', 'integer', 'exists:bs_squares,sid'],
            'date' => ['required', 'date_format:Y-m-d'],
            'date_end' => ['nullable', 'date_format:Y-m-d'],
            'repeat_type' => ['required', 'in:' . implode(',', array_keys(self::REPEAT_OPTIONS))],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['required', 'date_format:H:i'],
            'quantity' => ['required', 'integer', 'in:2,4'],
            'status' => ['required', 'in:single,subscription,cancelled'],
            'status_billing' => ['required', 'in:pending,paid,cancelled'],
            'player_name_2' => ['required_if:quantity,2,4', 'nullable', 'string', 'max:120'],
            'player_name_3' => ['required_if:quantity,4', 'nullable', 'string', 'max:120'],
            'player_name_4' => ['required_if:quantity,4', 'nullable', 'string', 'max:120'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
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

        $repeatType = $data['repeat_type'];
        $repeatEndDate = $repeatType === 'once'
            ? Carbon::createFromFormat('Y-m-d', $data['date'])
            : Carbon::createFromFormat('Y-m-d', (string) ($data['date_end'] ?: $data['date']));

        if ($repeatEndDate->lt($dateStart->copy()->startOfDay())) {
            return back()->withErrors(['booking' => 'Das Enddatum der Wiederholung muss am oder nach dem Startdatum liegen.'])->withInput();
        }

        $occurrenceStarts = $this->buildOccurrenceStarts($dateStart, $repeatType, $repeatEndDate);
        if ($occurrenceStarts === []) {
            return back()->withErrors(['booking' => 'Es konnte keine gültige Wiederholung erzeugt werden.'])->withInput();
        }

        $durationSeconds = $dateStart->diffInSeconds($dateEnd);

        if ($data['status'] !== 'cancelled') {
            foreach ($occurrenceStarts as $occurrenceStart) {
                $occurrenceEnd = $occurrenceStart->copy()->addSeconds($durationSeconds);
                if ($this->hasBookingConflict((int) $data['sid'], $occurrenceStart, $occurrenceEnd, $booking->bid)) {
                    return back()->withErrors(['booking' => 'Mindestens ein Wiederholungstermin kollidiert mit einer anderen Buchung.'])->withInput();
                }

                if ($this->hasEventConflict((int) $data['sid'], $occurrenceStart, $occurrenceEnd)) {
                    return back()->withErrors(['booking' => 'Mindestens ein Wiederholungstermin kollidiert mit einer Veranstaltung.'])->withInput();
                }
            }
        }

        DB::transaction(function () use ($booking, $data, $occurrenceStarts, $durationSeconds): void {
            $effectiveStatus = $data['status'] === 'cancelled'
                ? 'cancelled'
                : (count($occurrenceStarts) > 1 ? 'subscription' : 'single');

            $booking->update([
                'uid' => (int) $data['uid'],
                'sid' => (int) $data['sid'],
                'quantity' => (int) $data['quantity'],
                'status' => $effectiveStatus,
                'status_billing' => $data['status_billing'],
            ]);

            $booking->loadMissing(['reservations.meta']);
            foreach ($booking->reservations as $reservation) {
                $reservation->meta()->delete();
            }
            $booking->reservations()->delete();

            foreach ($occurrenceStarts as $occurrenceStart) {
                $occurrenceEnd = $occurrenceStart->copy()->addSeconds($durationSeconds);
                $booking->reservations()->create([
                    'date' => $occurrenceStart->format('Y-m-d'),
                    'time_start' => $occurrenceStart->format('H:i:s'),
                    'time_end' => $occurrenceEnd->format('H:i:s'),
                ]);
            }

            $this->bookingService->syncPlayerMeta($booking, [
                2 => $data['player_name_2'] ?? null,
                3 => $data['player_name_3'] ?? null,
                4 => $data['player_name_4'] ?? null,
            ]);

            $this->syncBookingMeta($booking, 'admin-note', $data['admin_note'] ?? null);
        });

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

        return redirect()->route('admin.bookings.index')->with('success', 'Buchung gelöscht.');
    }

    private function syncBookingMeta(Booking $booking, string $key, ?string $value): void
    {
        $row = $booking->meta()->where('key', $key)->first();
        $normalized = is_string($value) ? trim($value) : '';

        if ($normalized === '') {
            if ($row) {
                $row->delete();
            }

            return;
        }

        if ($row) {
            $row->update(['value' => $normalized]);
            return;
        }

        $booking->meta()->create(['key' => $key, 'value' => $normalized]);
    }

    /** @param list<Reservation> $reservations */
    private function detectRepeatType(array $reservations): string
    {
        if (count($reservations) <= 1) {
            return 'once';
        }

        $dates = array_map(
            static fn(Reservation $reservation): Carbon => Carbon::createFromFormat('Y-m-d', $reservation->date),
            $reservations,
        );

        $allMonthly = true;
        $daySteps = [];

        for ($i = 1, $count = count($dates); $i < $count; $i++) {
            $previous = $dates[$i - 1]->copy();
            $current = $dates[$i]->copy();
            $daySteps[] = $previous->diffInDays($current);
            if (!$previous->copy()->addMonthNoOverflow()->isSameDay($current)) {
                $allMonthly = false;
            }
        }

        if ($allMonthly) {
            return 'monthly';
        }

        $uniqueSteps = array_values(array_unique($daySteps));
        if (count($uniqueSteps) !== 1) {
            return 'custom';
        }

        return match ($uniqueSteps[0]) {
            1 => 'daily',
            2 => 'every_2_days',
            3 => 'every_3_days',
            4 => 'every_4_days',
            5 => 'every_5_days',
            6 => 'every_6_days',
            7 => 'weekly',
            14 => 'every_2_weeks',
            default => 'custom',
        };
    }

    /** @return list<Carbon> */
    private function buildOccurrenceStarts(Carbon $start, string $repeatType, Carbon $repeatEndDate): array
    {
        $occurrences = [];
        $cursor = $start->copy();
        $endBoundary = $repeatEndDate->copy()->endOfDay();

        while ($cursor->lte($endBoundary)) {
            $occurrences[] = $cursor->copy();

            $next = match ($repeatType) {
                'once' => null,
                'daily' => $cursor->copy()->addDay(),
                'every_2_days' => $cursor->copy()->addDays(2),
                'every_3_days' => $cursor->copy()->addDays(3),
                'every_4_days' => $cursor->copy()->addDays(4),
                'every_5_days' => $cursor->copy()->addDays(5),
                'every_6_days' => $cursor->copy()->addDays(6),
                'weekly' => $cursor->copy()->addWeek(),
                'every_2_weeks' => $cursor->copy()->addWeeks(2),
                'monthly' => $cursor->copy()->addMonthNoOverflow(),
                default => null,
            };

            if (!$next || $next->equalTo($cursor)) {
                break;
            }

            $cursor = $next;
        }

        return $occurrences;
    }

    private function hasBookingConflict(int $sid, Carbon $start, Carbon $end, int $excludeBookingId): bool
    {
        return Reservation::query()
            ->join('bs_bookings', 'bs_bookings.bid', '=', 'bs_reservations.bid')
            ->where('bs_bookings.sid', $sid)
            ->whereIn('bs_bookings.status', Booking::ACTIVE_STATUSES)
            ->where('bs_bookings.bid', '!=', $excludeBookingId)
            ->where('bs_reservations.date', $start->format('Y-m-d'))
            ->where('bs_reservations.time_start', '<', $end->format('H:i:s'))
            ->where('bs_reservations.time_end', '>', $start->format('H:i:s'))
            ->exists();
    }

    private function hasEventConflict(int $sid, Carbon $start, Carbon $end): bool
    {
        return Event::query()
            ->where('status', 'enabled')
            ->where('sid', $sid)
            ->where('datetime_start', '<', $end->format('Y-m-d H:i:s'))
            ->where('datetime_end', '>', $start->format('Y-m-d H:i:s'))
            ->exists();
    }
}

