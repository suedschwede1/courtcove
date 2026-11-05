<?php

declare(strict_types=1);

namespace App\Support\Ui;

final class UserStatusTone
{
    public static function for(string $status): BadgeTone
    {
        return match ($status) {
            'admin', 'assist', 'enabled' => BadgeTone::Good,
            'blocked', 'deleted' => BadgeTone::Problem,
            default => BadgeTone::Neutral,
        };
    }
}
