<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Staging;

use App\Modules\Tournament\Database\Seeders\TournamentDemoSeeder;
use App\Modules\TournamentBase\Staging\ModuleScrub;

final class TournamentScrub implements ModuleScrub
{
    public function module(): string
    {
        return 'tournament';
    }

    public function tables(): array
    {
        // Children before parents. The draw tables belong to DrawScrub, which runs first (provider order);
        // the championship draws are recreated by TournamentDemoSeeder.
        return ['bs_tournament_lines', 'bs_tournament_matches', 'bs_tournament_players', 'bs_tournaments'];
    }

    public function seeder(): string
    {
        return TournamentDemoSeeder::class;
    }
}
