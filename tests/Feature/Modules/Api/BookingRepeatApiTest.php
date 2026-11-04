<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Api;

use App\Models\Booking;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Sanctum\Sanctum;
use Modules\Api\Mcp\Tools\CreateBookingTool;
use Modules\Api\Mcp\Tools\UpdateBookingTool;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingRepeatApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function payload(User $admin, Square $square, array $overrides = []): array
    {
        return array_merge([
            'booked_for' => $admin->alias,
            'sid' => $square->sid,
            'date' => '2026-07-06',
            'date_end' => '2026-07-08',
            'repeat_type' => 'weekly',
            'time_start' => '10:00',
            'time_end' => '11:00',
            'quantity' => 2,
            'mitspieler' => 'Partner Mustermann',
        ], $overrides);
    }

    private function series(User $owner, Square $square, array $dates): Booking
    {
        $booking = Booking::factory()->create([
            'uid' => $owner->uid, 'sid' => $square->sid, 'status' => 'subscription', 'quantity' => 2,
        ]);
        foreach ($dates as $date) {
            Reservation::factory()->create([
                'bid' => $booking->bid, 'date' => $date, 'time_start' => '10:00:00', 'time_end' => '11:00:00',
            ]);
        }

        return $booking;
    }

    #[Test]
    public function rest_create_rejects_daily_repeat(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $square = Square::factory()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/bookings', $this->payload($admin, $square, ['repeat_type' => 'daily']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('repeat_type');

        $this->assertSame(0, Booking::count());
    }

    #[Test]
    public function rest_update_of_legacy_daily_series_accepts_daily(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $square = Square::factory()->create();
        $booking = $this->series($admin, $square, ['2026-07-06', '2026-07-07', '2026-07-08']);
        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/bookings/{$booking->bid}", $this->payload($admin, $square, [
            'date_end' => '2026-07-09', 'repeat_type' => 'daily',
        ]))->assertSuccessful();

        $this->assertCount(4, $booking->fresh()->reservations);
    }

    #[Test]
    public function rest_update_of_once_booking_rejects_daily(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $square = Square::factory()->create();
        $booking = $this->series($admin, $square, ['2026-07-06']);
        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/bookings/{$booking->bid}", $this->payload($admin, $square, ['repeat_type' => 'daily']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('repeat_type');
    }

    #[Test]
    public function mcp_create_rejects_daily_repeat(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $square = Square::factory()->create();
        Sanctum::actingAs($admin);

        $this->expectException(ValidationException::class);

        app(CreateBookingTool::class)->handle(new McpRequest($this->payload($admin, $square, ['repeat_type' => 'daily'])));
    }

    #[Test]
    public function mcp_update_of_legacy_daily_series_accepts_daily(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $square = Square::factory()->create();
        $booking = $this->series($admin, $square, ['2026-07-06', '2026-07-07', '2026-07-08']);
        Sanctum::actingAs($admin);

        app(UpdateBookingTool::class)->handle(new McpRequest($this->payload($admin, $square, [
            'booking_id' => $booking->bid, 'date_end' => '2026-07-09', 'repeat_type' => 'daily',
        ])));

        $this->assertCount(4, $booking->fresh()->reservations);
    }

    #[Test]
    public function mcp_update_of_once_booking_rejects_daily(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $square = Square::factory()->create();
        $booking = $this->series($admin, $square, ['2026-07-06']);
        Sanctum::actingAs($admin);

        $this->expectException(ValidationException::class);

        app(UpdateBookingTool::class)->handle(new McpRequest($this->payload($admin, $square, [
            'booking_id' => $booking->bid, 'repeat_type' => 'daily',
        ])));
    }
}
