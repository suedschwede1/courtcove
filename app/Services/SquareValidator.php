<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SquareStatus;
use App\Models\Booking;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use Carbon\Carbon;

/**
 * Validates whether a user may create a booking on a given court at a given time.
 *
 * Business rules (ported from module/Square/src/Square/Service/SquareValidator.php):
 * - Disabled squares: nobody can book
 * - Readonly squares: only users with the calendar.create-single-bookings privilege (see User::can())
 * - range_book: max advance window in seconds (0/null = unlimited); cutoff is end-of-day on last allowed day
 * - time_block_bookable_max: per-user per-court per-day limit in seconds (0/null = unlimited)
 * - Short booking exemption: bookings starting within 30 min are exempt from the daily limit
 */
final class SquareValidator
{
    private const SHORT_BOOKING_THRESHOLD_SECONDS = 1800;

    public function validate(
        Square $square,
        User $user,
        int $quantity,
        Carbon $dateStart,
        Carbon $dateEnd,
    ): ValidationResult {
        if ($square->status === SquareStatus::Disabled) {
            return ValidationResult::fail('Square is disabled — no bookings allowed');
        }

        if ($square->status === SquareStatus::Readonly
            && !$user->can('calendar.create-single-bookings')) {
            return ValidationResult::fail('Square is readonly — booking requires privilege');
        }

        if ((int) $square->range_book > 0) {
            $maxBookableAt = Carbon::now()->addSeconds((int) $square->range_book)->endOfDay();
            if ($dateStart->greaterThan($maxBookableAt)) {
                return ValidationResult::fail('Booking date exceeds the allowed advance booking range');
            }
        }

        if ((int) $square->time_block_bookable_max > 0 && !$this->isShortBooking($dateStart)) {
            $dailyUsed = $this->getDailyUsedSeconds($user, $square, $dateStart);
            $requested = (int) $dateStart->diffInSeconds($dateEnd);

            if ($dailyUsed + $requested > (int) $square->time_block_bookable_max) {
                return ValidationResult::fail('Daily booking limit exceeded for this square');
            }
        }

        return ValidationResult::pass();
    }

    /** Whether the booking starts within 30 minutes (short-booking daily-limit exemption). */
    private function isShortBooking(Carbon $dateStart): bool
    {
        return Carbon::now()->diffInSeconds($dateStart, absolute: false) <= self::SHORT_BOOKING_THRESHOLD_SECONDS;
    }

    /**
     * Total booked seconds for this user on this court on the given calendar day.
     * Computed in PHP (DB-agnostic) from the DATE/TIME columns.
     */
    private function getDailyUsedSeconds(User $user, Square $square, Carbon $date): int
    {
        $reservations = Reservation::query()
            ->join('bs_bookings', 'bs_bookings.bid', '=', 'bs_reservations.bid')
            ->where('bs_bookings.uid', $user->uid)
            ->where('bs_bookings.sid', $square->sid)
            ->whereIn('bs_bookings.status', Booking::ACTIVE_STATUSES)
            ->where('bs_reservations.date', $date->toDateString())
            ->get(['bs_reservations.time_start', 'bs_reservations.time_end']);

        $seconds = 0;
        foreach ($reservations as $reservation) {
            $seconds += strtotime((string) $reservation->time_end) - strtotime((string) $reservation->time_start);
        }

        return $seconds;
    }
}
