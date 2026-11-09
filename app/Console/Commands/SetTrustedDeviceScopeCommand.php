<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TrustedDeviceSettings;
use Illuminate\Console\Command;

/** Sets the admin value of the trusted-device scope (what the admin page "Sicherheit" stores); idempotent. */
class SetTrustedDeviceScopeCommand extends Command
{
    protected $signature = 'security:set-device-scope {scope : off | admins | privileged | all}';

    protected $description = 'Set the admin value of the trusted-device sign-in scope (a value in .env still wins)';

    public function handle(TrustedDeviceSettings $settings): int
    {
        $scope = strtolower(trim((string) $this->argument('scope')));

        if (! in_array($scope, TrustedDeviceSettings::SCOPES, true)) {
            $this->error('Unknown scope. Use one of: '.implode(', ', TrustedDeviceSettings::SCOPES).'.');

            return self::FAILURE;
        }

        $changed = $settings->store($scope);
        $this->info($changed ? "Admin value set to {$scope}." : "Admin value already {$scope}.");

        if ($settings->lockedByEnv()) {
            $this->warn('BOOKING_TRUSTED_DEVICE_SCOPE is set in .env and wins; remove it to let the admin value apply.');
        }
        $this->line('Effective scope now: '.$settings->scope().' (source: '.$settings->source().').');

        return self::SUCCESS;
    }
}
