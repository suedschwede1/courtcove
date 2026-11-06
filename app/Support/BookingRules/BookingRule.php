<?php

declare(strict_types=1);

namespace App\Support\BookingRules;

use App\Models\Square;

/**
 * A booking rule that moved (or moves) from a per-court setting to one global option.
 *
 * The court source is either a bs_squares column or a square meta key (kept for ZF2 / rollback and as
 * fallback while the option is not set). Court value and option use the same storage unit (`unit`) unless
 * `factor` converts: option value = court value * factor. `displayDivisor` turns the stored value into what
 * the admin forms show (e.g. seconds / 60 = minutes). `newCourtDefault` fills the column of a new court when
 * neither the option nor an existing court gives a value.
 */
final readonly class BookingRule
{
    public function __construct(
        public string $optionKey,
        public string $label,
        public ?string $column = null,
        public ?string $meta = null,
        public string $unit = 'seconds',
        public int $factor = 1,
        public int $displayDivisor = 60,
        public string $displayUnit = 'Minutes',
        public int $newCourtDefault = 0,
    ) {}

    /** The court's stored value converted to the option's unit, or null when the court has none. */
    public function courtValue(Square $square): ?int
    {
        $raw = $this->column !== null ? $square->getAttribute($this->column) : $square->getMeta((string) $this->meta);

        if ($raw === null || $raw === '') {
            return null;
        }

        return (int) $raw * $this->factor;
    }

    /** The stored value as the admin forms show it (e.g. minutes). */
    public function display(int $stored): string
    {
        return $this->displayDivisor > 1 && $stored % $this->displayDivisor !== 0
            ? (string) round($stored / $this->displayDivisor, 2)
            : (string) intdiv($stored, max(1, $this->displayDivisor));
    }
}
