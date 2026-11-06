<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PlayerExchangeService;
use Illuminate\Contracts\View\View;

/**
 * Player exchange endpoints that need no login. The signature check
 * (middleware "signed" on the route) is the only authorisation.
 */
final class PlayerExchangeController extends Controller
{
    /** One-click unsubscribe from the link in player exchange e-mails. */
    public function unsubscribe(User $user, PlayerExchangeService $exchange): View
    {
        $exchange->optOut($user);

        return view('player-exchange.unsubscribed');
    }
}
