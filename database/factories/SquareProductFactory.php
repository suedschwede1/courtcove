<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductType;
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
            'sid'      => Square::factory(),
            'name'     => fake()->words(2, true),
            'type'     => ProductType::Single->value,
            'price'    => fake()->numberBetween(500, 5000),
            'priority' => 100,
        ];
    }
}
