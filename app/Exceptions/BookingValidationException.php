<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Thrown when SquareValidator rejects a booking attempt. */
final class BookingValidationException extends RuntimeException
{
    public function __construct(string $reason)
    {
        parent::__construct($reason);
    }
}
