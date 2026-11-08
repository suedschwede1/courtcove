<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TrustedDeviceService;
use Illuminate\Console\Command;

/** Emergency tool: revoke every trusted device of one account (id or e-mail); the next sign-in needs a new code. */
class ResetTrustedDevicesCommand extends Command
{
    protected $signature = 'security:reset-devices {user : User id (uid) or e-mail address}';

    protected $description = 'Revoke all trusted devices of an account and end its sessions; the next sign-in needs a new e-mail code';

    public function handle(TrustedDeviceService $devices): int
    {
        $needle = (string) $this->argument('user');

        $user = ctype_digit($needle)
            ? User::query()->find((int) $needle)
            : User::query()->where('email', $needle)->first();

        if ($user === null) {
            $this->error('No such user.');

            return self::FAILURE;
        }

        $count = $devices->revokeAll($user, true);

        $this->info("Revoked {$count} trusted device(s) of user {$user->getKey()}.");

        return self::SUCCESS;
    }
}
