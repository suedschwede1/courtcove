<?php

declare(strict_types=1);

namespace App\Enums;

/** Payment/billing lifecycle state of a booking. */
enum BillingStatus: string
{
    case Pending       = 'pending';
    case Paid          = 'paid';
    case Cancelled     = 'cancelled';
    case Uncollectable = 'uncollectable';
}
