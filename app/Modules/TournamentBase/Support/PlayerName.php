<?php

declare(strict_types=1);

namespace App\Modules\TournamentBase\Support;

final class PlayerName
{
    /** First whitespace-separated part of the label. */
    public static function first(string $label): string
    {
        $parts = preg_split('/\s+/u', trim($label), 2);

        return ($parts[0] ?? '') !== '' ? $parts[0] : $label;
    }
}
