<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Option;
use App\Models\Square;
use App\Support\BookingRules\BookingRule;

/**
 * Booking rules that are global settings (Administration > Konfiguration > Verhalten) instead of per-court
 * values. Read order for every rule: the global option if it is set (an explicit 0 counts), else the court
 * value from before the switch (bs_squares column / square meta, kept for ZF2 and rollback).
 *
 * rules() is the list `booking:adopt-booking-rules` works through; later rules are added there.
 */
final class BookingRules
{
    /** "Zeitblock min. buchbar": minimum duration of a booking, seconds (0 = none). */
    public const TIME_BLOCK_MIN = 'booking.time_block_bookable_min';

    /** "Zeitblock max. buchbar": booked seconds per person and day across all courts (0 = unlimited). */
    public const TIME_BLOCK_MAX = 'booking.time_block_bookable_max';

    /** @return list<BookingRule> */
    public static function rules(): array
    {
        return [
            new BookingRule(self::TIME_BLOCK_MIN, 'Zeitblock min. buchbar', column: 'time_block_bookable'),
            new BookingRule(self::TIME_BLOCK_MAX, 'Zeitblock max. buchbar', column: 'time_block_bookable_max'),
        ];
    }

    public static function rule(string $optionKey): BookingRule
    {
        foreach (self::rules() as $rule) {
            if ($rule->optionKey === $optionKey) {
                return $rule;
            }
        }

        throw new \InvalidArgumentException('Unknown booking rule '.$optionKey);
    }

    /** The global option, or null when it is not set. */
    public function globalValue(string $optionKey): ?int
    {
        $value = Option::getValue($optionKey);

        return $value === null || trim((string) $value) === '' ? null : max(0, (int) $value);
    }

    /** Effective value of a rule for bookings on this court. */
    public function value(string $optionKey, Square $square): int
    {
        return $this->globalValue($optionKey) ?? max(0, (int) self::rule($optionKey)->courtValue($square));
    }

    public function minimumSeconds(Square $square): int
    {
        return $this->value(self::TIME_BLOCK_MIN, $square);
    }

    public function dailyMaxSeconds(Square $square): int
    {
        return $this->value(self::TIME_BLOCK_MAX, $square);
    }
}
