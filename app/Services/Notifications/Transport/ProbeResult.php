<?php

declare(strict_types=1);

namespace App\Services\Notifications\Transport;

/** Outcome of a connection check: status or error code only, never response data. */
final readonly class ProbeResult
{
    public function __construct(public bool $ok, public ?string $code = null) {}
}
