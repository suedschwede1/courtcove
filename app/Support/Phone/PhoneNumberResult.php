<?php

declare(strict_types=1);

namespace App\Support\Phone;

/**
 * Outcome of {@see PhoneNumber::parse()}. $error is one of empty|invalid|not_mobile.
 * $mobileChecked is false when the country has no mobile rule (only the format was checked).
 */
final readonly class PhoneNumberResult
{
    public function __construct(
        public ?string $e164,
        public bool $valid,
        public bool $mobile,
        public bool $mobileChecked,
        public ?string $error,
    ) {}

    /** Last two digits, safe to show or log instead of the full number. */
    public function last2(): ?string
    {
        return $this->e164 === null ? null : substr($this->e164, -2);
    }
}
