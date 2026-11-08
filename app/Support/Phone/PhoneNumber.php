<?php

declare(strict_types=1);

namespace App\Support\Phone;

/**
 * Strict E.164 parser with a mobile check for +43 and +49. No external library: "00" becomes
 * "+", a single leading "0" becomes "+{defaultCountry}", the result must match
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
