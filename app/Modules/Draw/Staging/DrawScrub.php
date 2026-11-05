<?php

declare(strict_types=1);

namespace App\Modules\Draw\Staging;

use App\Modules\Draw\Database\Seeders\DrawDemoSeeder;
use App\Modules\TournamentBase\Staging\ModuleScrub;

final class DrawScrub implements ModuleScrub
{
    public function module(): string
    {
        return 'draw';
    }

    public function tables(): array
    {
        return ['bs_draw_eligible', 'bs_draw_tokens', 'bs_draws'];
    }

    public function seeder(): string
    {
        return DrawDemoSeeder::class;
    }
}
