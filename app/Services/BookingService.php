<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BillingStatus;
use App\Enums\BookingStatus;
use App\Enums\Visibility;
use App\Exceptions\BookingValidationException;
use App\Models\Booking;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Application service for creating and cancelling court bookings.
 *
 * All DB writes are wrapped in transactions for atomicity.
 * Validation is delegated to SquareValidator before any DB write.
 */
final class BookingService
{
    public function __construct(
        private readonly SquareValidator $validator,
    ) {}

    /**
     * Create a single booking with one reservation.
     *
     * @param User   $user      Booking owner
     * @param Square $square    Court to book
     * @param int    $quantity  Number of players
     * @param Carbon $dateStart Start datetime
     * @param Carbon $dateEnd   End datetime
     * @param array<array{spid?: int|null, price: int, description?: string}> $bills Optional line-item bills
     * @param array<array{meta_key: string, meta_value: string}>               $meta  Optional booking metadata (e.g. player names)
     *
     * @throws BookingValidationException When SquareValidator rejects the booking
     */
    public function createSingle(
        User $user,
        Square $square,
        int $quantity,
        Carbon $dateStart,
        Carbon $dateEnd,
        array $bills = [],
        array $meta = [],
    ): Booking {
        $result = $this->validator->validate($square, $user, $quantity, $dateStart, $dateEnd);

        if (!$result->isValid()) {
            throw new BookingValidationException($result->getError());
        }

        return DB::transaction(function () use ($user, $square, $quantity, $dateStart, $dateEnd, $bills, $meta): Booking {
            $booking = Booking::create([
                'uid'            => $user->uid,
                'sid'            => $square->sid,
                'status'         => BookingStatus::Enabled->value,
                'status_billing' => BillingStatus::Pending->value,
                'visibility'     => Visibility::Public->value,
                'quantity'       => $quantity,
                'created'        => now()->timestamp,
                'updated'        => now()->timestamp,
            ]);

            Reservation::create([
                'bid'        => $booking->bid,
                'date'       => $dateStart->copy()->startOfDay()->timestamp,
                'time_start' => $dateStart->secondsSinceMidnight(),
                'time_end'   => $dateEnd->secondsSinceMidnight(),
            ]);

            foreach ($bills as $bill) {
                $booking->bills()->create($bill);
            }

            foreach ($meta as $entry) {
                $booking->meta()->create($entry);
            }

            return $booking->load('reservations', 'bills', 'meta');
        });
    }

    /**
     * Cancel a booking — sets status to Disabled and billing status to Cancelled.
     */
    public function cancelSingle(Booking $booking): void
    {
        $booking->update([
            'status'         => BookingStatus::Disabled->value,
            'status_billing' => BillingStatus::Cancelled->value,
            'updated'        => now()->timestamp,
        ]);
    }
}
