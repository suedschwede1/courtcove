<?php

declare(strict_types=1);

namespace App\Services\Notifications;

/**
 * Outcome of {@see ConsentService::gate()}. $reason is one of disabled|account_inactive|no_phone|
 * phone_invalid|phone_not_mobile, unknown_purpose|condition_invalid, or the reason of the purpose's own gate
 * condition (player search: exchange_off) when the gate fails (the gate looks at the booking account only).
 * $e164 and $last2 are only set once the number has been parsed (never logged or stored in full).
 */
final readonly class GateResult
{
    public function __construct(
        public bool $passed,
        public ?string $reason,
        public ?string $e164 = null,
        public ?string $last2 = null,
    ) {}

    public static function pass(string $e164, ?string $last2): self
    {
        return new self(true, null, $e164, $last2);
    }

    public static function fail(string $reason, ?string $e164 = null, ?string $last2 = null): self
    {
        return new self(false, $reason, $e164, $last2);
    }
}
