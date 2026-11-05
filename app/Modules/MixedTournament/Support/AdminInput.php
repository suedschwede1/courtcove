<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Support;

use Illuminate\Http\Request;

/**
 * Scalar-safe reads of admin form fields: an array where a single value is expected (`title[]=x`) counts
 * as empty instead of an "Array to string conversion" error, and `(int) ['x']` (= 1) can never pick an id.
 */
final class AdminInput
{
    public static function string(Request $request, string $key): string
    {
        $value = $request->input($key);

        return is_scalar($value) ? (string) $value : '';
    }

    /** A positive decimal id, otherwise 0. */
    public static function id(Request $request, string $key): int
    {
        $value = self::string($request, $key);

        return ctype_digit($value) ? (int) $value : 0;
    }
}
