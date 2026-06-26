<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BillingStatus;
use App\Enums\BookingStatus;
use App\Enums\Visibility;
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
            'status'         => BookingStatus::Enabled->value,
            'status_billing' => BillingStatus::Pending->value,
            'visibility'     => Visibility::Public->value,
            'quantity'       => fake()->numberBetween(1, 4),
            'created'        => time(),
            'updated'        => time(),
        ];
    }
}
