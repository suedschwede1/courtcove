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
            'name'                  => fake()->words(2, true),
            'alias'                 => null,
            'status'                => SquareStatus::Enabled->value,
            'capacity'              => 4,
            'capacity_heterogenic'  => 0,
            'time_start'            => 28800,   // 08:00
            'time_end'              => 79200,   // 22:00
            'time_block'            => 3600,    // 1 hour
            'time_block_bookable'   => 3600,
            'time_block_bookable_max' => 0,
            'min_range_book'        => 0,
            'range_book'            => 0,
            'range_cancel'          => 3600,
            'priority'              => 100,
        ];
    }
}
