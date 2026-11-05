<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

/**
 * Money formatting for the Members pages: German "3.800,00 €", English "€3,800.00".
 * Single helper so dues, member detail, dashboard and inline-edit responses print amounts alike.
 */
final class MembersFormat
{
    /** "1.200,00" (German) / "1,200.00" (English), without currency sign. */
    public static function amount(float|int|string|null $value): string
    {
        $german = app()->getLocale() === 'de';

        return number_format((float) $value, 2, $german ? ',' : '.', $german ? '.' : ',');
    }

    /** "3.800,00 €" (German) / "€3,800.00" (English). */
    public static function money(float|int|string|null $value): string
    {
        $amount = self::amount($value);

        return app()->getLocale() === 'de' ? $amount.' €' : '€'.$amount;
    }

    /** Like money(), but a dash when no amount is set (e.g. an unset partial amount). */
    public static function optionalMoney(float|int|string|null $value): string
    {
        return $value === null || $value === '' ? '–' : self::money($value);
    }
}
