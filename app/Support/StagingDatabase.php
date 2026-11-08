<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * The one rule for "this is a staging database": the name ends in `_staging`.
 * Fail closed: a database whose name cannot be determined (or is blank) counts as staging, so that
 * safety nets (WhatsApp downgrade, mail redirect) stay on rather than off.
 */
final class StagingDatabase
{
    public const SUFFIX = '_staging';

    public static function isStagingName(string $database): bool
    {
        return str_ends_with(strtolower($database), self::SUFFIX);
    }

    public static function active(): bool
    {
        try {
            $name = trim((string) DB::connection()->getDatabaseName());
        } catch (\Throwable) {
            return true;
        }

        return $name === '' || self::isStagingName($name);
    }
}
