<?php

declare(strict_types=1);

namespace App\Support\Phone;

/**
 * Strict E.164 parser with a mobile check for +43 and +49. No external library: "00" becomes
 * "+", a single leading "0" becomes "+{defaultCountry}", a trunk zero right after +43/+49 is dropped, the result must match
 * ^\+[1-9]\d{7,14}$. Other countries are accepted on format alone (mobileChecked = false).
 */
final class PhoneNumber
{
    public static function parse(?string $raw, string $defaultCountry = '43'): PhoneNumberResult
    {
        $value = preg_replace('/[\s\/\-().]/u', '', (string) $raw) ?? '';

        if ($value === '') {
            return new PhoneNumberResult(null, false, false, false, 'empty');
        }

        if (str_starts_with($value, '00')) {
            $value = '+'.substr($value, 2);
        } elseif (str_starts_with($value, '0')) {
            $value = '+'.$defaultCountry.substr($value, 1);
        }

        // "+43 (0) 660 ..." is the national form written behind the country code: drop one trunk zero.
        if (preg_match('/^\+(43|49)0/', $value) === 1) {
            $value = substr($value, 0, 3).substr($value, 4);
        }

        if (preg_match('/^\+[1-9]\d{7,14}$/', $value) !== 1) {
            return new PhoneNumberResult(null, false, false, false, 'invalid');
        }

        $mobile = null;

        if (str_starts_with($value, '+43')) {
            $mobile = str_starts_with(substr($value, 3), '6');
        } elseif (str_starts_with($value, '+49')) {
            $mobile = preg_match('/^1[567]/', substr($value, 3)) === 1;
        }

        if ($mobile === null) {
            return new PhoneNumberResult($value, true, false, false, null);
        }

        return new PhoneNumberResult($value, true, $mobile, true, $mobile ? null : 'not_mobile');
    }
}
