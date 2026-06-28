<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use Carbon\Carbon;
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
            'time_start' => 36000,
            'time_end'   => 39600,
            'quantity'   => 2,
        ])->assertRedirect('/login');
    }

    #[Test]
    public function user_can_create_booking_on_available_slot(): void
    {
        $user   = User::factory()->create();
        $square = Square::factory()->create([
            'status' => 'enabled', 'time_block_bookable_max' => 0, 'range_book' => 0,
        ]);

        $this->actingAs($user)->post('/bookings', [
            'sid'           => $square->sid,
            'date'          => '2026-07-10',
            'time_start'    => 36000,
            'time_end'      => 39600,
            'quantity'      => 2,
            'player_name_2' => 'Partner Mustermann',
        ])->assertRedirect();

        $this->assertDatabaseHas('bs_bookings', ['uid' => $user->uid, 'sid' => $square->sid, 'status' => 'single']);
        $this->assertDatabaseHas('bs_reservations', ['time_start' => '10:00:00', 'time_end' => '11:00:00']);
    }

    #[Test]
    public function user_cannot_create_booking_on_disabled_square(): void
    {
        $user   = User::factory()->create();
        $square = Square::factory()->create(['status' => 'disabled']);

        $response = $this->actingAs($user)->post('/bookings', [
            'sid'        => $square->sid,
            'date'       => '2026-07-10',
            'time_start' => 36000,
            'time_end'   => 39600,
            'quantity'   => 2,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('bs_bookings', ['uid' => $user->uid]);
    }

    #[Test]
    public function user_cannot_create_booking_on_already_reserved_slot(): void
    {
        $user = User::factory()->create();
        $square = Square::factory()->create([
            'status' => 'enabled', 'time_block_bookable_max' => 0, 'range_book' => 0,
        ]);
        $existing = Booking::factory()->create(['sid' => $square->sid, 'status' => 'single', 'quantity' => 2]);
        Reservation::factory()->create([
            'bid' => $existing->bid,
            'date' => '2026-07-10',
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
        ]);

        $this->actingAs($user)->post('/bookings', [
            'sid' => $square->sid,
            'date' => '2026-07-10',
            'time_start' => 36000,
            'time_end' => 39600,
            'quantity' => 2,
            'player_name_2' => 'Partner Mustermann',
        ])->assertSessionHasErrors(['booking']);

        $this->assertDatabaseMissing('bs_bookings', ['uid' => $user->uid, 'sid' => $square->sid]);
    }

    #[Test]
    public function user_can_create_booking_on_overlapping_slot_when_legacy_capacity_allows_it(): void
    {
        $user = User::factory()->create();
        $square = Square::factory()->create([
            'status' => 'enabled',
            'capacity' => 4,
            'capacity_heterogenic' => 1,
            'time_block_bookable_max' => 0,
            'range_book' => 0,
        ]);
        $existing = Booking::factory()->create([
            'sid' => $square->sid,
            'status' => 'single',
            'quantity' => 2,
        ]);
        Reservation::factory()->create([
            'bid' => $existing->bid,
            'date' => '2026-07-10',
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
        ]);

        $this->actingAs($user)->post('/bookings', [
            'sid' => $square->sid,
            'date' => '2026-07-10',
            'time_start' => 36000,
            'time_end' => 39600,
            'quantity' => 2,
            'player_name_2' => 'Partner Mustermann',
        ])->assertRedirect();

        $this->assertDatabaseHas('bs_bookings', ['uid' => $user->uid, 'sid' => $square->sid]);
    }

    #[Test]
    public function user_cannot_create_booking_during_global_event(): void
    {
        $user = User::factory()->create();
        $square = Square::factory()->create([
            'status' => 'enabled', 'time_block_bookable_max' => 0, 'range_book' => 0,
        ]);
        Event::factory()->create([
            'sid' => null,
            'status' => 'enabled',
            'datetime_start' => '2026-07-10 10:00:00',
            'datetime_end' => '2026-07-10 11:00:00',
        ]);

        $this->actingAs($user)->post('/bookings', [
            'sid' => $square->sid,
            'date' => '2026-07-10',
            'time_start' => 36000,
            'time_end' => 39600,
            'quantity' => 2,
            'player_name_2' => 'Partner Mustermann',
        ])->assertSessionHasErrors(['booking']);

        $this->assertDatabaseMissing('bs_bookings', ['uid' => $user->uid, 'sid' => $square->sid]);
    }

    #[Test]
    public function user_can_cancel_own_booking(): void
    {
        $user    = User::factory()->create();
        $booking = Booking::factory()->create(['uid' => $user->uid, 'status' => 'single']);

        $this->actingAs($user)->delete("/bookings/{$booking->bid}")->assertRedirect();

        $booking->refresh();
        $this->assertSame('cancelled', $booking->status);
    }

    #[Test]
    public function user_cannot_cancel_own_booking_inside_cancel_range(): void
    {
        $user = User::factory()->create();
        $square = Square::factory()->create(['range_cancel' => 86400]);
        $booking = Booking::factory()->create([
            'uid' => $user->uid,
            'sid' => $square->sid,
            'status' => 'single',
        ]);
        Reservation::factory()->create([
            'bid' => $booking->bid,
            'date' => Carbon::now()->addHours(2)->toDateString(),
            'time_start' => Carbon::now()->addHours(2)->format('H:i:s'),
            'time_end' => Carbon::now()->addHours(3)->format('H:i:s'),
        ]);

        $this->actingAs($user)->delete("/bookings/{$booking->bid}")->assertSessionHasErrors(['booking']);

        $this->assertSame('single', $booking->fresh()->status);
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
            'time_start' => 36000, 'time_end' => 39600,
            'quantity' => 5, // max is 4
        ])->assertSessionHasErrors(['quantity']);
    }
}
