<?php

declare(strict_types=1);

namespace App\Enums;

/** How a discount coupon value is applied. */
enum CouponType: string
{
    case Percent = 'percent';
    case Fixed   = 'fixed';
}
