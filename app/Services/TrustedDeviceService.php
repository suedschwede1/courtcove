<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Trusted devices for sign-in: privileged accounts (or all, per config) must present a device cookie that
 * belongs to a valid TrustedDevice row; without one the sign-in goes through the e-mail code step
 * (LoginVerificationController), which then issues the device.
 *
 * Cookie: one per account (the name carries the user id, so several accounts can share a browser), the value
 * is a random 256-bit token; the app's cookie encryption signs and encrypts it, the database holds only its
 * SHA-256. HttpOnly, Secure, SameSite=Lax. Validity is fixed from issue (config booking.security).
 */
final class TrustedDeviceService
{
    /** Rows revoked or expired longer ago than this are deleted when a new device is issued. */
    private const PURGE_AFTER_DAYS = 90;

    /** Whether this account has to sign in from a trusted device (config scope: privileged | all | off). */
    public function isRequiredFor(User $user): bool
    {
        return match ((string) config('booking.security.trusted_device_scope', 'privileged')) {
            'all' => true,
            'off' => false,
            default => $user->isPrivileged(),
        };
    }

    public function cookieName(int $userId): string
    {
        return 'trusted_device_'.$userId;
    }

    /** The valid device the request presents for this user, or null (no cookie, unknown, expired, revoked). */
    public function validDevice(Request $request, User $user): ?TrustedDevice
    {
        $hash = $this->currentTokenHash($request, $user);

        if ($hash === null) {
            return null;
        }

        return TrustedDevice::query()
            ->valid()
            ->where('user_id', $user->getKey())
            ->where('token_hash', $hash)
            ->first();
    }

    /** Hash of the token this request carries for the user (to mark the current device in a list), or null. */
    public function currentTokenHash(Request $request, User $user): ?string
    {
        $token = $request->cookie($this->cookieName((int) $user->getKey()));

        return is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token) === 1 ? hash('sha256', $token) : null;
    }

    public function touch(TrustedDevice $device): void
    {
        $device->forceFill(['last_seen_at' => now()])->save();
    }

    /** Create a device for the user and queue its cookie on the response. */
    public function issue(Request $request, User $user, ?string $countryCode): TrustedDevice
    {
        $days = max(1, (int) config('booking.security.trusted_device_days', 30));
        $token = bin2hex(random_bytes(32));
        $cutoff = now()->subDays(self::PURGE_AFTER_DAYS);

        TrustedDevice::query()
            ->where(fn ($q) => $q->where('revoked_at', '<', $cutoff)->orWhere('expires_at', '<', $cutoff))
            ->delete();

        $device = TrustedDevice::create([
            'user_id' => (int) $user->getKey(),
            'token_hash' => hash('sha256', $token),
            'label' => self::label((string) $request->userAgent()),
            'country_code' => $countryCode,
            'created_at' => now(),
            'last_seen_at' => now(),
            'expires_at' => now()->addDays($days),
        ]);

        Cookie::queue(Cookie::make(
            $this->cookieName((int) $user->getKey()),
            $token,
            $days * 1440,
            '/',
            null,
            $request->isSecure() || app()->isProduction(),
            true,
            false,
            'lax',
        ));

        return $device;
    }

    /** Revoke every device of the account (password change, rights change, removal, "remove all"). */
    public function revokeAll(int|User $user): int
    {
        $id = $user instanceof User ? (int) $user->getKey() : $user;

        return TrustedDevice::query()
            ->where('user_id', $id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /** Short display label from a user agent, e.g. "Firefox on Windows". Display only. */
    public static function label(string $userAgent): string
    {
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') => 'Opera',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/'), str_contains($userAgent, 'CriOS/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => null,
        };

        $os = match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Mac OS X'), str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };

        if ($browser === null && $os === null) {
            return (string) __('booking.devices.unknown_device');
        }

        return $browser !== null && $os !== null
            ? (string) __('booking.devices.label', ['browser' => $browser, 'os' => $os])
            : (string) ($browser ?? $os);
    }
}
