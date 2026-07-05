<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LoginHistory;

/**
 * Decides whether a just-password-verified login looks risky enough to require
 * an email verification code, based on the user's login_history (30-day
 * retention, see App\Models\LoginHistory). Any one signal is enough to trigger.
 * triggeredFlags() returns which ones fired (persisted onto the login_history
 * row by the caller, purely for the admin-only risk score display).
 */
final class LoginRiskEvaluator
{
    private const RECENT_FAILURE_WINDOW_MINUTES = 60;

    private const RECENT_FAILURE_THRESHOLD = 2;

    private const IMPOSSIBLE_TRAVEL_WINDOW_HOURS = 24;

    /** Absorbs same-request timing artifacts (e.g. a test recording a login and checking it milliseconds later) — not a deliberate security grace period. */
    private const IMPOSSIBLE_TRAVEL_MIN_GAP_SECONDS = 60;

    private const KNOWN_COUNTRY_WINDOW_DAYS = 30;

    public function isSuspicious(int $userId, ?string $countryCode): bool
    {
        return $this->triggeredFlags($userId, $countryCode) !== [];
    }

    /** @return array<int, string> subset of unusual_country|recent_failed_attempts|impossible_travel */
    public function triggeredFlags(int $userId, ?string $countryCode): array
    {
        $flags = [];

        if ($this->hasUnusualCountry($userId, $countryCode)) {
            $flags[] = 'unusual_country';
        }

        if ($this->hasRecentFailedAttempts($userId)) {
            $flags[] = 'recent_failed_attempts';
        }

        if ($this->hasImpossibleTravel($userId, $countryCode)) {
            $flags[] = 'impossible_travel';
        }

        return $flags;
    }

    private function hasUnusualCountry(int $userId, ?string $countryCode): bool
    {
        if ($countryCode === null) {
            return false;
        }

        $knownCountries = LoginHistory::query()
            ->where('user_id', $userId)
            ->where('status', 'success')
            ->where('created_at', '>=', now()->subDays(self::KNOWN_COUNTRY_WINDOW_DAYS))
            ->whereNotNull('country_code')
            ->pluck('country_code')
            ->unique();

        if ($knownCountries->isEmpty()) {
            return false;
        }

        return ! $knownCountries->contains($countryCode);
    }

    private function hasRecentFailedAttempts(int $userId): bool
    {
        return LoginHistory::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['failed', 'throttled'])
            ->where('created_at', '>=', now()->subMinutes(self::RECENT_FAILURE_WINDOW_MINUTES))
            ->count() >= self::RECENT_FAILURE_THRESHOLD;
    }

    private function hasImpossibleTravel(int $userId, ?string $countryCode): bool
    {
        if ($countryCode === null) {
            return false;
        }

        $lastSuccess = LoginHistory::query()
            ->where('user_id', $userId)
            ->where('status', 'success')
            ->where('created_at', '>', now()->subHours(self::IMPOSSIBLE_TRAVEL_WINDOW_HOURS))
            ->whereNotNull('country_code')
            ->latest('created_at')
            ->first();

        if (! $lastSuccess) {
            return false;
        }

        if ($lastSuccess->country_code === $countryCode) {
            return false;
        }

        return abs(now()->diffInSeconds($lastSuccess->created_at)) > self::IMPOSSIBLE_TRAVEL_MIN_GAP_SECONDS;
    }
}
