<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Reservation;
use App\Models\Square;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Query helper for reservations — date-range lookups and slot overlap detection.
 *
 * All queries filter by BookingStatus::Enabled to exclude cancelled/disabled bookings.
 */
final class ReservationService
{
    /**
     * Get all active reservations within a date range (inclusive).
     *
     * @return Collection<int, Reservation>
     */
    public function getInRange(Carbon $from, Carbon $to): Collection
    {
        return Reservation::query()
            ->join('bs_bookings', 'bs_bookings.bid', '=', 'bs_reservations.bid')
            ->where('bs_bookings.status', BookingStatus::Enabled->value)
            ->whereBetween('bs_reservations.date', [
                $from->copy()->startOfDay()->timestamp,
                $to->copy()->endOfDay()->timestamp,
            ])
            ->select('bs_reservations.*')
            ->get();
    }

    /**
     * Get active reservations for a specific court in a date range.
     *
     * @return Collection<int, Reservation>
     */
    public function getInRangeBySquare(Square $square, Carbon $from, Carbon $to): Collection
    {
        return Reservation::query()
            ->join('bs_bookings', 'bs_bookings.bid', '=', 'bs_reservations.bid')
            ->where('bs_bookings.status', BookingStatus::Enabled->value)
            ->where('bs_bookings.sid', $square->sid)
            ->whereBetween('bs_reservations.date', [
                $from->copy()->startOfDay()->timestamp,
                $to->copy()->endOfDay()->timestamp,
            ])
            ->select('bs_reservations.*')
            ->get();
    }

    /**
     * Check whether a time slot is already taken on a given court.
     *
     * Uses half-open interval logic: [time_start, time_end). Adjacent slots do NOT overlap.
     *
     * @param Square   $square           Court to check
     * @param Carbon   $date             Calendar date
     * @param int      $timeStart        Seconds from midnight (inclusive start)
     * @param int      $timeEnd          Seconds from midnight (exclusive end)
     * @param int|null $excludeBookingId Booking ID to exclude (for update scenarios)
     */
    public function hasOverlap(
        Square $square,
        Carbon $date,
        int $timeStart,
        int $timeEnd,
        ?int $excludeBookingId,
    ): bool {
        $query = Reservation::query()
            ->join('bs_bookings', 'bs_bookings.bid', '=', 'bs_reservations.bid')
            ->where('bs_bookings.status', BookingStatus::Enabled->value)
            ->where('bs_bookings.sid', $square->sid)
            ->where('bs_reservations.date', $date->copy()->startOfDay()->timestamp)
            ->where('bs_reservations.time_start', '<', $timeEnd)
            ->where('bs_reservations.time_end', '>', $timeStart);

        if ($excludeBookingId !== null) {
            $query->where('bs_bookings.bid', '!=', $excludeBookingId);
        }

        return $query->exists();
    }
}
