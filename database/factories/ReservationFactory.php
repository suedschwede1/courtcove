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
            'date'       => mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('Y')),
            'time_start' => 36000, // 10:00
            'time_end'   => 39600, // 11:00
        ];
    }
}
