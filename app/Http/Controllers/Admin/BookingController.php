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
use Illuminate\Http\Response;
use Illuminate\View\View;

final class BookingController extends Controller
{
    /**
     * Offered repeat options plus any legacy type of the series being edited, so an
     * existing legacy series is still displayed with its label.
     *
     * @param  list<string>  $extraKeys
     * @return array<string,string>
     */
    private static function repeatOptions(array $extraKeys = []): array
    {
        $keys = array_values(array_unique(array_merge(
            BookingService::REPEAT_KEYS,
            array_intersect(BookingService::LEGACY_REPEAT_KEYS, $extraKeys),
        )));

        return array_combine($keys, array_map(fn (string $k) => __('booking.repeat.'.$k), $keys));
    }

    public function __construct(private readonly BookingService $bookingService) {}

    public function index(Request $request): View
    {
        $searched = $request->boolean('searched');
        $bookings = null;

        if ($searched) {
            $query = Booking::with(['user', 'square', 'reservations', 'meta'])
                ->whereIn('status', Booking::ACTIVE_STATUSES);

            if ($request->filled('sid')) {
                $query->where('sid', (int) $request->input('sid'));
            }

            if ($request->filled('uid')) {
                $query->where('uid', (int) $request->input('uid'));
            }

            $bookings = $query->orderByDesc('bid')->paginate(50)->withQueryString();
        }

        $squares = Square::orderBy('priority')->get();

        return view('admin.bookings.index', compact('bookings', 'squares', 'searched'));
    }

    public function create(Request $request): View
    {
        $square = Square::findOrFail((int) $request->integer('sid'));
        $date = $request->string('date')->value();
        $rawStart = $request->input('time_start', '00:00');
        $rawEnd = $request->input('time_end', '01:00');
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
            'booking' => $booking,
            'users' => User::where('status', '!=', 'deleted')->orderBy('alias')->get(['uid', 'alias']),
            'squares' => $squares,
            'reservation' => $reservation,
            'bookedFor' => '',
            'playerNames' => [2 => '', 3 => '', 4 => ''],
            'adminNote' => '',
            'repeatOptions' => self::repeatOptions(),
            'repeatType' => 'once',
            'repeatEndDate' => $date,
            'isCreate' => true,
            'eventFormData' => [
                'event' => new Event(['sid' => $square->sid, 'capacity' => 0]),
                'name' => '',
                'description' => '',
                'notes' => '',
                'date_start' => $date,
                'time_start' => substr($reservation->time_start, 0, 5),
                'date_end' => $date,
                'time_end' => substr($reservation->time_end, 0, 5),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse|Response
    {
        try {
            $data = $this->validateBookingData($request);
            $owner = $this->bookingService->resolveOwner($data['booked_for']);
            [, , $occurrenceStarts, $durationSeconds] = $this->bookingService->buildOccurrenceTimeline($data);
            $this->bookingService->createSeries($data, $owner, $occurrenceStarts, $durationSeconds);
        } catch (BookingValidationException $e) {
            return back()->withErrors(['booking' => $e->getMessage()])->withInput();
        }

        if ($request->boolean('popup')) {
            return response('<script>window.parent.location.reload();</script>')->header('Content-Type', 'text/html');
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
        $reservations = $booking->reservations->sortBy([['date', 'asc'], ['time_start', 'asc']])->values();

        $occurrenceParam = request()->query('occurrence');
        $occurrenceReservation = is_string($occurrenceParam)
            ? $reservations->firstWhere('date', $occurrenceParam)
            : null;

        $showScopeChoice = $booking->isSubscription() && $occurrenceReservation !== null;
        $reservation = $occurrenceReservation ?? $reservations->first();

        $repeatType = $this->detectRepeatType($reservations->all());
        $detectedRepeatType = $repeatType;
        $repeatEndDate = $reservations->last()?->date ?? $reservation?->date;
        $followingRepeatType = $repeatType;
        $followingRepeatEndDate = $repeatEndDate;

        if ($showScopeChoice) {
            $followingReservations = $reservations
                ->filter(fn (Reservation $r): bool => $r->date >= $occurrenceReservation->date)
                ->values()
                ->all();
            $followingRepeatType = $this->detectRepeatType($followingReservations);
            $followingRepeatEndDate = $reservations->last()?->date ?? $occurrenceReservation->date;
            $repeatType = 'once';
            $repeatEndDate = $occurrenceReservation->date;
        }

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
            'repeatOptions' => self::repeatOptions([$detectedRepeatType, $followingRepeatType]),
            'repeatType' => $repeatType,
            'repeatEndDate' => $repeatEndDate,
            'isCreate' => false,
            'showScopeChoice' => $showScopeChoice,
            'occurrenceDate' => $occurrenceReservation?->date,
            'followingRepeatType' => $followingRepeatType,
            'followingRepeatEndDate' => $followingRepeatEndDate,
        ]);
    }

    public function update(Request $request, Booking $booking): RedirectResponse|Response
    {
        $firstReservation = $booking->reservations()->orderBy('date')->orderBy('time_start')->first();

        if (! $firstReservation) {
            return back()->withErrors(['booking' => __('booking.messages.booking_reservation_missing')]);
        }

        try {
            [$occurrenceDate, $scope] = $this->resolveOccurrenceScope($request);
            $data = $this->validateBookingData($request, $this->existingLegacyRepeatTypes($booking, $occurrenceDate));
            $owner = $this->bookingService->resolveOwner($data['booked_for']);
            [, , $occurrenceStarts, $durationSeconds] = $this->bookingService->buildOccurrenceTimeline($data);

            $this->bookingService->updateSeries($booking, $data, $owner, $occurrenceStarts, $durationSeconds, $occurrenceDate, $scope);
        } catch (BookingValidationException $e) {
            return back()->withErrors(['booking' => $e->getMessage()])->withInput();
        }

        if ($request->boolean('popup')) {
            return response('<script>window.parent.location.reload();</script>')->header('Content-Type', 'text/html');
        }

        $redirectTo = $request->string('redirect_to')->trim()->value();
        if ($redirectTo !== '') {
            return redirect()->to($redirectTo)->with('success', __('booking.messages.booking_updated'));
        }

        return redirect()->route('admin.bookings.index')->with('success', __('booking.messages.booking_updated'));
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse|Response
    {
        [$occurrenceDate, $scope] = $this->resolveOccurrenceScope($request);
        $this->bookingService->cancelSingle($booking, $occurrenceDate, $scope);

        if ($request->boolean('popup')) {
            return response('<script>window.parent.location.reload();</script>')->header('Content-Type', 'text/html');
        }

        $redirectTo = $request->string('redirect_to')->trim()->value();
        if ($redirectTo !== '') {
            return redirect()->to($redirectTo)->with('success', __('booking.messages.booking_cancelled'));
        }

        return redirect()->route('admin.bookings.index')->with('success', __('booking.messages.booking_cancelled'));
    }

    public function destroy(Request $request, Booking $booking): RedirectResponse|Response
    {
        [$occurrenceDate, $scope] = $this->resolveOccurrenceScope($request);
        $this->bookingService->deleteSingle($booking, $occurrenceDate, $scope);

        if ($request->boolean('popup')) {
            return response('<script>window.parent.location.reload();</script>')->header('Content-Type', 'text/html');
        }

        $redirectTo = $request->string('redirect_to')->trim()->value();
        if ($redirectTo !== '') {
            return redirect()->to($redirectTo)->with('success', __('booking.messages.booking_deleted'));
        }

        return redirect()->route('admin.bookings.index')->with('success', __('booking.messages.booking_deleted'));
    }

    /** @return array{0: ?string, 1: string} */
    private function resolveOccurrenceScope(Request $request): array
    {
        $occurrence = $request->string('occurrence')->trim()->value();
        $scope = $request->string('edit_scope')->trim()->value();

        return [
            $occurrence === '' ? null : $occurrence,
            in_array($scope, ['single_occurrence', 'this_and_following'], true) ? $scope : 'single_occurrence',
        ];
    }

    /**
     * Legacy repeat types of the existing series (whole series, and from the
     * occurrence on) that may be kept on update although they are no longer offered.
     *
     * @return list<string>
     */
    private function existingLegacyRepeatTypes(Booking $booking, ?string $occurrenceDate): array
    {
        $reservations = $booking->reservations()->orderBy('date')->orderBy('time_start')->get();
        $types = [$this->detectRepeatType($reservations->all())];

        if ($occurrenceDate !== null) {
            $types[] = $this->detectRepeatType(
                $reservations->filter(fn (Reservation $r): bool => $r->date >= $occurrenceDate)->values()->all()
            );
        }

        return array_values(array_intersect(BookingService::LEGACY_REPEAT_KEYS, $types));
    }

    /** @param list<string> $allowedLegacyRepeat */
    private function validateBookingData(Request $request, array $allowedLegacyRepeat = []): array
    {
        $data = $request->validate([
            'booked_for' => ['required', 'string', 'max:120'],
            'sid' => ['required', 'integer', 'exists:bs_squares,sid'],
            'date' => ['required', 'date_format:Y-m-d'],
            'date_end' => ['nullable', 'date_format:Y-m-d'],
            'repeat_type' => ['required', 'in:'.implode(',', array_merge(BookingService::REPEAT_KEYS, $allowedLegacyRepeat))],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['required', 'date_format:H:i'],
            'quantity' => ['required', 'integer', 'in:2,4'],
            'status' => ['nullable', 'in:single,subscription,cancelled'],
            'mitspieler' => ['required', 'string', 'max:255'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        return $data;
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
}
