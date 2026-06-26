<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'bid'        => Booking::factory(),
            'date'       => now()->toDateString(), // 'Y-m-d'
            'time_start' => '10:00:00',
            'time_end'   => '11:00:00',
        ];
    }
}
