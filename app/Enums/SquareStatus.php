<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Availability status of a bookable court.
 *
 * - Enabled:  Anyone can book
 * - Disabled: Nobody can book (not even admins via UI)
 * - Readonly: Only privileged users (calendar.create-single-bookings) can book
 */
enum SquareStatus: string
{
    case Enabled  = 'enabled';
    case Disabled = 'disabled';
    case Readonly = 'readonly';
}
