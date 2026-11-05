<?php

declare(strict_types=1);

namespace App\Support\Ui;

final class Initials
{
    /** Up to two upper-case initials from the first two words, or "?" for an empty name. */
    public static function from(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);

        if ($words === false || $words === []) {
            return '?';
        }

        $initials = '';
        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
        }

        return $initials;
    }
}
