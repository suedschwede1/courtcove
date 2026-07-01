<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SquareStatus;
use App\Models\Booking;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use App\Services\PeakLimitService;
use Carbon\Carbon;

/**
 * Validates whether a user may create a booking on a given court at a given time.
 * Mirrors the legacy SquareValidator rules from ep3-bs.
 */
final class SquareValidator
{
    public function __construct(
        private readonly PeakLimitService $peakLimitService = new PeakLimitService,
    ) {}

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
        $shortTermOverrideActive = $this->isShortTermLimitOverrideActive($square, $dateStart);

        if ($startSeconds < $squareStart || $endSeconds > $squareEnd) {
            return ValidationResult::fail('Booking time is outside square opening hours');
        }

        // Block past slots: booking not allowed once the slot time has fully passed.
        // The current hour remains bookable ($dateEnd is still in the future).
        if ($dateEnd->lessThanOrEqualTo(Carbon::now())
            && ! $user->can('calendar.see-past')) {
            return ValidationResult::fail(__('booking.messages.booking_in_past'));
        }

        if ((int) $square->min_range_book > 0) {
            $minimumStart = Carbon::now()->addSeconds((int) $square->min_range_book);
            if ($dateStart->lessThan($minimumStart) && ! $shortTermOverrideActive && ! $this->canCreateAnyBooking($user)) {
                return ValidationResult::fail(__('booking.messages.booking_too_early'));
            }
        }

        if ((int) $square->range_book > 0) {
            $maxBookableAt = Carbon::now()->addSeconds((int) $square->range_book);
            $sameDayAsMax = $dateStart->isSameDay($maxBookableAt);
            if ($dateStart->greaterThan($maxBookableAt) && ! $sameDayAsMax && ! $shortTermOverrideActive && ! $this->canCreateAnyBooking($user)) {
                return ValidationResult::fail('Booking date exceeds the allowed advance booking range');
            }
        }

        if ((int) $square->time_block_bookable > 0 && $durationSeconds < (int) $square->time_block_bookable) {
            return ValidationResult::fail(__('booking.messages.booking_duration_too_short'));
        }

        if ((int) $square->max_active_bookings > 0 && ! $shortTermOverrideActive) {
            $peakActive = $this->peakLimitService->isEnabled()
                && $square->getMeta('peak_limit_enabled') === '1';

            if ($peakActive) {
                if ($this->peakLimitService->isPeakTime($dateStart)) {
                    $count = $this->getPeakActiveFutureBookingCount(
                        $user,
                        $this->peakLimitService->windows(),
                    );
                    if ($count >= (int) $square->max_active_bookings) {
                        return ValidationResult::fail(__('booking.messages.peak_limit_reached'));
                    }
                }
            } elseif ($this->getActiveFutureBookingCount($user) >= (int) $square->max_active_bookings) {
                return ValidationResult::fail(__('booking.messages.max_active_bookings_reached'));
            }
        }

        if ((int) $square->time_block_bookable_max > 0) {
            $dailyUsed = $this->getDailyUsedSeconds($user, $dateStart);

            if ($dailyUsed + $durationSeconds > (int) $square->time_block_bookable_max
                && ! $shortTermOverrideActive
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

    /** Whether the booking starts within the per-square short-term override window. */
    private function isShortTermLimitOverrideActive(Square $square, Carbon $dateStart): bool
    {
        $windowSeconds = (int) $square->short_booking_window;
        if ($windowSeconds <= 0) {
            return false;
        }

        return Carbon::now()->diffInSeconds($dateStart, absolute: false) <= $windowSeconds;
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

    /** @param list<array{start: string, end: string}> $windows */
    private function getPeakActiveFutureBookingCount(User $user, array $windows): int
    {
        return Reservation::query()
            ->join('bs_bookings', 'bs_bookings.bid', '=', 'bs_reservations.bid')
            ->where('bs_bookings.uid', $user->uid)
            ->whereIn('bs_bookings.status', Booking::ACTIVE_STATUSES)
            ->where('bs_reservations.date', '>=', Carbon::today()->toDateString())
            ->where(function ($query) use ($windows): void {
                foreach ($windows as $window) {
                    $query->orWhere(function ($q) use ($window): void {
                        $q->where('bs_reservations.time_start', '>=', $window['start'].':00')
                          ->where('bs_reservations.time_start', '<',  $window['end'].':00');
                    });
                }
            })
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
