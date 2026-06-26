<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use App\Models\EventMeta;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\SquareMeta;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CalendarControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_can_view_calendar_and_login_panel(): void
    {
        $this->get('/calendar')
            ->assertOk()
            ->assertViewIs('calendar.index');
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
        $response = $this->get('/calendar');

        $response->assertOk();
        $date = $response->viewData('date');
        $this->assertInstanceOf(Carbon::class, $date);
        $this->assertTrue($date->isToday());
    }

    #[Test]
    public function calendar_accepts_custom_date_param(): void
    {
        $response = $this->get('/calendar?date=2026-07-15');

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
    public function calendar_shows_court_number_and_alias_in_header(): void
    {
        $square = Square::factory()->create(['name' => '2', 'priority' => 1]);
        SquareMeta::create(['sid' => $square->sid, 'key' => 'alias', 'value' => 'Court D']);

        $user = User::factory()->create();
        $this->actingAs($user)->get('/calendar')
            ->assertSee('2')
            ->assertSee('Court D');
    }

    #[Test]
    public function calendar_shows_booking_owner_name_for_authenticated_user(): void
    {
        $owner   = User::factory()->create(['alias' => 'Max Mustermann']);
        $square  = Square::factory()->create();
        $booking = Booking::factory()->create([
            'uid'    => $owner->uid,
            'sid'    => $square->sid,
            'status' => 'single',
        ]);
        Reservation::factory()->create([
            'bid'        => $booking->bid,
            'date'       => Carbon::today()->toDateString(),
            'time_start' => '10:00:00',
            'time_end'   => '11:00:00',
        ]);

        $viewer = User::factory()->create();
        $this->actingAs($viewer)->get('/calendar?date=' . Carbon::today()->format('Y-m-d'))
            ->assertSee('Max Mustermann');
    }

    #[Test]
    public function calendar_does_not_show_cancelled_bookings(): void
    {
        $owner   = User::factory()->create(['alias' => 'Storniert User']);
        $square  = Square::factory()->create();
        $booking = Booking::factory()->create([
            'uid'    => $owner->uid,
            'sid'    => $square->sid,
            'status' => 'cancelled',
        ]);
        Reservation::factory()->create([
            'bid'        => $booking->bid,
            'date'       => Carbon::today()->toDateString(),
            'time_start' => '10:00:00',
            'time_end'   => '11:00:00',
        ]);

        $viewer = User::factory()->create();
        $this->actingAs($viewer)->get('/calendar?date=' . Carbon::today()->format('Y-m-d'))
            ->assertDontSee('Storniert User');
    }

    #[Test]
    public function calendar_passes_reservations_keyed_by_square_sid(): void
    {
        $square  = Square::factory()->create();
        $booking = Booking::factory()->create(['sid' => $square->sid, 'status' => 'single']);
        Reservation::factory()->create([
            'bid'        => $booking->bid,
            'date'       => Carbon::today()->toDateString(),
            'time_start' => '10:00:00',
            'time_end'   => '11:00:00',
        ]);

        $response = $this->get('/calendar?date=' . Carbon::today()->format('Y-m-d'));

        $reservationsBySquare = $response->viewData('reservationsBySquare');
        $this->assertArrayHasKey($square->sid, $reservationsBySquare);
        $this->assertCount(1, $reservationsBySquare[$square->sid]);
    }

    #[Test]
    public function calendar_shows_date_navigation_links(): void
    {
        $response = $this->get('/calendar?date=2026-07-10');

        $response->assertSee('2026-07-09');
        $response->assertSee('2026-07-11');
        $response->assertSee('10.07.2026');
    }

    #[Test]
    public function calendar_only_shows_reservations_for_requested_date(): void
    {
        $owner   = User::factory()->create(['alias' => 'Nur Heute']);
        $square  = Square::factory()->create();
        $booking = Booking::factory()->create(['uid' => $owner->uid, 'sid' => $square->sid, 'status' => 'single']);

        Reservation::factory()->create([
            'bid'        => $booking->bid,
            'date'       => Carbon::today()->addDays(5)->toDateString(),
            'time_start' => '10:00:00',
            'time_end'   => '11:00:00',
        ]);

        $viewer = User::factory()->create();
        $this->actingAs($viewer)->get('/calendar?date=' . Carbon::today()->format('Y-m-d'))
            ->assertDontSee('Nur Heute');
    }

    #[Test]
    public function guest_does_not_see_booking_text(): void
    {
        $owner   = User::factory()->create(['alias' => 'Geheim Mitglied']);
        $square  = Square::factory()->create();
        $booking = Booking::factory()->create(['uid' => $owner->uid, 'sid' => $square->sid, 'status' => 'single']);
        Reservation::factory()->create([
            'bid'        => $booking->bid,
            'date'       => Carbon::today()->toDateString(),
            'time_start' => '10:00:00',
            'time_end'   => '11:00:00',
        ]);

        // Not authenticated → booking owner name must not appear
        $this->get('/calendar?date=' . Carbon::today()->format('Y-m-d'))
            ->assertOk()
            ->assertDontSee('Geheim Mitglied');
    }

    #[Test]
    public function guest_sees_events(): void
    {
        $square = Square::factory()->create();
        $event  = Event::factory()->create([
            'sid'            => $square->sid,
            'status'         => 'enabled',
            'datetime_start' => Carbon::today()->setTime(10, 0),
            'datetime_end'   => Carbon::today()->setTime(11, 0),
        ]);
        EventMeta::create(['eid' => $event->eid, 'key' => 'name', 'value' => 'Stadtmeisterschaft']);

        $this->get('/calendar?date=' . Carbon::today()->format('Y-m-d'))
            ->assertOk()
            ->assertSee('Stadtmeisterschaft');
    }
}
