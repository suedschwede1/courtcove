<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use App\Models\Square;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sid' => Square::factory(),
            'status' => 'enabled',
            'datetime_start' => now(),
            'datetime_end' => now()->copy()->addHours(2),
            'capacity' => null,
        ];
    }
}
