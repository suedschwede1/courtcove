<?php

declare(strict_types=1);

namespace Database\Factories\Modules\MixedTournament\Models;

use App\Modules\MixedTournament\Models\MixedRound;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Datetimes are plain "Y-m-d H:i:s" strings, exactly as the legacy app stores them.
 */
class MixedRoundFactory extends Factory
{
    protected $model = MixedRound::class;

    public function definition(): array
    {
        return [
            'event_id' => 1,
            'round_no' => 1,
            'group_size_mode' => '4_only',
            'distribution' => 'random',
            'status' => 'proposed',
        ];
    }
}
