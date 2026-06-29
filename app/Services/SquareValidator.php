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
 * Mirrors the legacy SquareValidator rules from ep3-bs.
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
        if (! $dateEnd->greaterThan($dateStart)) {
            return ValidationResult::fail('Booking end time must be after start time');
        }

        if (! $dateStart->isSameDay($dateEnd)) {
            return ValidationResult::fail('Bookings must start and end on the same day');
        }

        if ($quantity < 1) {
            return ValidationResult::fail('Booking quantity must be positive');
        }

        if ($square->status === SquareStatus::Disabled && ! $this->canCreateAnyBooking($user)) {
            return ValidationResult::fail('Square is disabled - no bookings allowed');
        }

        if ($square->status === SquareStatus::Readonly && ! $this->canCreateAnyBooking($user)) {
            return ValidationResult::fail('Square is readonly - booking requires privilege');
        }

        $startSeconds = $this->timeOfDaySeconds($dateStart);
        $endSeconds = $this->timeOfDaySeconds($dateEnd);
        $squareStart = $this->clockTimeToSeconds((string) $square->time_start);
        $squareEnd = $this->clockTimeToSeconds((string) $square->time_end);
        $durationSeconds = (int) $dateStart->diffInSeconds($dateEnd);

        if ($startSeconds < $squareStart || $endSeconds > $squareEnd) {
            return ValidationResult::fail('Booking time is outside square opening hours');
        }

        $minimumStart = Carbon::now();
        if ((int) $square->min_range_book === 0) {
            $minimumStart->subSeconds((int) $square->time_block_bookable / 2);
        } else {
            $minimumStart->addSeconds((int) $square->min_range_book);
        }

        if ($dateStart->lessThan($minimumStart)
            && ! $user->can('calendar.see-past')
            && ! ($user->can('calendar.see-data') && $dateEnd->isSameDay($minimumStart))) {
            return ValidationResult::fail('Booking time is already over');
        }

        if ((int) $square->min_range_book > 0
            && $dateStart->lessThan($minimumStart)
            && ! $this->canCreateAnyBooking($user)) {
            return ValidationResult::fail('Booking date is before the minimum advance booking time');
        }

        if ((int) $square->range_book > 0) {
            $maxBookableAt = Carbon::now()->addSeconds((int) $square->range_book);
            $sameDayAsMax = $dateStart->isSameDay($maxBookableAt);
            if ($dateStart->greaterThan($maxBookableAt) && ! $sameDayAsMax && ! $this->canCreateAnyBooking($user)) {
                return ValidationResult::fail('Booking date exceeds the allowed advance booking range');
            }
        }

        if ((int) $square->time_block_bookable > 0 && $durationSeconds < (int) $square->time_block_bookable) {
            return ValidationResult::fail(__('booking.messages.booking_duration_too_short'));
        }

        if ((int) $square->max_active_bookings > 0
            && $this->getActiveFutureBookingCount($user) >= (int) $square->max_active_bookings) {
            return ValidationResult::fail(__('booking.messages.max_active_bookings_reached'));
        }

        if ((int) $square->time_block_bookable_max > 0 && ! $this->isShortBooking($dateStart)) {
            $dailyUsed = $this->getDailyUsedSeconds($user, $dateStart);

            if ($dailyUsed + $durationSeconds > (int) $square->time_block_bookable_max
                && ! $this->canCreateAnyBooking($user)) {
                return ValidationResult::fail(__('booking.messages.daily_booking_limit_exceeded'));
            }
        }

        return ValidationResult::pass();
    }

    private function canCreateAnyBooking(User $user): bool
    {
        return $user->can('calendar.create-single-bookings, calendar.create-subscription-bookings');
    }

    /** Whether the booking starts within 30 minutes (short-booking daily-limit exemption). */
    private function isShortBooking(Carbon $dateStart): bool
    {
        return Carbon::now()->diffInSeconds($dateStart, absolute: false) <= self::SHORT_BOOKING_THRESHOLD_SECONDS;
    }

    /** Total booked seconds for this user on the given calendar day across all squares. */
    private function getDailyUsedSeconds(User $user, Carbon $date): int
    {
        $reservations = Reservation::query()
            ->join('bs_bookings', 'bs_bookings.bid', '=', 'bs_reservations.bid')
            ->where('bs_bookings.uid', $user->uid)
            ->where('bs_bookings.visibility', 'public')
            ->whereIn('bs_bookings.status', Booking::ACTIVE_STATUSES)
            ->where('bs_reservations.date', $date->toDateString())
            ->get(['bs_reservations.time_start', 'bs_reservations.time_end']);

        $seconds = 0;
        foreach ($reservations as $reservation) {
            $seconds += strtotime((string) $reservation->time_end) - strtotime((string) $reservation->time_start);
        }

        return $seconds;
    }

    private function getActiveFutureBookingCount(User $user): int
    {
        return Reservation::query()
            ->join('bs_bookings', 'bs_bookings.bid', '=', 'bs_reservations.bid')
            ->where('bs_bookings.uid', $user->uid)
            ->whereIn('bs_bookings.status', Booking::ACTIVE_STATUSES)
            ->where('bs_reservations.date', '>=', Carbon::today()->toDateString())
            ->count();
    }

    private function timeOfDaySeconds(Carbon $time): int
    {
        return ((int) $time->format('H')) * 3600
            + ((int) $time->format('i')) * 60
            + (int) $time->format('s');
    }

    private function clockTimeToSeconds(string $time): int
    {
        [$hours, $minutes, $seconds] = array_pad(explode(':', $time), 3, 0);

        return (int) $hours * 3600 + (int) $minutes * 60 + (int) $seconds;
    }
}
