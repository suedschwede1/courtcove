<?php

declare(strict_types=1);

namespace Database\Factories;

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
            'alias' => fake()->name(),
            'status' => 'enabled',
            'email' => fake()->unique()->safeEmail(),
            'pw' => static::$password ??= Hash::make('password'),
            'login_attempts' => null,
            'login_detent' => null,
            'last_activity' => null,
            'last_ip' => null,
            'created' => now(),
        ];
    }

    /** Grant a status (e.g. admin / assist) for permission tests. */
    public function status(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
