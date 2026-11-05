<?php

declare(strict_types=1);

namespace Database\Factories\Modules\MixedTournament\Models;

use App\Modules\MixedTournament\Models\MixedEventGrant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Datetimes are plain "Y-m-d H:i:s" strings, exactly as the legacy app stores them.
 */
class MixedEventGrantFactory extends Factory
{
    protected $model = MixedEventGrant::class;

    public function definition(): array
    {
        return [
            'event_id' => 1,
            'uid' => 1,
            'role' => 'score',
            'created' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
