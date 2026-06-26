<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingMeta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingMeta>
 */
class BookingMetaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'bid'        => Booking::factory(),
            'meta_key'   => 'player_name_1',
            'meta_value' => fake()->name(),
        ];
    }
}
