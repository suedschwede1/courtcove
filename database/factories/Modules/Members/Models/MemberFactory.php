<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Members\Models;

use App\Modules\Members\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        return [
            'member_number' => $this->faker->unique()->numberBetween(1, 999999),
            'firstname' => $this->faker->firstName(),
            'lastname' => $this->faker->lastName(),
            'birthdate' => $this->faker->optional()->date(),
            'email' => $this->faker->optional()->safeEmail(),
            'phone' => $this->faker->optional()->phoneNumber(),
            'gender' => $this->faker->optional()->randomElement(['m', 'w', 'd']),
            'street' => $this->faker->optional()->streetAddress(),
            'zip' => $this->faker->optional()->postcode(),
            'city' => $this->faker->optional()->city(),
            'joined_at' => $this->faker->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'left_at' => null,
        ];
    }
}
