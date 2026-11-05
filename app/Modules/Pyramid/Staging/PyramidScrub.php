<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Staging;

use App\Modules\Pyramid\Database\Seeders\PyramidDemoSeeder;
use App\Modules\TournamentBase\Staging\ModuleScrub;

final class PyramidScrub implements ModuleScrub
{
    public function module(): string
    {
        return 'pyramid';
    }

    public function tables(): array
    {
        // Schema-version rows are kept: they are not part of the demo data.
        return ['bs_pyramid_events', 'bs_pyramid_challenges', 'bs_pyramid_grants', 'bs_pyramid_players', 'bs_pyramid_ladders'];
    }

    public function seeder(): string
    {
        return PyramidDemoSeeder::class;
    }
}
