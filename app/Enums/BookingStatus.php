<?php

declare(strict_types=1);

namespace App\Enums;

/** Status of a booking record — whether it is active or cancelled. */
enum BookingStatus: string
{
    case Enabled = 'enabled';
    case Disabled = 'disabled';
}
