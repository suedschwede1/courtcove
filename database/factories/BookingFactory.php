<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Square;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uid'            => User::factory(),
            'sid'            => Square::factory(),
            'status'         => 'single',
            'status_billing' => 'pending',
            'visibility'     => 'public',
            'quantity'       => fake()->numberBetween(1, 4),
            'created'        => now(),
        ];
    }
}
