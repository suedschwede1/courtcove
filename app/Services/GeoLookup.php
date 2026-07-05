<?php

declare(strict_types=1);

namespace App\Services;

use Stevebauman\Location\Drivers\MaxMind;
use Stevebauman\Location\Facades\Location;
use Throwable;

/**
 * Resolves a country code for an IP via stevebauman/location, but only when the
 * configured MaxMind driver's local .mmdb file actually exists on disk — it's an
 * untracked, per-deployment download (see AGENTS.md), not something committed to
 * the repo. Fails open (returns null) whenever the file is missing, the lookup
 * throws, or no country could be resolved — the same fail-open behavior the
 * geo-blocking check already had for an unresolved lookup.
 */
final class GeoLookup
{
    public function resolveCountry(string $ip): ?string
    {
        if (config('location.driver') === MaxMind::class
            && ! file_exists((string) config('location.maxmind.local.path'))) {
            return null;
        }

        try {
            $position = Location::get($ip);
        } catch (Throwable) {
            return null;
        }

        if (! $position || ! $position->countryCode) {
            return null;
        }

        return $position->countryCode;
    }
}
