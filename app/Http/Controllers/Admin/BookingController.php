<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\BookingValidationException;
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
    private const REPEAT_KEYS = [
        'once', 'daily', 'every_2_days', 'every_3_days', 'every_4_days',
        'every_5_days', 'every_6_days', 'weekly', 'every_2_weeks', 'monthly', 'custom',
    ];

    /** @return array<string,string> */
    private static function repeatOptions(): array
    {
        return array_combine(
            self::REPEAT_KEYS,
            array_map(fn (string $k) => __('booking.repeat.'.$k), self::REPEAT_KEYS),
        );
    }

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

    public function create(Request $request): View
    {
        $square = Square::findOrFail((int) $request->integer('sid'));
        $date = $request->string('date')->value();
        $rawStart = $request->input('time_start', '00:00');
        $rawEnd   = $request->input('time_end', '01:00');
        // Accept either H:i strings ("12:00") or raw seconds ("43200")
        $timeStartSeconds = str_contains((string) $rawStart, ':')
            ? (int) explode(':', $rawStart)[0] * 3600 + (int) explode(':', $rawStart)[1] * 60
            : (int) $rawStart;
        $timeEndSeconds = str_contains((string) $rawEnd, ':')
            ? (int) explode(':', $rawEnd)[0] * 3600 + (int) explode(':', $rawEnd)[1] * 60
            : (int) $rawEnd;
        if ($timeEndSeconds <= $timeStartSeconds) {
            $timeEndSeconds = $timeStartSeconds + 3600;
        }
        $defaultUser = auth()->user();

        $booking = new Booking([
            'uid' => $defaultUser?->getAuthIdentifier(),
            'sid' => $square->sid,
            'quantity' => 2,
            'status' => 'single',
            'status_billing' => 'pending',
        ]);
        $booking->setRelation('user', $defaultUser);
        $booking->setRelation('square', $square);

        $reservation = new Reservation([
            'date' => $date,
            'time_start' => sprintf('%02d:%02d:00', intdiv($timeStartSeconds, 3600), intdiv($timeStartSeconds % 3600, 60)),
            'time_end' => sprintf('%02d:%02d:00', intdiv($timeEndSeconds, 3600), intdiv($timeEndSeconds % 3600, 60)),
        ]);

        $squares = Square::orderBy('priority')->orderBy('sid')->get();

        return view('admin.bookings.edit', [
            'booking'      => $booking,
            'users'        => User::where('status', '!=', 'deleted')->orderBy('alias')->get(['uid', 'alias']),
            'squares'      => $squares,
            'reservation'  => $reservation,
            'bookedFor'    => '',
            'playerNames'  => [2 => '', 3 => '', 4 => ''],
            'adminNote'    => '',
            'repeatOptions' => self::repeatOptions(),
            'repeatType'   => 'once',
            'repeatEndDate' => $date,
            'isCreate'     => true,
            'eventFormData' => [
                'event'       => new Event(['sid' => $square->sid, 'capacity' => 0]),
                'name'        => '',
                'description' => '',
                'notes'       => '',
                'date_start'  => $date,
                'time_start'  => substr($reservation->time_start, 0, 5),
                'date_end'    => $date,
                'time_end'    => substr($reservation->time_end, 0, 5),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $data = $this->validateBookingData($request);
            $owner = $this->resolveOwner($data['booked_for']);
            [, , $occurrenceStarts, $durationSeconds] = $this->buildBookingTimeline($data);

            if ($data['status'] !== 'cancelled') {
                $conflictError = $this->batchCheckConflicts((int) $data['sid'], $occurrenceStarts, $durationSeconds, null);
                if ($conflictError !== null) {
                    return back()->withErrors(['booking' => $conflictError])->withInput();
                }
            }

            DB::transaction(function () use ($data, $owner, $occurrenceStarts, $durationSeconds): void {
                $booking = Booking::create([
                    'uid' => $owner['uid'],
                    'sid' => (int) $data['sid'],
                    'quantity' => (int) $data['quantity'],
                    'status' => $data['status'] === 'cancelled' ? 'cancelled' : (count($occurrenceStarts) > 1 ? 'subscription' : 'single'),
                    'status_billing' => 'pending',
                    'visibility' => 'public',
                    'created' => now()->format('Y-m-d H:i:s'),
                ]);

                $rows = [];
                foreach ($occurrenceStarts as $occurrenceStart) {
                    $rows[] = [
                        'bid'        => $booking->bid,
                        'date'       => $occurrenceStart->format('Y-m-d'),
                        'time_start' => $occurrenceStart->format('H:i:s'),
                        'time_end'   => $occurrenceStart->copy()->addSeconds($durationSeconds)->format('H:i:s'),
                    ];
                }
                DB::table('bs_reservations')->insert($rows);

                $this->bookingService->syncPlayerMeta($booking, [
                    2 => $data['player_name_2'] ?? null,
                    3 => $data['player_name_3'] ?? null,
                    4 => $data['player_name_4'] ?? null,
                ]);

                $this->syncBookingMeta($booking, 'owner-name', $owner['ownerName']);
                $this->syncBookingMeta($booking, 'admin-note', $data['admin_note'] ?? null);
            });
        } catch (BookingValidationException $e) {
            return back()->withErrors(['booking' => $e->getMessage()])->withInput();
        }

        return redirect()->route('calendar.index', ['date' => Carbon::parse($data['date'])->format('Y-m-d')])
            ->with('success', __('booking.messages.booking_created'));
    }

    public function show(Booking $booking): View
    {
        $booking->load(['user', 'square', 'reservations', 'meta']);

        return view('admin.bookings.show', compact('booking'));
    }

    public function edit(Booking $booking): View
    {
        $booking->load(['user', 'square', 'reservations', 'meta']);
        $reservations = $booking->reservations->sortBy([['date', 'asc'], ['time_start', 'asc']]);
        $reservation = $reservations->first();
        $repeatType = $this->detectRepeatType($reservations->all());
        $repeatEndDate = $reservations->last()?->date ?? $reservation?->date;

        return view('admin.bookings.edit', [
            'booking' => $booking,
            'users' => User::where('status', '!=', 'deleted')->orderBy('alias')->get(['uid', 'alias']),
            'squares' => Square::orderBy('priority')->orderBy('sid')->get(),
            'reservation' => $reservation,
            'bookedFor' => $booking->owner_label,
            'playerNames' => [
                2 => $booking->player_names[0] ?? '',
                3 => $booking->player_names[1] ?? '',
                4 => $booking->player_names[2] ?? '',
            ],
            'adminNote' => $booking->meta->firstWhere('key', 'admin-note')?->value ?? '',
            'repeatOptions' => self::repeatOptions(),
            'repeatType' => $repeatType,
            'repeatEndDate' => $repeatEndDate,
            'isCreate' => false,
        ]);
    }

    public function update(Request $request, Booking $booking): RedirectResponse
    {
        $firstReservation = $booking->reservations()->orderBy('date')->orderBy('time_start')->first();

        if (! $firstReservation) {
            return back()->withErrors(['booking' => __('booking.messages.booking_reservation_missing')]);
        }

        try {
            $data = $this->validateBookingData($request);
            $owner = $this->resolveOwner($data['booked_for']);
            [, , $occurrenceStarts, $durationSeconds] = $this->buildBookingTimeline($data);

            if ($data['status'] !== 'cancelled') {
                $conflictError = $this->batchCheckConflicts((int) $data['sid'], $occurrenceStarts, $durationSeconds, $booking->bid);
                if ($conflictError !== null) {
                    return back()->withErrors(['booking' => $conflictError])->withInput();
                }
            }

            DB::transaction(function () use ($booking, $data, $owner, $occurrenceStarts, $durationSeconds): void {
                $effectiveStatus = $data['status'] === 'cancelled'
                    ? 'cancelled'
                    : (count($occurrenceStarts) > 1 ? 'subscription' : 'single');

                $booking->update([
                    'uid' => $owner['uid'],
                    'sid' => (int) $data['sid'],
                    'quantity' => (int) $data['quantity'],
                    'status' => $effectiveStatus,
                ]);

                $booking->loadMissing(['reservations.meta']);
                foreach ($booking->reservations as $reservation) {
                    $reservation->meta()->delete();
                }
                $booking->reservations()->delete();

                $rows = [];
                foreach ($occurrenceStarts as $occurrenceStart) {
                    $rows[] = [
                        'bid'        => $booking->bid,
                        'date'       => $occurrenceStart->format('Y-m-d'),
                        'time_start' => $occurrenceStart->format('H:i:s'),
                        'time_end'   => $occurrenceStart->copy()->addSeconds($durationSeconds)->format('H:i:s'),
                    ];
                }
                DB::table('bs_reservations')->insert($rows);

                $this->bookingService->syncPlayerMeta($booking, [
                    2 => $data['player_name_2'] ?? null,
                    3 => $data['player_name_3'] ?? null,
                    4 => $data['player_name_4'] ?? null,
                ]);

                $this->syncBookingMeta($booking, 'owner-name', $owner['ownerName']);
                $this->syncBookingMeta($booking, 'admin-note', $data['admin_note'] ?? null);
            });
        } catch (BookingValidationException $e) {
            return back()->withErrors(['booking' => $e->getMessage()])->withInput();
        }

        $redirectTo = $request->string('redirect_to')->trim()->value();
        if ($redirectTo !== '') {
            return redirect()->to($redirectTo)->with('success', __('booking.messages.booking_updated'));
        }

        return redirect()->route('admin.bookings.index')->with('success', __('booking.messages.booking_updated'));
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $this->bookingService->cancelSingle($booking);

        $redirectTo = $request->string('redirect_to')->trim()->value();
        if ($redirectTo !== '') {
            return redirect()->to($redirectTo)->with('success', __('booking.messages.booking_cancelled'));
        }

        return redirect()->route('admin.bookings.index')->with('success', __('booking.messages.booking_cancelled'));
    }

    public function destroy(Request $request, Booking $booking): RedirectResponse
    {
        $this->bookingService->deleteSingle($booking);

        $redirectTo = $request->string('redirect_to')->trim()->value();
        if ($redirectTo !== '') {
            return redirect()->to($redirectTo)->with('success', __('booking.messages.booking_deleted'));
        }

        return redirect()->route('admin.bookings.index')->with('success', __('booking.messages.booking_deleted'));
    }

    /**
     * Resolve the free-text "Gebucht für" value to a booking owner.
     *
     * An exact, unambiguous member alias resolves to that member's uid (the booking
     * attaches to their account). Anything else — no match, or an alias shared by
     * several members — is kept as a free-text owner name stored in booking meta,
     * with the acting admin as the fallback uid so the FK/permissions stay valid.
     *
     * @return array{uid: int, ownerName: ?string}
     */
    private function resolveOwner(string $bookedFor): array
    {
        $name = trim($bookedFor);

        $matches = User::where('status', '!=', 'deleted')
            ->whereRaw('LOWER(alias) = ?', [mb_strtolower($name)])
            ->limit(2)
            ->get();

        if ($matches->count() === 1) {
            return ['uid' => (int) $matches->first()->uid, 'ownerName' => null];
        }

        return ['uid' => (int) auth()->id(), 'ownerName' => $name];
    }

    private function validateBookingData(Request $request): array
    {
        $data = $request->validate([
            'booked_for' => ['required', 'string', 'max:120'],
            'sid' => ['required', 'integer', 'exists:bs_squares,sid'],
            'date' => ['required', 'date_format:Y-m-d'],
            'date_end' => ['nullable', 'date_format:Y-m-d'],
            'repeat_type' => ['required', 'in:'.implode(',', self::REPEAT_KEYS)],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['required', 'date_format:H:i'],
            'quantity' => ['required', 'integer', 'in:2,4'],
            'status' => ['nullable', 'in:single,subscription,cancelled'],
            'player_name_2' => ['required_if:quantity,2,4', 'nullable', 'string', 'max:120'],
            'player_name_3' => ['required_if:quantity,4', 'nullable', 'string', 'max:120'],
            'player_name_4' => ['required_if:quantity,4', 'nullable', 'string', 'max:120'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ((int) $data['quantity'] === 2) {
            $data['player_name_3'] = null;
            $data['player_name_4'] = null;
        }

        return $data;
    }

    private function buildBookingTimeline(array $data): array
    {
        $dateStart = Carbon::createFromFormat('Y-m-d H:i', $data['date'].' '.$data['time_start']);
        $dateEnd = Carbon::createFromFormat('Y-m-d H:i', $data['date'].' '.$data['time_end']);

        if (! $dateEnd->greaterThan($dateStart)) {
            throw new BookingValidationException(__('booking.validation.end_time_after_start'));
        }

        $repeatType = $data['repeat_type'];
        $repeatEndDate = $repeatType === 'once'
            ? Carbon::createFromFormat('Y-m-d', $data['date'])
            : Carbon::createFromFormat('Y-m-d', (string) ($data['date_end'] ?: $data['date']));

        if ($repeatEndDate->lt($dateStart->copy()->startOfDay())) {
            throw new BookingValidationException(__('booking.validation.repeat_end_date_after_start'));
        }

        $occurrenceStarts = $this->buildOccurrenceStarts($dateStart, $repeatType, $repeatEndDate);
        if ($occurrenceStarts === []) {
            throw new BookingValidationException(__('booking.validation.no_valid_repeat'));
        }

        return [$dateStart, $dateEnd, $occurrenceStarts, (int) $dateStart->diffInSeconds($dateEnd)];
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
            static fn (Reservation $reservation): Carbon => Carbon::createFromFormat('Y-m-d', $reservation->date),
            $reservations,
        );

        $allMonthly = true;
        $daySteps = [];

        for ($i = 1, $count = count($dates); $i < $count; $i++) {
            $previous = $dates[$i - 1]->copy();
            $current = $dates[$i]->copy();
            // diffInDays() returns a float in Carbon 3; cast to int so the match
            // arms below (1, 2, … 7, 14) compare correctly instead of always failing.
            $daySteps[] = (int) $previous->diffInDays($current);
            if (! $previous->copy()->addMonthNoOverflow()->isSameDay($current)) {
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

            if (! $next || $next->equalTo($cursor)) {
                break;
            }

            $cursor = $next;
        }

        return $occurrences;
    }

    /**
     * Check all occurrences for booking and event conflicts in 2 queries instead of 2×N.
     * Returns a translated error string on the first conflict found, or null if clear.
     *
     * @param Carbon[] $occurrenceStarts
     */
    private function batchCheckConflicts(int $sid, array $occurrenceStarts, int $durationSeconds, ?int $excludeBookingId): ?string
    {
        if (empty($occurrenceStarts)) {
            return null;
        }

        $dateMin = min($occurrenceStarts)->format('Y-m-d');
        $lastEnd = max($occurrenceStarts)->copy()->addSeconds($durationSeconds);
        $dateMax = $lastEnd->format('Y-m-d');

        // One query: all active reservations for this court in the date range
        $existingReservations = Reservation::query()
            ->join('bs_bookings', 'bs_bookings.bid', '=', 'bs_reservations.bid')
            ->where('bs_bookings.sid', $sid)
            ->whereIn('bs_bookings.status', Booking::ACTIVE_STATUSES)
            ->whereBetween('bs_reservations.date', [$dateMin, $dateMax])
            ->when($excludeBookingId !== null, fn ($q) => $q->where('bs_bookings.bid', '!=', $excludeBookingId))
            ->select(['bs_reservations.date', 'bs_reservations.time_start', 'bs_reservations.time_end'])
            ->get();

        // One query: all enabled events overlapping the range
        $existingEvents = Event::query()
            ->where('status', 'enabled')
            ->where(fn ($q) => $q->where('sid', $sid)->orWhereNull('sid'))
            ->where('datetime_start', '<', $lastEnd->format('Y-m-d H:i:s'))
            ->where('datetime_end', '>', min($occurrenceStarts)->format('Y-m-d H:i:s'))
            ->select(['datetime_start', 'datetime_end'])
            ->get();

        foreach ($occurrenceStarts as $occurrenceStart) {
            $occurrenceEnd   = $occurrenceStart->copy()->addSeconds($durationSeconds);
            $dateStr         = $occurrenceStart->format('Y-m-d');
            $startTimeStr    = $occurrenceStart->format('H:i:s');
            $endTimeStr      = $occurrenceEnd->format('H:i:s');
            $startDatetimeStr = $occurrenceStart->format('Y-m-d H:i:s');
            $endDatetimeStr   = $occurrenceEnd->format('Y-m-d H:i:s');

            foreach ($existingReservations as $res) {
                if ($res->date === $dateStr
                    && (string) $res->time_start < $endTimeStr
                    && (string) $res->time_end > $startTimeStr) {
                    return __('booking.messages.repeat_booking_conflict');
                }
            }

            foreach ($existingEvents as $event) {
                if ((string) $event->datetime_start < $endDatetimeStr
                    && (string) $event->datetime_end > $startDatetimeStr) {
                    return __('booking.messages.repeat_event_conflict');
                }
            }
        }

        return null;
    }

    private function hasBookingConflict(int $sid, Carbon $start, Carbon $end, ?int $excludeBookingId): bool
    {
        $query = Reservation::query()
            ->join('bs_bookings', 'bs_bookings.bid', '=', 'bs_reservations.bid')
            ->where('bs_bookings.sid', $sid)
            ->whereIn('bs_bookings.status', Booking::ACTIVE_STATUSES)
            ->where('bs_reservations.date', $start->format('Y-m-d'))
            ->where('bs_reservations.time_start', '<', $end->format('H:i:s'))
            ->where('bs_reservations.time_end', '>', $start->format('H:i:s'));

        if ($excludeBookingId !== null) {
            $query->where('bs_bookings.bid', '!=', $excludeBookingId);
        }

        return $query->exists();
    }

    private function hasEventConflict(int $sid, Carbon $start, Carbon $end): bool
    {
        return Event::query()
            ->where('status', 'enabled')
            ->where(function ($query) use ($sid): void {
                $query->where('sid', $sid)->orWhereNull('sid');
            })
            ->where('datetime_start', '<', $end->format('Y-m-d H:i:s'))
            ->where('datetime_end', '>', $start->format('Y-m-d H:i:s'))
            ->exists();
    }
}
