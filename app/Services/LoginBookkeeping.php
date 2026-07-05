<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Shared login logging/legacy-tracking, used by both LoginController (initial
 * password check) and LoginVerificationController (after a required
 * verification code is confirmed) so the "succeeded" bookkeeping only lives
 * in one place.
 */
final class LoginBookkeeping
{
    public function syncLegacyLoginTracking(?User $user, bool $successful, string $ip, int $lockoutSeconds): void
    {
        if (! $user) {
            return;
        }

        if ($successful) {
            $user->forceFill([
                'login_attempts' => 0,
                'login_detent' => null,
                'last_activity' => now(),
                'last_ip' => $ip,
            ])->save();

            return;
        }

        $attempts = (int) ($user->login_attempts ?? 0) + 1;

        $user->forceFill([
            'login_attempts' => min($attempts, 255),
            'login_detent' => now()->addSeconds($lockoutSeconds),
            'last_ip' => $ip,
        ])->save();
    }

    public function logAttempt(string $event, Request $request, ?User $user, array $context = []): void
    {
        $payload = array_merge($context, [
            'event' => $event,
            'user_id' => $user?->getKey(),
            'status' => $user?->status,
            'ip' => (string) $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255),
        ]);

        match ($event) {
            'succeeded' => Log::info('Login succeeded', $payload),
            'throttled' => Log::warning('Login throttled', $payload),
            default => Log::warning('Login failed', $payload),
        };
    }
}
