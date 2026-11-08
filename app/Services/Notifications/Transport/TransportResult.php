<?php

declare(strict_types=1);

namespace App\Services\Notifications\Transport;

final readonly class TransportResult
{
    public function __construct(
        public bool $accepted,
        public ?string $sid = null,
        public ?string $errorCode = null,
        public bool $retryable = false,
        public bool $authFailure = false,
    ) {}

    public static function accepted(string $sid): self
    {
        return new self(true, $sid);
    }

    public static function failed(string $errorCode, bool $retryable = false, bool $authFailure = false): self
    {
        return new self(false, null, $errorCode, $retryable, $authFailure);
    }
}
