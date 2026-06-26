<?php

declare(strict_types=1);

namespace App\Enums;

/** Publication status of a court event or closure. */
enum EventStatus: string
{
    case Enabled  = 'enabled';
    case Disabled = 'disabled';
}
