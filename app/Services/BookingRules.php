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

    /**
     * "Zeitblock": slot size, seconds. Only shown/stored; the Laravel calendar renders a fixed one-hour grid and
     * no booking check reads it (audit 2026-11-06). Kept as a global value for ZF2 compatibility and later use.
     */
    public const TIME_BLOCK = 'booking.time_block';

    /** "Buchungsvorlauf": a booking must start at least this many seconds from now (0 = none). */
    public const MIN_RANGE_BOOK = 'booking.min_range_book';

    /** "Buchung im Voraus": a booking may start at most this many seconds ahead, same day allowed (0 = unlimited). */
    public const RANGE_BOOK = 'booking.range_book';

    /** "Kurzfristige Buchung trotz Limits": seconds before start in which Vorlauf/Voraus/limits are lifted (0 = off). */
    public const SHORT_BOOKING_WINDOW = 'booking.short_booking_window';

    /** "Stornierung": members may cancel until this many seconds before the start (0 = never). */
    public const RANGE_CANCEL = 'booking.range_cancel';

    /** @return list<BookingRule> */
    public static function rules(): array
    {
        return [
            new BookingRule(self::TIME_BLOCK_MIN, 'Zeitblock min. buchbar', column: 'time_block_bookable', newCourtDefault: 1800),
            new BookingRule(self::TIME_BLOCK_MAX, 'Zeitblock max. buchbar', column: 'time_block_bookable_max', newCourtDefault: 10800),
            new BookingRule(self::TIME_BLOCK, 'Zeitblock', column: 'time_block', newCourtDefault: 3600),
            new BookingRule(self::MIN_RANGE_BOOK, 'Buchungsvorlauf', column: 'min_range_book'),
            new BookingRule(self::RANGE_BOOK, 'Buchung im Voraus', column: 'range_book', displayDivisor: 86400, displayUnit: 'Days', newCourtDefault: 56 * 86400),
            new BookingRule(self::SHORT_BOOKING_WINDOW, 'Kurzfristige Buchung trotz Limits', meta: 'short-booking-window'),
            new BookingRule(self::RANGE_CANCEL, 'Stornierung', column: 'range_cancel', displayDivisor: 3600, displayUnit: 'Hours', newCourtDefault: 86400),
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

    /**
     * Column values for a new court (the columns are NOT NULL and still read by ZF2): per rule with a column,
     * the global value, else the first court's value, else the rule's default.
     *
     * @return array<string, int>
     */
    public function columnsForNewCourt(): array
    {
        $first = Square::query()->orderBy('sid')->first();
        $columns = [];
        foreach (self::rules() as $rule) {
            if ($rule->column !== null) {
                $columns[$rule->column] = $this->globalValue($rule->optionKey)
                    ?? ($first !== null ? (int) $rule->courtValue($first) : $rule->newCourtDefault);
            }
        }

        return $columns;
    }
}
