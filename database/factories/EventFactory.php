<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EventStatus;
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
            'sid'            => Square::factory(),
            'datetime_start' => time(),
            'datetime_end'   => time() + 7200,
            'capacity'       => 0,
            'status'         => EventStatus::Enabled->value,
        ];
    }
}
