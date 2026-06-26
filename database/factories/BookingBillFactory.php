<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingBill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingBill>
 */
class BookingBillFactory extends Factory
{
    public function definition(): array
    {
        return [
            'bid'         => Booking::factory(),
            'description' => fake()->sentence(),
            'quantity'    => 1,
            'time'        => null,
            'price'       => fake()->numberBetween(500, 5000),
            'rate'        => 20,
            'gross'       => 1,
        ];
    }
}
