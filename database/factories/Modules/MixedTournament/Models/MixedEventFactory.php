<?php

declare(strict_types=1);

namespace Database\Factories\Modules\MixedTournament\Models;

use App\Modules\MixedTournament\Models\MixedEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Datetimes are plain "Y-m-d H:i:s" strings, exactly as the legacy app stores them.
 */
class MixedEventFactory extends Factory
{
    protected $model = MixedEvent::class;

    public function definition(): array
    {
        return [
            'title' => 'Beispielturnier',
            'event_date' => '2027-04-10',
            'status' => 'draft',
            'is_default' => 0,
            'is_test' => 0,
            'court_count' => 4,
            'mode' => 'americano',
            'created' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
