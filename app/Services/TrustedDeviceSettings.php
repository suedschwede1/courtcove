<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Option;
use App\Models\User;
use Illuminate\Database\QueryException;

/**
 * Scope of the trusted-device check at sign-in (see TrustedDeviceService). Resolution: `.env`
 * (BOOKING_TRUSTED_DEVICE_SCOPE, config booking.security.trusted_device_scope; empty or unknown = not set)
 * > admin option `security.trusted_device_scope` > default "off". Nothing is cached, so a change applies at once.
 *
 * Levels, widening with the rank:
 *  - off:        nobody needs a device.
 *  - admins:     accounts whose role is admin ("Admins").
 *  - privileged: admins plus every other privileged account: role assist ("Bearbeiter" / Mitarbeiter) and every
 *                holder of the members right (User::requiresTrustedDevice()).
 *  - all:        every account that signs in.
 * The role counts, never the admin mode of the current session.
 */
final class TrustedDeviceSettings
{
    public const OPTION = 'security.trusted_device_scope';

    /** In ascending order of reach. */
    public const SCOPES = ['off', 'admins', 'privileged', 'all'];

    public const DEFAULT = 'off';

    /** The scope in effect. */
    public function scope(): string
    {
        return $this->envScope() ?? $this->optionScope() ?? self::DEFAULT;
    }

    /** Where the effective value comes from: env | admin | default. */
    public function source(): string
    {
        return match (true) {
            $this->envScope() !== null => 'env',
            $this->optionScope() !== null => 'admin',
            default => 'default',
        };
    }

    /** Whether the server configuration fixes the value (the admin page then cannot change it). */
    public function lockedByEnv(): bool
    {
        return $this->envScope() !== null;
    }

    /** Stores the admin value (does not touch the .env value); true when the effective stored value changed. */
    public function store(string $scope): bool
    {
        if (! in_array($scope, self::SCOPES, true)) {
            throw new \InvalidArgumentException('Unknown trusted device scope.');
        }

        $row = Option::where('key', self::OPTION)->whereNull('locale')->first();

        if ($row?->value === $scope) {
            return false;
        }

        $row === null
            ? Option::create(['key' => self::OPTION, 'value' => $scope, 'locale' => null])
            : $row->update(['value' => $scope]);

        return true;
    }

    /** Whether the level makes this account need a trusted device. */
    public function covers(User $user, string $scope): bool
    {
        return match ($scope) {
            'all' => true,
            'privileged' => $user->requiresTrustedDevice(),
            'admins' => $user->effectiveRole() === 'admin',
            default => false,
        };
    }

    /** Reach of a level (0 = off ... 3 = all); unknown = 0. */
    public static function rank(string $scope): int
    {
        $rank = array_search($scope, self::SCOPES, true);

        return $rank === false ? 0 : (int) $rank;
    }

    private function envScope(): ?string
    {
        $config = config('booking.security.trusted_device_scope');
        $value = is_scalar($config) ? strtolower(trim((string) $config)) : '';

        return in_array($value, self::SCOPES, true) ? $value : null;
    }

    private function optionScope(): ?string
    {
        try {
            $value = Option::getValue(self::OPTION);
        } catch (QueryException) {
            return null; // options table missing (fresh install, before migrating)
        }
        $value = is_scalar($value) ? strtolower(trim((string) $value)) : '';

        return in_array($value, self::SCOPES, true) ? $value : null;
    }
}
