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
    public function __construct(
        private readonly SquareValidator $validator,
    ) {}

    public function createSingle(
        User $user,
        Square $square,
        int $quantity,
        Carbon $dateStart,
        Carbon $dateEnd,
        array $bills = [],
        array $meta = [],
    ): Booking {
        $dateEnd = $this->normalizeBookableEnd($square, $dateStart, $dateEnd);
        $this->assertSingleBookingIsValid($user, $square, $quantity, $dateStart, $dateEnd);

        $booking = DB::transaction(function () use ($user, $square, $quantity, $dateStart, $dateEnd, $bills, $meta): Booking {
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

            foreach ($bills as $bill) {
                $booking->bills()->create($bill);
            }

            foreach ($meta as $entry) {
                $booking->meta()->create($entry);
            }

            return $booking->load('reservations', 'bills', 'meta');
        });

        if (!empty($user->email) && $this->isMailConfigured()) {
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
        if (!empty($email) && $this->isMailConfigured()) {
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
        return !in_array($host, ['', 'localhost', '127.0.0.1'], true);
    }
}
