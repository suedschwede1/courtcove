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
            // Re-check inside the transaction so stale forms cannot overbook a slot.
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

        if (!empty($user->email)) {
            $email = $user->email;
            dispatch(function () use ($email, $booking): void {
                try {
                    Mail::to($email)->send(new BookingConfirmed($booking));
                } catch (\Throwable) {
                    // Mail-Fehler dürfen die gesendete Response nicht beeinflussen
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

    public function cancelSingle(Booking $booking): void
    {
        $booking->loadMissing('user');

        $booking->update([
            'status' => 'cancelled',
            'status_billing' => 'cancelled',
        ]);

        $email = $booking->user?->email ?? null;
        if (!empty($email)) {
            dispatch(function () use ($email, $booking): void {
                try {
                    Mail::to($email)->send(new BookingCancelled($booking));
                } catch (\Throwable) {
                    // Mail-Fehler dürfen die gesendete Response nicht beeinflussen
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

    public function deleteSingle(Booking $booking): void
    {
        DB::transaction(function () use ($booking): void {
            $booking->loadMissing(['meta', 'bills', 'reservations.meta']);

            foreach ($booking->reservations as $reservation) {
                $reservation->meta()->delete();
            }

            $booking->reservations()->delete();
            $booking->meta()->delete();
            $booking->bills()->delete();
            $booking->delete();
        });
    }

    private function assertSingleBookingIsValid(
        User $user,
        Square $square,
        int $quantity,
        Carbon $dateStart,
        Carbon $dateEnd,
    ): void {
        $result = $this->validator->validate($square, $user, $quantity, $dateStart, $dateEnd);

        if (! $result->isValid()) {
            throw new BookingValidationException($result->getError());
        }

        if (! $this->hasEnoughCapacity($square, $quantity, $dateStart, $dateEnd)) {
            throw new BookingValidationException(__('booking.messages.slot_occupied'));
        }

        if ($this->hasEventConflict($square, $dateStart, $dateEnd)) {
            throw new BookingValidationException(__('booking.messages.slot_blocked_by_event'));
        }
    }

    private function hasEnoughCapacity(Square $square, int $requestedQuantity, Carbon $start, Carbon $end): bool
    {
        $overlappingQuantity = Booking::query()
            ->join('bs_reservations', 'bs_reservations.bid', '=', 'bs_bookings.bid')
            ->where('bs_bookings.sid', $square->sid)
            ->where('bs_bookings.visibility', 'public')
            ->whereIn('bs_bookings.status', Booking::ACTIVE_STATUSES)
            ->where('bs_reservations.date', $start->format('Y-m-d'))
            ->where('bs_reservations.time_start', '<', $end->format('H:i:s'))
            ->where('bs_reservations.time_end', '>', $start->format('H:i:s'))
            ->sum('bs_bookings.quantity');

        if ((int) $overlappingQuantity > 0 && (int) $square->capacity_heterogenic === 0) {
            return false;
        }

        return (int) $square->capacity >= ((int) $overlappingQuantity + $requestedQuantity);
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
}
