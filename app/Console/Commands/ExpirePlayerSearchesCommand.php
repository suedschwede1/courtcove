<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PlayerExchangeService;
use Illuminate\Console\Command;

/**
 * Verfall of the player exchange, run by the scheduler every five minutes: open searches
 * past their deadline (one hour before the start) lapse. Without signups the booking is
 * cancelled and the booker mailed, with signups only the search ends. Safe to run twice.
 */
class ExpirePlayerSearchesCommand extends Command
{
    protected $signature = 'player-exchange:expire';

    protected $description = 'Lapse open player searches whose deadline (one hour before the start) has passed';

    public function handle(PlayerExchangeService $exchange): int
    {
        $lapsed = $exchange->lapseExpired();
        $this->info('Lapsed bookings: '.$lapsed);

        return self::SUCCESS;
    }
}
