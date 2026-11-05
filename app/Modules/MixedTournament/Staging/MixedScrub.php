<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Staging;

use App\Modules\MixedTournament\Database\Seeders\MixedDemoSeeder;
use App\Modules\TournamentBase\Staging\ModuleScrub;

final class MixedScrub implements ModuleScrub
{
    public function module(): string
    {
        return 'mixed';
    }

    public function tables(): array
    {
        // Children before parents. The real member roster is personal data and is replaced too;
        // schema-version rows are kept: they are not part of the demo data.
        return [
            'bs_mixed_event_grants', 'bs_mixed_matches', 'bs_mixed_round_players', 'bs_mixed_rounds',
            'bs_mixed_avoid_pairs', 'bs_mixed_participants', 'bs_mixed_events', 'bs_mixed_members',
        ];
    }

    public function seeder(): string
    {
        return MixedDemoSeeder::class;
    }
}
