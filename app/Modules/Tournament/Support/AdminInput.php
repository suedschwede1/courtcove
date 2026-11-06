<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Support;

use Illuminate\Http\Request;

/**
 * Scalar-safe reads of the championship admin forms: an array where a single value is expected (`tid[]=x`)
 * counts as empty instead of an "Array to string conversion" error, and `(int) ['x']` (= 1) can never pick
 * an id. Ids are decimal and at most 10 digits (the unsigned int columns), anything else is 0.
 */
final class AdminInput
{
    public static function string(Request $request, string $key): string
    {
        $value = $request->input($key);

        return is_scalar($value) ? (string) $value : '';
    }

    public static function id(Request $request, string $key): int
    {
        return self::digits(trim(self::string($request, $key)));
    }

    /** ZF2's (int) cast for a plain number field: decimal digits (an optional minus) or 0. */
    public static function int(Request $request, string $key): int
    {
        $value = trim(self::string($request, $key));

        return preg_match('/^-?[0-9]{1,9}$/', $value) === 1 ? (int) $value : 0;
    }

    public static function digits(string $value): int
    {
        return ctype_digit($value) && strlen($value) <= 10 && (int) $value <= 4294967295 ? (int) $value : 0;
    }
}
