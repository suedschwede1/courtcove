<?php

declare(strict_types=1);

namespace App\Services\Notifications;

/** A slice of the monthly budget held for one message until it is settled. */
final readonly class BudgetReservation
{
    public function __construct(
        public string $month,
        public int $micros,
    ) {}
}
