<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BookingValidationException;
use App\Mail\BookingCancelled;
use App\Mail\BookingConfirmed;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

final class BookingService
{
    public const REPEAT_KEYS = [
        'once', 'daily', 'every_2_days', 'every_3_days', 'every_4_days',
        'every_5_days', 'every_6_days', 'weekly', 'every_2_weeks', 'monthly', 'custom',
    ];

    public function __construct(
        private readonly SquareValidator $validator,
    ) {}

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
    public function resolveOwner(string $bookedFor): array
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

    /**
     * Validate the timeline of a (possibly repeating) booking and expand it into
     * concrete occurrence start times.
     *
     * @return array{0: Carbon, 1: Carbon, 2: list<Carbon>, 3: int} [dateStart, dateEnd, occurrenceStarts, durationSeconds]
     */
    public function buildOccurrenceTimeline(array $data): array
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
     * @param  Carbon[]  $occurrenceStarts
     */
    public function batchCheckConflicts(
        int $sid,
        array $occurrenceStarts,
        int $durationSeconds,
        ?int $excludeBookingId,
    ): ?string {
        if (empty($occurrenceStarts)) {
            return null;
        }

        $dateMin = min($occurrenceStarts)->format('Y-m-d');
        $lastEnd = max($occurrenceStarts)->copy()->addSeconds($durationSeconds);
        $dateMax = $lastEnd->format('Y-m-d');

        // One query: all active reservations for this court in the date range.
        $existingReservations = Reservation::query()
            ->join('bs_bookings', 'bs_bookings.bid', '=', 'bs_reservations.bid')
            ->where('bs_bookings.sid', $sid)
            ->where('bs_bookings.visibility', 'public')
            ->whereIn('bs_bookings.status', Booking::ACTIVE_STATUSES)
            ->whereBetween('bs_reservations.date', [$dateMin, $dateMax])
            ->when($excludeBookingId !== null, fn ($q) => $q->where('bs_bookings.bid', '!=', $excludeBookingId))
            ->select(['bs_reservations.date', 'bs_reservations.time_start', 'bs_reservations.time_end'])
            ->get();

        // One query: all enabled events overlapping the range.
        $existingEvents = Event::query()
            ->where('status', 'enabled')
            ->where(fn ($q) => $q->where('sid', $sid)->orWhereNull('sid'))
            ->where('datetime_start', '<', $lastEnd->format('Y-m-d H:i:s'))
            ->where('datetime_end', '>', min($occurrenceStarts)->format('Y-m-d H:i:s'))
            ->select(['datetime_start', 'datetime_end'])
            ->get();

        foreach ($occurrenceStarts as $occurrenceStart) {
            $occurrenceEnd = $occurrenceStart->copy()->addSeconds($durationSeconds);
            $dateStr = $occurrenceStart->format('Y-m-d');
            $startTimeStr = $occurrenceStart->format('H:i:s');
            $endTimeStr = $occurrenceEnd->format('H:i:s');
            $startDatetimeStr = $occurrenceStart->format('Y-m-d H:i:s');
            $endDatetimeStr = $occurrenceEnd->format('Y-m-d H:i:s');

            $hasOverlap = $existingReservations->contains(
                fn ($res) => $res->date === $dateStr
                    && (string) $res->time_start < $endTimeStr
                    && (string) $res->time_end > $startTimeStr,
            );

            if ($hasOverlap) {
                return __('booking.messages.repeat_booking_conflict');
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

    /**
     * Create a (possibly repeating) booking for a resolved owner, e.g. from an admin
     * form or the API — as opposed to createSingle(), which is the simple self-service
     * single-slot path used by the member-facing booking flow.
     *
     * @param  array{sid: int|string, quantity: int|string, status?: ?string, mitspieler?: ?string, admin_note?: ?string}  $data
     * @param  array{uid: int, ownerName: ?string}  $owner
     * @param  list<Carbon>  $occurrenceStarts
     */
    public function createSeries(array $data, array $owner, array $occurrenceStarts, int $durationSeconds): Booking
    {
        return DB::transaction(function () use ($data, $owner, $occurrenceStarts, $durationSeconds): Booking {
            if (($data['status'] ?? null) !== 'cancelled') {
                // Serialize with concurrent bookings on this square, then check conflicts.
                Square::query()->whereKey((int) $data['sid'])->lockForUpdate()->get();
                $conflictError = $this->batchCheckConflicts((int) $data['sid'], $occurrenceStarts, $durationSeconds, null);
                if ($conflictError !== null) {
                    throw new BookingValidationException($conflictError);
                }
            }

            $booking = Booking::create([
                'uid' => $owner['uid'],
                'sid' => (int) $data['sid'],
                'quantity' => (int) $data['quantity'],
                'status' => ($data['status'] ?? null) === 'cancelled' ? 'cancelled' : (count($occurrenceStarts) > 1 ? 'subscription' : 'single'),
                'status_billing' => 'pending',
                'visibility' => 'public',
                'created' => now()->format('Y-m-d H:i:s'),
            ]);

            $this->insertReservations($booking, $occurrenceStarts, $durationSeconds);

            $this->syncPlayerMeta($booking, [2 => $data['mitspieler'] ?? null]);
            $this->syncBookingMeta($booking, 'owner-name', $owner['ownerName']);
            $this->syncBookingMeta($booking, 'admin-note', $data['admin_note'] ?? null);

            return $booking->fresh(['reservations', 'meta']);
        });
    }

    /**
     * Update a (possibly repeating) booking for a resolved owner, replacing its
     * reservations — the update counterpart to createSeries().
     *
     * @param  array{sid: int|string, quantity: int|string, status?: ?string, mitspieler?: ?string, admin_note?: ?string}  $data
     * @param  array{uid: int, ownerName: ?string}  $owner
     * @param  list<Carbon>  $occurrenceStarts
     */
    public function updateSeries(
        Booking $booking,
        array $data,
        array $owner,
        array $occurrenceStarts,
        int $durationSeconds,
        ?string $occurrenceDate,
        string $scope,
    ): Booking {
        return DB::transaction(function () use ($booking, $data, $owner, $occurrenceStarts, $durationSeconds, $occurrenceDate, $scope): Booking {
            $targetBooking = $this->splitForScope($booking, $occurrenceDate, $scope);

            if (($data['status'] ?? null) !== 'cancelled') {
                // Serialize with concurrent bookings on this square, then check conflicts.
                Square::query()->whereKey((int) $data['sid'])->lockForUpdate()->get();
                $conflictError = $this->batchCheckConflicts((int) $data['sid'], $occurrenceStarts, $durationSeconds, $targetBooking->bid);
                if ($conflictError !== null) {
                    throw new BookingValidationException($conflictError);
                }
            }

            $effectiveStatus = ($data['status'] ?? null) === 'cancelled'
                ? 'cancelled'
                : (count($occurrenceStarts) > 1 ? 'subscription' : 'single');

            $targetBooking->update([
                'uid' => $owner['uid'],
                'sid' => (int) $data['sid'],
                'quantity' => (int) $data['quantity'],
                'status' => $effectiveStatus,
            ]);

            $targetBooking->loadMissing(['reservations.meta']);
            foreach ($targetBooking->reservations as $reservation) {
                $reservation->meta()->delete();
            }
            $targetBooking->reservations()->delete();

            $this->insertReservations($targetBooking, $occurrenceStarts, $durationSeconds);

            $this->syncPlayerMeta($targetBooking, [2 => $data['mitspieler'] ?? null]);
            $this->syncBookingMeta($targetBooking, 'owner-name', $owner['ownerName']);
            $this->syncBookingMeta($targetBooking, 'admin-note', $data['admin_note'] ?? null);

            return $targetBooking->fresh(['reservations', 'meta']);
        });
    }

    /** @param list<Carbon> $occurrenceStarts */
    private function insertReservations(Booking $booking, array $occurrenceStarts, int $durationSeconds): void
    {
        $rows = [];
        foreach ($occurrenceStarts as $occurrenceStart) {
            $rows[] = [
                'bid' => $booking->bid,
                'date' => $occurrenceStart->format('Y-m-d'),
                'time_start' => $occurrenceStart->format('H:i:s'),
                'time_end' => $occurrenceStart->copy()->addSeconds($durationSeconds)->format('H:i:s'),
            ];
        }
        DB::table('bs_reservations')->insert($rows);
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

    public function createSingle(
        User $user,
        Square $square,
        int $quantity,
        Carbon $dateStart,
        Carbon $dateEnd,
        array $meta = [],
    ): Booking {
        $dateEnd = $this->normalizeBookableEnd($square, $dateStart, $dateEnd);
        $this->assertSingleBookingIsValid($user, $square, $quantity, $dateStart, $dateEnd);

        $booking = DB::transaction(function () use ($user, $square, $quantity, $dateStart, $dateEnd, $meta): Booking {
            // Serialize concurrent bookings on this square (row lock as mutex),
            // then re-check the slot so two parallel requests cannot both book it.
            Square::query()->whereKey($square->sid)->lockForUpdate()->get();
            $this->assertSingleBookingIsValid($user, $square, $quantity, $dateStart, $dateEnd);

            $booking = Booking::create([
                'uid' => $user->uid,
                'sid' => $square->sid,
                'status' => 'single',
                'status_billing' => 'pending',
                'visibility' => 'public',
                'quantity' => $quantity,
                'created' => now()->format('Y-m-d H:i:s'),
            ]);

            Reservation::create([
                'bid' => $booking->bid,
                'date' => $dateStart->format('Y-m-d'),
                'time_start' => $dateStart->format('H:i:s'),
                'time_end' => $dateEnd->format('H:i:s'),
            ]);

            foreach ($meta as $entry) {
                $booking->meta()->create($entry);
            }

            return $booking->load('reservations', 'meta');
        });

        if (! empty($user->email) && $this->isMailConfigured()) {
            $email = $user->email;
            dispatch(function () use ($email, $booking): void {
                try {
                    Mail::to($email)->send(new BookingConfirmed($booking));
                } catch (\Throwable) {
                    // Mail errors must not affect the response already sent
                }
            })->afterResponse();
        }

        return $booking;
    }

    /**
     * @param  array<int, string|null>  $playerNames
     * @return array<array{key: string, value: string}>
     */
    public function buildPlayerMeta(array $playerNames): array
    {
        $metaEntries = [];

        foreach ([2, 3, 4] as $index) {
            $name = trim((string) ($playerNames[$index] ?? ''));
            if ($name === '') {
                continue;
            }

            $metaEntries[] = [
                'name' => 'sb-player-name-'.$index,
                'value' => $name,
            ];
        }

        if ($metaEntries === []) {
            return [];
        }

        return [[
            'key' => 'player-names',
            'value' => serialize($metaEntries),
        ]];
    }

    /** @param array<int, string|null> $playerNames */
    public function syncPlayerMeta(Booking $booking, array $playerNames): void
    {
        $meta = $this->buildPlayerMeta($playerNames);
        $row = $booking->meta()->where('key', 'player-names')->first();

        if ($meta === []) {
            if ($row) {
                $row->delete();
            }

            return;
        }

        if ($row) {
            $row->update($meta[0]);

            return;
        }

        $booking->meta()->create($meta[0]);
    }

    /** @param array<int, string|null> $playerNames */
    public function updateSinglePlayers(Booking $booking, User $user, int $quantity, array $playerNames): void
    {
        $booking->loadMissing(['square', 'reservations']);

        $square = $booking->square;
        $reservation = $booking->reservations
            ->sortBy(fn ($reservation): string => (string) $reservation->date.' '.(string) $reservation->time_start)
            ->first();

        if (! $square || ! $reservation) {
            throw new BookingValidationException(__('booking.messages.booking_reservation_missing'));
        }

        $dateStart = Carbon::parse($reservation->date.' '.$reservation->time_start);
        $dateEnd = Carbon::parse($reservation->date.' '.$reservation->time_end);

        $this->assertSingleBookingIsValid($user, $square, $quantity, $dateStart, $dateEnd, (int) $booking->bid);

        DB::transaction(function () use ($booking, $quantity, $playerNames): void {
            $booking->update(['quantity' => $quantity]);
            $this->syncPlayerMeta($booking, $playerNames);
        });
    }

    public function cancelSingle(Booking $booking, ?string $occurrenceDate = null, string $scope = 'single_occurrence'): void
    {
        $target = DB::transaction(function () use ($booking, $occurrenceDate, $scope): Booking {
            $target = $this->splitForScope($booking, $occurrenceDate, $scope);
            $target->update([
                'status' => 'cancelled',
                'status_billing' => 'cancelled',
            ]);

            return $target;
        });

        $target->loadMissing('user');

        $email = $target->user?->email ?? null;
        if (! empty($email) && $this->isMailConfigured()) {
            dispatch(function () use ($email, $target): void {
                try {
                    Mail::to($email)->send(new BookingCancelled($target));
                } catch (\Throwable) {
                    // Mail errors must not affect the response already sent
                }
            })->afterResponse();
        }
    }

    public function canUserCancelSingle(User $user, Booking $booking): bool
    {
        if ($user->can('calendar.cancel-single-bookings') && $booking->status === 'single') {
            return true;
        }

        if ($booking->uid !== $user->uid || $booking->status === 'subscription') {
            return false;
        }

        $booking->loadMissing(['square', 'reservations']);
        $cancelRange = (int) ($booking->square?->range_cancel ?? 0);
        if ($cancelRange <= 0) {
            return false;
        }

        $reservation = $booking->reservations
            ->sortBy(fn ($reservation): string => (string) $reservation->date.' '.(string) $reservation->time_start)
            ->first();
        if (! $reservation) {
            return true;
        }

        $reservationStart = Carbon::parse($reservation->date.' '.$reservation->time_start);

        return $reservationStart->greaterThan(Carbon::now()->addSeconds($cancelRange));
    }

    public function deleteSingle(Booking $booking, ?string $occurrenceDate = null, string $scope = 'single_occurrence'): void
    {
        DB::transaction(function () use ($booking, $occurrenceDate, $scope): void {
            $target = $this->splitForScope($booking, $occurrenceDate, $scope);
            $target->loadMissing(['meta', 'bills', 'reservations.meta']);

            foreach ($target->reservations as $reservation) {
                $reservation->meta()->delete();
            }

            $target->reservations()->delete();
            $target->meta()->delete();
            $target->bills()->delete();
            $target->delete();
        });
    }

    public function splitForScope(Booking $booking, ?string $occurrenceDate, string $scope): Booking
    {
        if ($occurrenceDate === null || ! $booking->isSubscription()) {
            return $booking;
        }

        $booking->loadMissing(['reservations', 'meta']);
        $reservations = $booking->reservations
            ->sortBy([['date', 'asc'], ['time_start', 'asc']])
            ->values();

        if (! $reservations->contains(fn (Reservation $r): bool => $r->date === $occurrenceDate)) {
            return $booking;
        }

        return $scope === 'this_and_following'
            ? $this->splitFromDate($booking, $reservations, $occurrenceDate)
            : $this->splitSingleOccurrence($booking, $reservations, $occurrenceDate);
    }

    /** @param Collection<int, Reservation> $reservations */
    private function splitSingleOccurrence(Booking $booking, Collection $reservations, string $occurrenceDate): Booking
    {
        if ($reservations->count() <= 1) {
            return $booking;
        }

        $moving = $reservations->filter(fn (Reservation $r): bool => $r->date === $occurrenceDate)->values();

        return DB::transaction(function () use ($booking, $moving): Booking {
            $newBooking = $this->cloneBookingShell($booking, $moving->count());
            Reservation::whereIn('rid', $moving->pluck('rid'))->update(['bid' => $newBooking->bid]);
            $this->recalculateStatus($booking);

            return $newBooking->fresh(['reservations', 'meta']);
        });
    }

    /** @param Collection<int, Reservation> $reservations */
    private function splitFromDate(Booking $booking, Collection $reservations, string $occurrenceDate): Booking
    {
        $head = $reservations->filter(fn (Reservation $r): bool => $r->date < $occurrenceDate)->values();
        $tail = $reservations->filter(fn (Reservation $r): bool => $r->date >= $occurrenceDate)->values();

        if ($head->isEmpty()) {
            return $booking;
        }

        return DB::transaction(function () use ($booking, $tail): Booking {
            $newBooking = $this->cloneBookingShell($booking, $tail->count());
            Reservation::whereIn('rid', $tail->pluck('rid'))->update(['bid' => $newBooking->bid]);
            $this->recalculateStatus($booking);

            return $newBooking->fresh(['reservations', 'meta']);
        });
    }

    private function cloneBookingShell(Booking $booking, int $movingReservationCount): Booking
    {
        $newBooking = Booking::create([
            'uid' => $booking->uid,
            'sid' => $booking->sid,
            'status' => $movingReservationCount > 1 ? 'subscription' : 'single',
            'status_billing' => $booking->status_billing,
            'visibility' => $booking->visibility,
            'quantity' => $booking->quantity,
            'created' => now()->format('Y-m-d H:i:s'),
        ]);

        foreach (['owner-name', 'admin-note', 'player-names'] as $key) {
            $value = $booking->meta->firstWhere('key', $key)?->value;
            if ($value !== null) {
                $newBooking->meta()->create(['key' => $key, 'value' => $value]);
            }
        }

        return $newBooking;
    }

    private function recalculateStatus(Booking $booking): void
    {
        $remaining = $booking->reservations()->count();
        $booking->update(['status' => $remaining > 1 ? 'subscription' : 'single']);
    }

    private function assertSingleBookingIsValid(
        User $user,
        Square $square,
        int $quantity,
        Carbon $dateStart,
        Carbon $dateEnd,
        ?int $excludeBookingId = null,
    ): void {
        $result = $this->validator->validate($square, $user, $quantity, $dateStart, $dateEnd);

        if (! $result->isValid()) {
            throw new BookingValidationException($result->getError());
        }

        if ($this->hasOverlappingBooking($square, $dateStart, $dateEnd, $excludeBookingId)) {
            throw new BookingValidationException(__('booking.messages.slot_occupied'));
        }

        if ($this->hasEventConflict($square, $dateStart, $dateEnd)) {
            throw new BookingValidationException(__('booking.messages.slot_blocked_by_event'));
        }
    }

    /** A cell holds at most one booking: any overlapping active booking blocks the slot. */
    private function hasOverlappingBooking(
        Square $square,
        Carbon $start,
        Carbon $end,
        ?int $excludeBookingId = null,
    ): bool {
        $query = Booking::query()
            ->join('bs_reservations', 'bs_reservations.bid', '=', 'bs_bookings.bid')
            ->where('bs_bookings.sid', $square->sid)
            ->where('bs_bookings.visibility', 'public')
            ->whereIn('bs_bookings.status', Booking::ACTIVE_STATUSES)
            ->where('bs_reservations.date', $start->format('Y-m-d'))
            ->where('bs_reservations.time_start', '<', $end->format('H:i:s'))
            ->where('bs_reservations.time_end', '>', $start->format('H:i:s'));

        if ($excludeBookingId !== null) {
            $query->where('bs_bookings.bid', '!=', $excludeBookingId);
        }

        return $query->exists();
    }

    private function hasEventConflict(Square $square, Carbon $start, Carbon $end): bool
    {
        return Event::query()
            ->where('status', 'enabled')
            ->where(function ($query) use ($square): void {
                $query->where('sid', $square->sid)->orWhereNull('sid');
            })
            ->where('datetime_start', '<', $end->format('Y-m-d H:i:s'))
            ->where('datetime_end', '>', $start->format('Y-m-d H:i:s'))
            ->exists();
    }

    private function normalizeBookableEnd(Square $square, Carbon $dateStart, Carbon $dateEnd): Carbon
    {
        $minimumSeconds = (int) $square->time_block_bookable;
        if ($minimumSeconds <= 0) {
            return $dateEnd;
        }

        $requestedSeconds = (int) $dateStart->diffInSeconds($dateEnd);
        if ($requestedSeconds >= $minimumSeconds) {
            return $dateEnd;
        }

        return $dateStart->copy()->addSeconds($minimumSeconds);
    }

    private function isMailConfigured(): bool
    {
        $mailer = config('mail.default', 'log');
        if ($mailer === 'log' || $mailer === 'array') {
            return false;
        }
        $host = config('mail.mailers.smtp.host', '');

        return ! in_array($host, ['', 'localhost', '127.0.0.1'], true);
    }
}
