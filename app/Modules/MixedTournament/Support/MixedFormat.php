<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Support;

use App\Modules\MixedTournament\Domain\RoundService;
use Carbon\CarbonImmutable;

/**
 * Display formatting for the Mixed pages: raw legacy values (dates and decimals are not cast) and
 * locale-aware numbers (German "5,5", English "5.5").
 */
final class MixedFormat
{
    /** "1,67" (German) / "1.67" (English). */
    public static function decimal(float|int|string|null $value, int $decimals = 2): string
    {
        $separator = app()->getLocale() === 'de' ? ',' : '.';

        return number_format((float) $value, $decimals, $separator, '');
    }

    /** ITN with one decimal, a dash when unknown. */
    public static function itn(float|int|string|null $itn): string
    {
        return $itn === null || $itn === '' ? '–' : self::decimal($itn, 1);
    }

    /** "Sa, 7. November" / "Sat, November 7"; a broken stored value is shown as it is. */
    public static function date(?string $sql): string
    {
        if ($sql === null || trim($sql) === '') {
            return '–';
        }

        try {
            return CarbonImmutable::parse($sql)->locale(app()->getLocale())->isoFormat(__('mixed::common.date_format'));
        } catch (\Throwable) {
            return $sql;
        }
    }

    public static function mode(string $mode): string
    {
        return $mode === 'mexicano' ? __('mixed::common.mode_mexicano') : __('mixed::common.mode_americano');
    }

    /** Label of a distribution key; an unknown stored key is shown raw like in ZF2. */
    public static function distribution(string $distribution): string
    {
        return in_array($distribution, RoundService::DISTRIBUTIONS, true)
            ? __('mixed::common.distribution.'.$distribution)
            : $distribution;
    }

    /** "D" / "H" (German), "?" when unknown. */
    public static function gender(?string $gender): string
    {
        return match ($gender) {
            'f' => __('mixed::common.gender_f'),
            'm' => __('mixed::common.gender_m'),
            default => '?',
        };
    }
}
