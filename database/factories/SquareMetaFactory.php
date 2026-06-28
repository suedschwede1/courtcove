<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Square;
use App\Models\SquareMeta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SquareMeta>
 */
class SquareMetaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sid' => Square::factory(),
            'key' => fake()->unique()->slug(2),
            'value' => fake()->word(),
            'locale' => null,
        ];
    }
}
