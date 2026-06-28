<?php

declare(strict_types=1);

namespace App\Enums;

/** Whether a square product applies to a single booking or a subscription. */
enum ProductType: string
{
    case Single = 'single';
    case Subscription = 'subscription';
}
