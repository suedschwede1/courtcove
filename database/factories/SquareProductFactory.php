<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Square;
use App\Models\SquareProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SquareProduct>
 */
class SquareProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sid' => Square::factory(),
            'priority' => 1,
            'date_start' => null,
            'date_end' => null,
            'name' => fake()->words(2, true),
            'description' => null,
            'options' => '',
            'price' => fake()->numberBetween(500, 5000),
            'rate' => 20,
            'gross' => 1,
            'locale' => null,
        ];
    }
}
