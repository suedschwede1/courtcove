<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SquareStatus;
use App\Models\Square;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Square>
 */
class SquareFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'                    => (string) fake()->numberBetween(1, 3),
            'status'                  => SquareStatus::Enabled->value,
            'priority'                => 1.0,
            'capacity'                => 2,
            'capacity_heterogenic'    => 1,
            'allow_notes'             => 0,
            'time_start'              => '08:00:00',
            'time_end'                => '22:00:00',
            'time_block'              => 3600,
            'time_block_bookable'     => 3600,
            'time_block_bookable_max' => 0,
            'min_range_book'          => 0,
            'range_book'              => 0,
            'max_active_bookings'     => 0,
            'range_cancel'            => 3600,
        ];
    }
}
