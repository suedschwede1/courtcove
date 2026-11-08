<?php

declare(strict_types=1);

namespace App\Notifications\Registry;

use App\Models\User;

/**
 * Extra prerequisite of a consent purpose, checked by the gate after the general rules (main switch,
 * account, mobile number). Returns the gate reason when the account does not qualify, null when it does.
 */
interface PurposeGateCondition
{
    public function failure(User $user): ?string;
}
