<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BookingValidationException;
use App\Exceptions\PlayerExchangeException;
use App\Jobs\SendPlayerSearchMails;
use App\Mail\BookingCancelled;
use App\Mail\BookingConfirmed;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use App\Services\Notifications\PlayerSearchEvents;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

final class BookingService
{
    /** Repeat types offered for new input. */
    public const REPEAT_KEYS = ['once', 'weekly', 'every_2_weeks'];

    /**
     * Repeat types that only exist in legacy series: still displayed, edited and
     * deleted, but no longer offered for new bookings.
     */
    public const LEGACY_REPEAT_KEYS = [
        'daily', 'every_2_days', 'every_3_days', 'every_4_days',
        'every_5_days', 'every_6_days', 'monthly', 'custom',
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
    public function resolveOwner(string $bookedFor, ?Booking $existing = null): array
    {
        $name = trim($bookedFor);
        if ($existing !== null && mb_strtolower($name) === mb_strtolower(trim($existing->owner_label))) {
            return ['uid' => (int) $existing->uid, 'ownerName' => $existing->meta->firstWhere('key', 'owner-name')?->value];
        }

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
        if ($repeatType === 'custom') {
            // Irregular legacy series cannot be regenerated from a rule; saving them
            // would silently collapse them to a single occurrence.
            throw new BookingValidationException(__('booking.validation.custom_repeat_not_editable'));
        }

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

    /** @return array{0: Carbon, 1: Carbon, 2: list<Carbon>, 3: int} */
    public function buildUpdateTimeline(Booking $booking, array $data, ?string $occurrenceDate, string $scope): array
    {
        if ($data['repeat_type'] !== 'custom') {
            return $this->buildOccurrenceTimeline($data);
        }
        $reservations = $booking->reservations()->orderBy('date')->orderBy('time_start')->get()
            ->filter(fn (Reservation $r): bool => $occurrenceDate === null || ($scope === 'this_and_following' ? $r->date >= $occurrenceDate : $r->date === $occurrenceDate))->values();
        $first = $reservations->first();
        $last = $reservations->last();
        if (! $first || $this->detectRepeatType($reservations->all()) !== 'custom'
            || $data['date'] !== $first->date || ($data['date_end'] ?? '') !== $last->date
            || $data['time_start'] !== substr((string) $first->time_start, 0, 5)
            || $data['time_end'] !== substr((string) $first->time_end, 0, 5)) {
            throw new BookingValidationException(__('booking.validation.custom_repeat_not_editable'));
        }
        $start = Carbon::parse($first->date.' '.$first->time_start);
        $end = Carbon::parse($first->date.' '.$first->time_end);

        return [$start, $end, $reservations->map(fn (Reservation $r): Carbon => Carbon::parse($r->date.' '.$r->time_start))->all(), (int) $start->diffInSeconds($end)];
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
     * Derive the repeat type of a series from its reservation dates.
     *
     * @param  list<Reservation>  $reservations  ordered by date
     */
    public function detectRepeatType(array $reservations): string
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

    /**
     * Legacy repeat types of an existing series (whole series, and from the
     * occurrence on) that may be kept on update although they are no longer offered.
     * Always derived from the stored reservations, never from request input.
     *
     * @return list<string>
     */
    public function legacyRepeatTypesOf(Booking $booking, ?string $occurrenceDate): array
    {
        $reservations = $booking->reservations()->orderBy('date')->orderBy('time_start')->get();
        $types = [$this->detectRepeatType($reservations->all())];

        if ($occurrenceDate !== null) {
            $types[] = $this->detectRepeatType(
                $reservations->filter(fn (Reservation $r): bool => $r->date >= $occurrenceDate)->values()->all()
            );
        }

        return array_values(array_intersect(self::LEGACY_REPEAT_KEYS, $types));
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
            ->lockForUpdate()
            ->get();

        // One query: all enabled events overlapping the range.
        $existingEvents = Event::query()
            ->where('status', 'enabled')
            ->where(fn ($q) => $q->where('sid', $sid)->orWhereNull('sid'))
            ->where('datetime_start', '<', $lastEnd->format('Y-m-d H:i:s'))
            ->where('datetime_end', '>', min($occurrenceStarts)->format('Y-m-d H:i:s'))
            ->select(['datetime_start', 'datetime_end'])
            ->lockForUpdate()
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
     * @param  array{sid: int|string, quantity: int|string, repeat_type?: string, date?: string, date_end?: ?string, time_start?: string, time_end?: string, mitspieler?: ?string, player_names?: array<int, string|null>, admin_note?: ?string}  $data
     * @param  array{uid: int, ownerName: ?string}  $owner
     * @param  list<Carbon>  $occurrenceStarts
     */
    public function createSeries(array $data, array $owner, array $occurrenceStarts, int $durationSeconds): Booking
    {
        return DB::transaction(function () use ($data, $owner, $occurrenceStarts, $durationSeconds): Booking {
            // Serialize with concurrent bookings on this square, then check conflicts.
            Square::query()->whereKey((int) $data['sid'])->lockForUpdate()->get();
            $conflictError = $this->batchCheckConflicts((int) $data['sid'], $occurrenceStarts, $durationSeconds, null);
            if ($conflictError !== null) {
                throw new BookingValidationException($conflictError);
            }

            $booking = Booking::create([
                'uid' => $owner['uid'],
                'sid' => (int) $data['sid'],
                'quantity' => (int) $data['quantity'],
                // The status follows from the data (one slot or a series); it is never taken from input.
                'status' => count($occurrenceStarts) > 1 ? 'subscription' : 'single',
                'status_billing' => 'pending',
                'visibility' => 'public',
                'created' => now()->format('Y-m-d H:i:s'),
            ]);

            $this->insertReservations($booking, $occurrenceStarts, $durationSeconds);

            $this->syncPlayerMeta($booking, $data['player_names'] ?? [2 => $data['mitspieler'] ?? null]);
            $this->syncBookingMeta($booking, 'owner-name', $owner['ownerName']);
            $this->syncBookingMeta($booking, 'admin-note', $data['admin_note'] ?? null);

            return $booking->fresh(['reservations', 'meta']);
        });
    }

    /**
     * Update a (possibly repeating) booking for a resolved owner, replacing its
     * reservations — the update counterpart to createSeries().
     *
     * @param  array{sid: int|string, quantity: int|string, repeat_type?: string, date?: string, date_end?: ?string, time_start?: string, time_end?: string, mitspieler?: ?string, player_names?: array<int, string|null>, admin_note?: ?string}  $data
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
            // Booking row lock first, the mutex of join, leave, lapse and the owner edit; then
            // the current state (a lapse or cancel may have won the lock since the form loaded).
            Booking::query()->whereKey($booking->bid)->lockForUpdate()->first();
            $booking->refresh();

            if (($data['repeat_type'] ?? '') === 'custom') {
                $this->buildUpdateTimeline($booking, $data, $occurrenceDate, $scope);
            }
            $targetBooking = $this->splitForScope($booking, $occurrenceDate, $scope);
            $preserveSchedule = ($data['repeat_type'] ?? '') === 'custom';

            // A cancelled booking stays cancelled (only the cancel action sets that) and holds no slot to check.
            $isCancelled = $targetBooking->status === 'cancelled';

            if (! $isCancelled) {
                // Serialize with concurrent bookings on this square, then check conflicts.
                Square::query()->whereKey((int) $data['sid'])->lockForUpdate()->get();
                $conflictError = null;
                if ($preserveSchedule) {
                    foreach ($targetBooking->reservations()->get() as $reservation) {
                        $start = Carbon::parse($reservation->date.' '.$reservation->time_start);
                        $duration = (int) $start->diffInSeconds(Carbon::parse($reservation->date.' '.$reservation->time_end));
                        $conflictError = $this->batchCheckConflicts((int) $data['sid'], [$start], $duration, $targetBooking->bid);
                        if ($conflictError !== null) {
                            break;
                        }
                    }
                } else {
                    $conflictError = $this->batchCheckConflicts((int) $data['sid'], $occurrenceStarts, $durationSeconds, $targetBooking->bid);
                }
                if ($conflictError !== null) {
                    throw new BookingValidationException($conflictError);
                }
            }

            $exchange = app(PlayerExchangeService::class);
            $search = $isCancelled ? null : $exchange->searchBindingAdminEdit($targetBooking);
            if ($search !== null) {
                // A player search binds the booking: time, repeat and booker stay, joined members
                // keep their places; court, own partners and quantity may change.
                $slots = array_map(fn (Carbon $s): array => [
                    $s->format('Y-m-d'), $s->format('H:i:s'), $s->copy()->addSeconds($durationSeconds)->format('H:i:s'),
                ], $occurrenceStarts);
                sort($slots);

                try {
                    $exchange->applyAdminEdit(
                        $targetBooking->load(['reservations', 'meta']), $search, (int) $owner['uid'], $slots,
                        (int) $data['quantity'], $data['player_names'] ?? [2 => $data['mitspieler'] ?? null],
                    );
                } catch (PlayerExchangeException $e) {
                    throw new BookingValidationException($e->getMessage());
                }

                $targetBooking->update(['sid' => (int) $data['sid']]);
                $this->syncBookingMeta($targetBooking, 'owner-name', $owner['ownerName']);
                $this->syncBookingMeta($targetBooking, 'admin-note', $data['admin_note'] ?? null);

                return $targetBooking->fresh(['reservations', 'meta']);
            }

            $effectiveStatus = $isCancelled
                ? 'cancelled'
                : (count($occurrenceStarts) > 1 ? 'subscription' : 'single');

            $targetBooking->update([
                'uid' => $owner['uid'],
                'sid' => (int) $data['sid'],
                'quantity' => (int) $data['quantity'],
                'status' => $effectiveStatus,
            ]);

            if (! $preserveSchedule) {
                $targetBooking->loadMissing(['reservations.meta']);
                foreach ($targetBooking->reservations as $reservation) {
                    $reservation->meta()->delete();
                }
                $targetBooking->reservations()->delete();
                $this->insertReservations($targetBooking, $occurrenceStarts, $durationSeconds);
            }

            $this->syncPlayerMeta($targetBooking, $data['player_names'] ?? [2 => $data['mitspieler'] ?? null]);
            $this->syncBookingMeta($targetBooking, 'owner-name', $owner['ownerName']);
            $this->syncBookingMeta($targetBooking, 'admin-note', $data['admin_note'] ?? null);

            return $targetBooking->fresh(['reservations', 'meta']);
        }, self::TRANSACTION_ATTEMPTS);
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

    /** Deadlocks and lock wait timeouts of an owner edit are retried this often. */
    private const TRANSACTION_ATTEMPTS = 3;

    public function createSingle(
        User $user,
        Square $square,
        int $quantity,
        Carbon $dateStart,
        Carbon $dateEnd,
        array $meta = [],
        int $searchPlayers = 0,
    ): Booking {
        $dateEnd = $this->normalizeBookableEnd($square, $dateStart, $dateEnd);
        $this->assertSingleBookingIsValid($user, $square, $quantity, $dateStart, $dateEnd);

        $searchId = null;
        $booking = DB::transaction(function () use ($user, $square, $quantity, $dateStart, $dateEnd, $meta, $searchPlayers, &$searchId): Booking {
            // Serialize concurrent bookings on this square (row lock as mutex),
            // then re-check the slot so two parallel requests cannot both book it.
            // The member row next (same mutex as a join of this member, lock order court/booking
            // row, then member row), before any plain read: the check below then sees a join
            // that committed while this booking waited.
            Square::query()->whereKey($square->sid)->lockForUpdate()->get();
            $availability = app(MemberAvailability::class);
            $availability->lockMember($user);
            $this->assertSingleBookingIsValid($user, $square, $quantity, $dateStart, $dateEnd);

            // A member who joined a player search at that time already plays (PX-02).
            if ($availability->joinedAt($user, $dateStart, $dateEnd)) {
                throw new BookingValidationException(__('booking.messages.player_exchange.booking_busy'));
            }

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

            $booking->load('reservations', 'meta');

            if ($searchPlayers > 0) {
                try {
                    $searchId = app(PlayerExchangeService::class)->openSearch($booking, $searchPlayers)->id;
                } catch (PlayerExchangeException $e) {
                    throw new BookingValidationException($e->getMessage());
                }
            }

            return $booking;
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

        // The exchange mails go out after the response, outside the booking transaction.
        if ($searchId !== null && $this->isMailConfigured()) {
            dispatch(new SendPlayerSearchMails($searchId))->afterResponse();
        }

        // WhatsApp in addition to the mail, also without a mail server: the event is fired after the response
        // (in the console at once); its listener only writes outbox rows and never fails the booking.
        if ($searchId !== null) {
            PlayerSearchEvents::started($searchId);
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

    /**
     * The booker changes Einzel/Doppel and his partner names. Runs under the booking row lock,
     * the same mutex as join, leave, ending and the lapse of a player search. Without a live
     * search the names are replaced as they always were. With one, members who joined via the
     * exchange keep their place and name, the typed names go to the free slots, and a quantity
     * too small for typed partners plus joined members is refused (BookingValidationException),
     * and so is an edit of a booking that was cancelled or lapsed before the lock was taken.
     *
     * The member form has one partner field (slot 2). Partners in slots 3/4 only come from the
     * admin form; without a search they are replaced as before, with a search they are the
     * booker's own names and the single field replaces them too (joined members stay).
     *
     * @param  array<int, string|null>  $playerNames
     */
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
            // Booking row lock first, before any plain read: the checks below then see every
            // join or leave that committed while this edit waited for the lock.
            $locked = Booking::query()->whereKey($booking->bid)->lockForUpdate()->first();

            // The caller checked a snapshot: the lapse or a cancel may have won the lock since.
            if ($locked === null || ! in_array($locked->status, Booking::ACTIVE_STATUSES, true)) {
                throw new BookingValidationException(__('booking.messages.booking_not_editable'));
            }

            $exchange = app(PlayerExchangeService::class);
            $search = $exchange->liveSearch($locked);

            // Writes go to the row loaded in this attempt: after a retried deadlock the
            // caller's model would believe the quantity is saved and write nothing.
            if ($search === null) {
                $locked->update(['quantity' => $quantity]);
                $this->syncPlayerMeta($locked, $playerNames);

                return;
            }

            ksort($playerNames);
            $ownNames = array_values(array_filter(
                array_map(fn ($name): string => trim((string) $name), $playerNames),
                fn (string $name): bool => $name !== '',
            ));

            try {
                $exchange->applyOwnerEdit($locked->load('meta'), $search, $quantity, $ownNames);
            } catch (PlayerExchangeException $e) {
                throw new BookingValidationException($e->getMessage());
            }

        }, self::TRANSACTION_ATTEMPTS);

        $booking->setAttribute('quantity', $quantity)->syncOriginalAttribute('quantity');
    }

    /**
     * Cancel the booking (or one occurrence of a subscription). A player search of the booking
     * is cancelled with it and its joined players are mailed. $notifyOwner false skips the
     * cancel confirmation to the booker (the lapse of a player search mails him itself).
     *
     * Decided on the locked booking row, not on the copy the caller loaded before the lock: a
     * booking that is gone or already cancelled (by the lapse of its search or a parallel
     * cancel) stays as it is and nobody gets a cancel mail again.
     *
     * @return bool true when this call cancelled the booking (or the occurrence)
     */
    public function cancelSingle(Booking $booking, ?string $occurrenceDate = null, string $scope = 'single_occurrence', bool $notifyOwner = true): bool
    {
        $target = DB::transaction(function () use ($booking, $occurrenceDate, $scope): ?Booking {
            // Booking row lock first: the same mutex as joins and the lapse of its player search.
            $locked = Booking::query()->whereKey($booking->bid)->lockForUpdate()->first();
            if ($locked === null) {
                return null;
            }

            $exchange = app(PlayerExchangeService::class);
            if (! in_array($locked->status, Booking::ACTIVE_STATUSES, true)) {
                // Already cancelled. A live search the exchange missed is still closed and its
                // players are told (once: a closed search is left alone).
                $exchange->onBookingCancelled($locked);

                return null;
            }

            $target = $this->splitForScope($locked, $occurrenceDate, $scope);
            $target->update([
                'status' => 'cancelled',
                'status_billing' => 'cancelled',
            ]);

            // A subscription never carries a search (openSearch refuses it), so the split-off
            // occurrence is the booking to look at; the rest of the series keeps running.
            $exchange->onBookingCancelled($target);

            return $target;
        });

        if ($target === null) {
            return false;
        }
        if ((int) $target->bid === (int) $booking->bid) {
            // The caller's copy shows the cancel, as before.
            $booking->setRawAttributes($target->getAttributes(), true);
        }

        if (! $notifyOwner) {
            return true;
        }

        $target->loadMissing('user');

        $email = $target->user->email ?? null;
        if (! empty($email) && $this->isMailConfigured()) {
            dispatch(function () use ($email, $target): void {
                try {
                    Mail::to($email)->send(new BookingCancelled($target));
                } catch (\Throwable) {
                    // Mail errors must not affect the response already sent
                }
            })->afterResponse();
        }

        return true;
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
        $cancelRange = $booking->square !== null ? app(BookingRules::class)->value(BookingRules::RANGE_CANCEL, $booking->square) : 0;
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

    /**
     * Delete the booking (or one occurrence of a subscription) for good. Its player search goes
     * with it in the same transaction: a live search tells its joined players like a cancel
     * does, then the search and signup rows are removed, so nothing is left behind.
     */
    public function deleteSingle(Booking $booking, ?string $occurrenceDate = null, string $scope = 'single_occurrence'): void
    {
        DB::transaction(function () use ($booking, $occurrenceDate, $scope): void {
            // Booking row lock first: the same mutex as joins, cancels and the lapse of its search.
            $locked = Booking::query()->whereKey($booking->bid)->lockForUpdate()->first();
            if ($locked === null) {
                return;
            }

            $target = $this->splitForScope($locked, $occurrenceDate, $scope);
            app(PlayerExchangeService::class)->onBookingDeleted($target);
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
        if ($occurrenceDate === null) {
            return $booking;
        }

        $booking->load(['reservations', 'meta']);
        $reservations = $booking->reservations
            ->sortBy([['date', 'asc'], ['time_start', 'asc']])
            ->values();

        if (! in_array($scope, ['single_occurrence', 'this_and_following'], true)
            || ! $reservations->contains(fn (Reservation $r): bool => $r->date === $occurrenceDate)) {
            throw new BookingValidationException(__('booking.validation.occurrence_missing'));
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
        $result = $this->validator->validate($square, $user, $quantity, $dateStart, $dateEnd, $excludeBookingId);

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
        if (! $dateEnd->greaterThan($dateStart)) {
            throw new BookingValidationException(__('booking.validation.end_time_after_start'));
        }
        $minimumSeconds = app(BookingRules::class)->minimumSeconds($square);
        if ($minimumSeconds <= 0) {
            return $dateEnd;
        }

        $requestedSeconds = (int) $dateStart->diffInSeconds($dateEnd);
        if ($requestedSeconds >= $minimumSeconds) {
            return $dateEnd;
        }

        return $dateStart->copy()->addSeconds($minimumSeconds);
    }

    /** Whether a real SMTP mailer is configured; without it no booking or exchange mail is sent. */
    public function isMailConfigured(): bool
    {
        $mailer = config('mail.default', 'log');
        if ($mailer === 'log' || $mailer === 'array') {
            return false;
        }
        $host = config('mail.mailers.smtp.host', '');

        return ! in_array($host, ['', 'localhost', '127.0.0.1'], true);
    }
}
