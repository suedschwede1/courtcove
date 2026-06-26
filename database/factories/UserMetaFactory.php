<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserMeta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserMeta>
 */
class UserMetaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uid'        => User::factory(),
            'meta_key'   => 'key_' . fake()->unique()->word(),
            'meta_value' => fake()->sentence(),
        ];
    }
}
