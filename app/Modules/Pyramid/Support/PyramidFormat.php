<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Support;

use Carbon\CarbonImmutable;

/**
 * Display formatting for raw legacy column values (decimals and datetimes are not cast).
 */
final class PyramidFormat
{
    public static function itn(string|int|float|null $itn): string
    {
        if ($itn === null) {
            return '–';
        }

        $decimal = app()->getLocale() === 'de' ? ',' : '.';

        return number_format((float) $itn, 1, $decimal, '');
    }

    public static function date(string $sql): string
    {
        return CarbonImmutable::parse($sql)->format('d.m.Y');
    }

    public static function dateTime(string $sql): string
    {
        return CarbonImmutable::parse($sql)->format('d.m.Y H:i');
    }
}
