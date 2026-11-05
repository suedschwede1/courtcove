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
     * "K. Beispiel" for "Karl Beispiel": first name as an initial, the rest as it is. A single word, or a name that
     * already starts with an initial, stays unchanged.
     */
    public static function shortName(?string $label): string
    {
        $label = trim((string) preg_replace('/\s+/u', ' ', (string) $label));
        $parts = explode(' ', $label, 2);

        if (count($parts) < 2) {
            return $label;
        }

        [$first, $rest] = $parts;

        if (mb_strlen($first) === 1 || str_ends_with($first, '.')) {
            return $label;
        }

        return mb_substr($first, 0, 1).'. '.$rest;
    }

    /**
     * "Sa, 7. Nov 10:00" (German) / "Sat, Nov 7 10:00": weekday, day, month and time of a stored datetime.
     * A broken value renders as a dash.
     */
    public static function dayTime(?string $sql): string
    {
        $time = $sql === null || trim($sql) === '' ? false : strtotime($sql);

        if (! $time) {
            return '–';
        }

        return __('pyramid::ui.fmt.day_time', [
            'weekday' => __('pyramid::ui.fmt.weekdays')[(int) date('N', $time) - 1],
            'day' => (int) date('j', $time),
            'month' => __('pyramid::ui.fmt.months')[(int) date('n', $time) - 1],
            'time' => date('H:i', $time),
        ]);
    }

    /**
     * "18.11." (German) / "Nov 18": day and month of a stored date. A broken value renders as a dash.
     */
    public static function shortDate(?string $sql): string
    {
        $time = $sql === null || trim($sql) === '' ? false : strtotime($sql);

        if (! $time) {
            return '–';
        }

        return __('pyramid::ui.fmt.short_date', [
            'day' => date('d', $time),
            'month' => date('m', $time),
            'month_name' => __('pyramid::ui.fmt.months')[(int) date('n', $time) - 1],
            'day_number' => (int) date('j', $time),
        ]);
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
