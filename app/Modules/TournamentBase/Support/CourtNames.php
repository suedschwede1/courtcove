<?php

declare(strict_types=1);

namespace App\Modules\TournamentBase\Support;

/**
 * Court labels. Names come only from the `tournament-base.courts` config.
 */
final class CourtNames
{
    public static function label(int|string|null $court): string
    {
        if ($court === null || $court === '') {
            return '';
        }

        $name = self::name($court);

        if ($name === '') {
            return __('tournament-base::courts.label_plain', ['number' => $court]);
        }

        return __('tournament-base::courts.label', ['number' => $court, 'name' => $name]);
    }

    /** The configured name, or the court number when none is configured. */
    public static function short(int|string|null $court): string
    {
        if ($court === null || $court === '') {
            return '';
        }

        $name = self::name($court);

        return $name !== '' ? $name : (string) $court;
    }

    private static function name(int|string $court): string
    {
        $courts = config('tournament-base.courts', []);
        $name = is_array($courts) ? ($courts[$court] ?? '') : '';

        return is_string($name) ? trim($name) : '';
    }
}
