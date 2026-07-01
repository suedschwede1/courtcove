<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Reads optional, per-installation, per-locale overrides for the
 * booking-confirmation quotes:
 *
 * - Quote groups (German only): lang/de/booking/quote_groups.php
 *   (not tracked in git — see quote_groups.example.php). Lets an admin
 *   assign a user to a group (via User::setMeta('quote_group', ...))
 *   that gets its own pool of quotes.
 * - Base quotes override (per locale): lang/{locale}/booking/quotes.local.php
 *   (not tracked in git — see quotes.local.example.php). Fully replaces
 *   the built-in booking.quotes list for that locale when present.
 * - Named quotes override (per locale): lang/{locale}/booking/quotes_named.local.php
 *   (not tracked in git — see quotes_named.local.example.php). Fully
 *   replaces the built-in booking.quotes_named list for that locale
 *   when present.
 */
final class QuoteGroups
{
    private const FILE = __DIR__.'/../../lang/de/booking/quote_groups.php';

    /** @return array<string, array{label: string, quotes: array<int, string>}> */
    public static function all(): array
    {
        return file_exists(self::FILE) ? require self::FILE : [];
    }

    /** @return array<int, string> */
    public static function quotesFor(?string $group): array
    {
        if ($group === null || $group === '') {
            return [];
        }

        return self::all()[$group]['quotes'] ?? [];
    }

    /**
     * Reads the optional, per-installation base-quotes override from
     * lang/{locale}/booking/quotes.local.php (not tracked in git — see
     * quotes.local.example.php). When present and non-empty, it fully
     * replaces $default instead of merging with it.
     *
     * @param array<int, string> $default
     * @return array<int, string>
     */
    public static function baseQuotes(string $locale, array $default): array
    {
        return self::localOverride($locale, 'quotes.local.php', $default);
    }

    /**
     * Reads the optional, per-installation named-quotes override from
     * lang/{locale}/booking/quotes_named.local.php (not tracked in git —
     * see quotes_named.local.example.php). When present and non-empty, it
     * fully replaces $default instead of merging with it.
     *
     * @param array<int, string> $default
     * @return array<int, string>
     */
    public static function namedQuotes(string $locale, array $default): array
    {
        return self::localOverride($locale, 'quotes_named.local.php', $default);
    }

    /**
     * @param array<int, string> $default
     * @return array<int, string>
     */
    private static function localOverride(string $locale, string $filename, array $default): array
    {
        $file = __DIR__."/../../lang/{$locale}/booking/{$filename}";

        if (! file_exists($file)) {
            return $default;
        }

        $override = require $file;

        return is_array($override) && $override !== [] ? $override : $default;
    }
}
