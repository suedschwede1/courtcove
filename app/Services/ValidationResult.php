<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Immutable result of a booking validation check.
 *
 * Use isValid() to check pass/fail, getError() to retrieve the reason on failure.
 */
final class ValidationResult
{
    private function __construct(
        private readonly bool $valid,
        private readonly string $error = '',
    ) {}

    public static function pass(): self
    {
        return new self(true);
    }

    public static function fail(string $error): self
    {
        return new self(false, $error);
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getError(): string
    {
        return $this->error;
    }
}
