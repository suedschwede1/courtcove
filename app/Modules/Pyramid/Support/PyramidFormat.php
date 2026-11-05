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

    /**
     * Nullable legacy columns (e.g. a challenge's `resolved`) render as a dash instead of "now".
     */
    public static function date(?string $sql): string
    {
        if ($sql === null || trim($sql) === '') {
            return '–';
        }

        return CarbonImmutable::parse($sql)->format('d.m.Y');
    }

    /**
     * Like date(), for stored values that may hold anything (hand-edited rows): an unparseable value renders as
     * a dash instead of failing the whole page.
     */
    public static function safeDate(?string $sql): string
    {
        try {
            return self::date($sql);
        } catch (\Throwable) {
            return '–';
        }
    }

    /**
     * Time of day (H:i) of a stored value; an unparseable value renders as a dash.
     */
    public static function safeTime(?string $sql): string
    {
        if ($sql === null || trim($sql) === '') {
            return '–';
        }

        try {
            return CarbonImmutable::parse($sql)->format('H:i');
        } catch (\Throwable) {
            return '–';
        }
    }

    /**
     * Like dateTime(), for stored values that may hold anything: a dash instead of a failing page.
     */
    public static function safeDateTime(?string $sql): string
    {
        try {
            return self::dateTime($sql);
        } catch (\Throwable) {
            return '–';
        }
    }

    public static function dateTime(?string $sql): string
    {
        if ($sql === null || trim($sql) === '') {
            return '–';
        }

        return CarbonImmutable::parse($sql)->format('d.m.Y H:i');
    }
}
