<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use RuntimeException;

/** Thrown by {@see ConsentService::optIn()} when the gate fails; carries the gate reason. */
final class ConsentRefused extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('WhatsApp consent refused: '.$reason);
    }
}
