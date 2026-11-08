<?php

declare(strict_types=1);

namespace App\Services\Notifications\Conditions;

use App\Models\User;
use App\Notifications\Registry\PurposeGateCondition;
use App\Services\PlayerExchangeService;

/** Gate prerequisite of the player search purpose: the account takes part in the player exchange. */
final class PlayerExchangeRequired implements PurposeGateCondition
{
    public function failure(User $user): ?string
    {
        return $user->getMeta(PlayerExchangeService::META_KEY) === '1' ? null : 'exchange_off';
    }
}
