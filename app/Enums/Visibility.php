<?php

declare(strict_types=1);

namespace App\Enums;

/** Whether a booking is publicly visible in the calendar grid. Legacy rows may hold other values. */
enum Visibility: string
{
    case Public = 'public';
}
