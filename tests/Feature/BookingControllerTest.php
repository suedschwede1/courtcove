<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Square;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_cannot_create_booking(): void
    {
        $square = Square::factory()->create();

        $this->post('/bookings', [
            'sid'        => $square->sid,
            'date'       => '2026-07-10',
            'time_start' => '10:00',
            'time_end'   => '11:00',
            'quantity'   => 2,
        ])->assertRedirect('/login');
    }

    #[Test]
    public function user_can_create_booking_on_available_slot(): void
    {
        $user   = User::factory()->create(['permissions' => 'calendar.create-single-bookings']);
        $square = Square::factory()->create([
            'status' => 'enabled', 'time_block_bookable_max' => 0, 'range_book' => 0,
        ]);

        $this->actingAs($user)->post('/bookings', [
            'sid'        => $square->sid,
            'date'       => '2026-07-10',
            'time_start' => '10:00',
            'time_end'   => '11:00',
            'quantity'   => 2,
        ])->assertRedirect();

        $this->assertDatabaseHas('bs_bookings', ['uid' => $user->uid, 'sid' => $square->sid]);
        $this->assertDatabaseHas('bs_reservations', ['time_start' => 36000, 'time_end' => 39600]);
    }

    #[Test]
    public function user_cannot_create_booking_on_disabled_square(): void
    {
        $user   = User::factory()->create(['permissions' => 'calendar.create-single-bookings']);
        $square = Square::factory()->create(['status' => 'disabled']);

        $response = $this->actingAs($user)->post('/bookings', [
            'sid'        => $square->sid,
            'date'       => '2026-07-10',
            'time_start' => '10:00',
            'time_end'   => '11:00',
            'quantity'   => 2,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('bs_bookings', ['uid' => $user->uid]);
    }

    #[Test]
    public function user_can_cancel_own_booking(): void
    {
        $user    = User::factory()->create();
        $booking = Booking::factory()->create(['uid' => $user->uid, 'status' => 'enabled']);

        $this->actingAs($user)->delete("/bookings/{$booking->bid}")->assertRedirect();

        $booking->refresh();
        $this->assertSame(BookingStatus::Disabled, $booking->status);
    }

    #[Test]
    public function user_cannot_cancel_another_users_booking(): void
    {
        $owner   = User::factory()->create();
        $other   = User::factory()->create();
        $booking = Booking::factory()->create(['uid' => $owner->uid]);

        $this->actingAs($other)->delete("/bookings/{$booking->bid}")->assertForbidden();
    }

    #[Test]
    public function guest_cannot_cancel_booking(): void
    {
        $booking = Booking::factory()->create();
        $this->delete("/bookings/{$booking->bid}")->assertRedirect('/login');
    }

    #[Test]
    public function booking_creation_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/bookings', [])->assertSessionHasErrors(['sid', 'date', 'time_start', 'time_end', 'quantity']);
    }

    #[Test]
    public function booking_creation_validates_quantity_range(): void
    {
        $user   = User::factory()->create();
        $square = Square::factory()->create();

        $this->actingAs($user)->post('/bookings', [
            'sid' => $square->sid, 'date' => '2026-07-10',
            'time_start' => '10:00', 'time_end' => '11:00',
            'quantity' => 5, // max is 4
        ])->assertSessionHasErrors(['quantity']);
    }
}
