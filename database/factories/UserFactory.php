<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name'        => fake()->name(),
            'email'       => fake()->unique()->safeEmail(),
            'password'    => static::$password ??= Hash::make('password'),
            'phone'       => fake()->phoneNumber(),
            'roles'       => 'member',
            'permissions' => '',
            'status'      => UserStatus::Enabled->value,
            'token'       => null,
            'created'     => time(),
            'updated'     => time(),
        ];
    }
}
