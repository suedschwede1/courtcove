<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\Reservation;
use App\Models\Square;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Query helper for reservations - date-range lookups and slot overlap detection.
 *
 * LEGACY DB FACTS (do not change):
 *   bs_bookings.status      = 'single' | 'subscription' (active) | 'cancelled' (inactive)
 *   bs_reservations.date    = DATE column stored as 'Y-m-d' string, NOT a Unix timestamp
 *   bs_reservations.time_start = TIME column stored as 'HH:MM:SS' string, NOT seconds
 *   bs_reservations.time_end   = TIME column stored as 'HH:MM:SS' string, NOT seconds
 */
final class ReservationService
{
    /** Active booking statuses - plain strings, NOT enums. */
    private const ACTIVE_STATUSES = Booking::ACTIVE_STATUSES;

    /**
     * Get all active reservations within a date range (inclusive).
     *
     * @return Collection<int, Reservation>
     */
    public function getInRange(Carbon $from, Carbon $to): Collection
    {
        return Reservation::query()
            ->whereBetween('date', [
                $from->format('Y-m-d'),
                $to->format('Y-m-d'),
            ])
            ->whereHas('booking', fn($query) => $query->whereIn('status', self::ACTIVE_STATUSES))
            ->get();
    }

    /**
     * Get active reservations for a specific court within a date range.
     *
     * @return Collection<int, Reservation>
     */
    public function getInRangeBySquare(Square $square, Carbon $from, Carbon $to): Collection
    {
        return Reservation::query()
            ->whereBetween('date', [
                $from->format('Y-m-d'),
                $to->format('Y-m-d'),
            ])
            ->whereHas('booking', fn($query) => $query
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->where('sid', $square->sid))
            ->get();
    }

    /**
     * Calendar-specific reservation query for many courts at once.
     * Loads everything needed by the view in one pass.
     *
     * @param list<int> $squareIds
     * @return Collection<int, Reservation>
     */
    public function getCalendarReservations(array $squareIds, Carbon $from, Carbon $to): Collection
    {
        return Reservation::query()
            ->with([
                'booking.user',
                'booking.meta',
            ])
            ->whereBetween('date', [
                $from->format('Y-m-d'),
                $to->format('Y-m-d'),
            ])
            ->whereHas('booking', fn($query) => $query
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->whereIn('sid', $squareIds))
            ->orderBy('date')
            ->orderBy('time_start')
            ->get();
    }

    /**
     * Check whether a time slot is already taken on a given court.
     *
     * Uses half-open interval logic: [time_start, time_end). Adjacent slots do NOT overlap.
     * Time values use 'HH:MM:SS' string format matching the TIME columns in the legacy DB.
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
        $tsStr = sprintf('%02d:%02d:00', intdiv($timeStart, 3600), ($timeStart % 3600) / 60);
        $teStr = sprintf('%02d:%02d:00', intdiv($timeEnd, 3600), ($timeEnd % 3600) / 60);

        $query = Reservation::query()
            ->join('bs_bookings', 'bs_bookings.bid', '=', 'bs_reservations.bid')
            ->whereIn('bs_bookings.status', self::ACTIVE_STATUSES)
            ->where('bs_bookings.sid', $square->sid)
            ->where('bs_reservations.date', $date->format('Y-m-d'))
            ->where('bs_reservations.time_start', '<', $teStr)
            ->where('bs_reservations.time_end', '>', $tsStr);

        if ($excludeBookingId !== null) {
            $query->where('bs_bookings.bid', '!=', $excludeBookingId);
        }

        return $query->exists();
    }
}
