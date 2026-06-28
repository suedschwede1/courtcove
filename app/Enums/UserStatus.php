<?php

declare(strict_types=1);

namespace App\Enums;

/** Account activation status of a user. */
enum UserStatus: string
{
    case Enabled = 'enabled';
    case Disabled = 'disabled';
}
