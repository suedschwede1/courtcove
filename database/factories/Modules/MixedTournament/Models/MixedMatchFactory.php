<?php

declare(strict_types=1);

namespace Database\Factories\Modules\MixedTournament\Models;

use App\Modules\MixedTournament\Models\MixedMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Datetimes are plain "Y-m-d H:i:s" strings, exactly as the legacy app stores them.
 */
class MixedMatchFactory extends Factory
{
    protected $model = MixedMatch::class;

    public function definition(): array
    {
        return [
            'round_id' => 1,
            'group_no' => 1,
            'match_no' => 1,
            'team_a_p1' => 1,
            'team_a_p2' => 2,
            'team_b_p1' => 3,
            'team_b_p2' => 4,
            'games_a' => null,
            'games_b' => null,
            'is_extra' => 0,
        ];
    }
}
