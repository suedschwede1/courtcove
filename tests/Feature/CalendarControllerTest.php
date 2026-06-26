<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingMeta;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CalendarControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_is_redirected_to_login(): void
    {
        $this->get('/calendar')->assertRedirect('/login');
    }

    #[Test]
    public function authenticated_user_sees_calendar_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/calendar')->assertOk()->assertViewIs('calendar.index');
    }

    #[Test]
    public function calendar_uses_today_as_default_date(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/calendar');

        $response->assertOk();
        $date = $response->viewData('date');
        $this->assertInstanceOf(Carbon::class, $date);
        $this->assertTrue($date->isToday());
    }

    #[Test]
    public function calendar_accepts_custom_date_param(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/calendar?date=2026-07-15');

        $response->assertOk();
        $date = $response->viewData('date');
        $this->assertEquals('2026-07-15', $date->format('Y-m-d'));
    }

    #[Test]
    public function calendar_shows_all_squares_ordered_by_priority(): void
    {
        Square::factory()->create(['name' => 'Platz 3', 'priority' => 3]);
        Square::factory()->create(['name' => 'Platz 1', 'priority' => 1]);
        Square::factory()->create(['name' => 'Platz 2', 'priority' => 2]);

        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/calendar');

        $squares = $response->viewData('squares');
        $this->assertCount(3, $squares);
        $this->assertEquals('Platz 1', $squares->first()->name);
        $this->assertEquals('Platz 3', $squares->last()->name);
    }

    #[Test]
    public function calendar_shows_court_alias_in_header(): void
    {
        Square::factory()->create(['name' => 'Platz 2', 'alias' => 'Court A', 'priority' => 0]);

        $user = User::factory()->create();
        $this->actingAs($user)->get('/calendar')
            ->assertSee('Platz 2')
            ->assertSee('Court A');
    }

    #[Test]
    public function calendar_shows_booking_owner_name_for_authenticated_user(): void
    {
        $owner  = User::factory()->create(['name' => 'Max Mustermann']);
        $square = Square::factory()->create();
        $booking = Booking::factory()->create([
            'uid'    => $owner->uid,
            'sid'    => $square->sid,
            'status' => 'enabled',
        ]);
        Reservation::factory()->create([
            'bid'        => $booking->bid,
            'date'       => Carbon::today()->startOfDay()->timestamp,
            'time_start' => 36000, // 10:00
            'time_end'   => 39600, // 11:00
        ]);

        $viewer = User::factory()->create();
        $this->actingAs($viewer)->get('/calendar?date=' . Carbon::today()->format('Y-m-d'))
            ->assertSee('Max Mustermann');
    }

    #[Test]
    public function calendar_does_not_show_cancelled_bookings(): void
    {
        $owner  = User::factory()->create(['name' => 'Storniert User']);
        $square = Square::factory()->create();
        $booking = Booking::factory()->create([
            'uid'    => $owner->uid,
            'sid'    => $square->sid,
            'status' => 'disabled', // cancelled
        ]);
        Reservation::factory()->create([
            'bid'        => $booking->bid,
            'date'       => Carbon::today()->startOfDay()->timestamp,
            'time_start' => 36000,
            'time_end'   => 39600,
        ]);

        $viewer = User::factory()->create();
        $this->actingAs($viewer)->get('/calendar?date=' . Carbon::today()->format('Y-m-d'))
            ->assertDontSee('Storniert User');
    }

    #[Test]
    public function calendar_passes_reservations_keyed_by_square_sid(): void
    {
        $square  = Square::factory()->create();
        $booking = Booking::factory()->create(['sid' => $square->sid, 'status' => 'enabled']);
        Reservation::factory()->create([
            'bid'        => $booking->bid,
            'date'       => Carbon::today()->startOfDay()->timestamp,
            'time_start' => 36000,
            'time_end'   => 39600,
        ]);

        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/calendar?date=' . Carbon::today()->format('Y-m-d'));

        $reservationsBySquare = $response->viewData('reservationsBySquare');
        $this->assertArrayHasKey($square->sid, $reservationsBySquare);
        $this->assertCount(1, $reservationsBySquare[$square->sid]);
    }

    #[Test]
    public function calendar_shows_date_navigation_links(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/calendar?date=2026-07-10');

        $response->assertSee('2026-07-09'); // previous day
        $response->assertSee('2026-07-11'); // next day
        $response->assertSee('2026-07-10'); // current date displayed
    }

    #[Test]
    public function calendar_only_shows_reservations_for_requested_date(): void
    {
        $owner  = User::factory()->create(['name' => 'Nur Heute']);
        $square = Square::factory()->create();
        $booking = Booking::factory()->create(['uid' => $owner->uid, 'sid' => $square->sid, 'status' => 'enabled']);

        // Reservation on a different day
        Reservation::factory()->create([
            'bid'        => $booking->bid,
            'date'       => Carbon::today()->addDays(5)->startOfDay()->timestamp,
            'time_start' => 36000,
            'time_end'   => 39600,
        ]);

        $viewer = User::factory()->create();
        // View today — the reservation is on a different day, should NOT appear
        $this->actingAs($viewer)->get('/calendar?date=' . Carbon::today()->format('Y-m-d'))
            ->assertDontSee('Nur Heute');
    }
}
