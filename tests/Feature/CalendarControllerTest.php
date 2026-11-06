<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingMeta;
use App\Models\Event;
use App\Models\EventMeta;
use App\Models\Option;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\SquareMeta;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CalendarControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function guest_can_view_calendar_and_login_panel(): void
    {
        $this->get('/calendar')
            ->assertOk()
            ->assertViewIs('calendar.index');
    }

    #[Test]
    public function calendar_shows_operator_information_in_info_panel(): void
    {
        Option::create(['key' => 'client.name.full', 'value' => 'Example Tennis Club', 'locale' => null]);
        Option::create(['key' => 'client.contact.email', 'value' => 'info@example.com', 'locale' => null]);
        Option::create(['key' => 'client.contact.phone', 'value' => '+43 123 4567', 'locale' => null]);
        Option::create(['key' => 'client.website', 'value' => 'https://example.com', 'locale' => null]);

        $this->get('/calendar')
            ->assertOk()
            ->assertSee('Example Tennis Club')
            ->assertSee('info@example.com')
            ->assertSee('+43 123 4567')
            ->assertSee('https://example.com');
    }

    #[Test]
    public function authenticated_user_sees_calendar_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/calendar')->assertOk()->assertViewIs('calendar.index');
    }

    private function createTournamentsTable(): void
    {
        Schema::dropIfExists('bs_tournaments');
        Schema::create('bs_tournaments', function ($table): void {
            $table->increments('tid');
            $table->string('title');
            $table->string('status')->default('running');
            $table->integer('season')->default(2026);
            $table->tinyInteger('active')->default(0);
            $table->tinyInteger('is_test')->default(0);
        });
    }

    #[Test]
    public function logged_in_user_sees_tournament_buttons(): void
    {
        $this->createTournamentsTable();
        DB::table('bs_tournaments')->insert(['title' => 'VM 2026', 'status' => 'running', 'season' => 2026, 'active' => 1, 'is_test' => 0]);

        $this->actingAs(User::factory()->create())
            ->get('/calendar')
            ->assertOk()
            ->assertSee('VM 2026')
            ->assertSee('Forderungspyramide')
            ->assertSee('Mixed-/Fun-Turnier')
            ->assertSee(url('/tournament'))
            ->assertSee(url('/pyramide'))
            ->assertSee(url('/mixed-tournament'));

        Schema::dropIfExists('bs_tournaments');
    }

    #[Test]
    public function championship_button_hidden_without_tournament(): void
    {
        $this->createTournamentsTable();
        DB::table('bs_tournaments')->insert(['title' => 'Zq Finished Cup', 'status' => 'finished', 'season' => 2026, 'active' => 1, 'is_test' => 0]);

        $this->actingAs(User::factory()->create())
            ->get('/calendar')
            ->assertOk()
            ->assertSee('Forderungspyramide')
            ->assertSee('Mixed-/Fun-Turnier')
            ->assertDontSee('Zq Finished Cup')
            ->assertDontSee(url('/tournament').'"', false);

        Schema::dropIfExists('bs_tournaments');
    }

    #[Test]
    public function guests_see_no_tournament_buttons(): void
    {
        $this->get('/calendar')
            ->assertOk()
            ->assertDontSee('Forderungspyramide')
            ->assertDontSee('Mixed-/Fun-Turnier');
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
        SquareMeta::create(['sid' => $square->sid, 'key' => 'alias', 'value' => 'Beta Court']);

        $user = User::factory()->create();
        $this->actingAs($user)->get('/calendar')
            ->assertSee('2')
            ->assertSee('Beta Court');
    }

    #[Test]
    public function calendar_shows_booking_owner_name_for_authenticated_user(): void
    {
        $owner = User::factory()->create(['alias' => 'Max Mustermann']);
        $square = Square::factory()->create();
        $booking = Booking::factory()->create([
            'uid' => $owner->uid,
            'sid' => $square->sid,
            'status' => 'single',
        ]);
        Reservation::factory()->create([
            'bid' => $booking->bid,
            'date' => Carbon::today()->toDateString(),
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
        ]);

        $viewer = User::factory()->create();
        $this->actingAs($viewer)->get('/calendar?date='.Carbon::today()->format('Y-m-d'))
            ->assertSee('Max Mustermann');
    }

    private function championshipCalendar(?string $notes, ?User $owner = null, ?User $viewer = null): TestResponse
    {
        $owner ??= User::factory()->create(['alias' => 'Other Player']);
        $square = Square::factory()->create();
        $booking = Booking::factory()->create(['uid' => $owner->uid, 'sid' => $square->sid, 'status' => 'single']);
        if ($notes !== null) {
            BookingMeta::create(['bid' => $booking->bid, 'key' => 'notes', 'value' => $notes]);
        }
        Reservation::factory()->create([
            'bid' => $booking->bid,
            'date' => Carbon::today()->toDateString(),
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
        ]);

        $viewer ??= User::factory()->create();

        return $this->actingAs($viewer)->get('/calendar?date='.Carbon::today()->format('Y-m-d'));
    }

    #[Test]
    public function championship_booking_gets_cc_turnier_class(): void
    {
        $this->championshipCalendar('Vereinsmeisterschaft Runde 1')
            ->assertOk()
            ->assertSee('cc-turnier', false);
    }

    #[Test]
    public function booking_without_championship_note_has_no_cc_turnier_class(): void
    {
        $this->championshipCalendar('Training')->assertOk()->assertDontSee('cc-turnier', false);
        $this->championshipCalendar(null)->assertOk()->assertDontSee('cc-turnier', false);
    }

    #[Test]
    public function own_championship_booking_has_cc_own_and_cc_turnier_on_same_element(): void
    {
        $user = User::factory()->create();

        $html = $this->championshipCalendar('Vereinsmeisterschaft', $user, $user)->assertOk()->getContent();

        preg_match_all('/class="(calendar-cell[^"]*)"/', $html, $matches);
        $combined = array_filter(
            $matches[1],
            fn (string $classes): bool => in_array('cc-own', explode(' ', $classes), true)
                && in_array('cc-turnier', explode(' ', $classes), true)
        );

        $this->assertNotEmpty($combined, 'Expected one cell carrying both cc-own and cc-turnier.');
    }

    #[Test]
    public function championship_detection_does_not_add_queries_per_booking(): void
    {
        $viewer = User::factory()->create();
        $date = Carbon::today()->format('Y-m-d');
        $countQueries = function () use ($viewer, $date): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($viewer)->get('/calendar?date='.$date)->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $square = Square::factory()->create();
        $addBooking = function (int $hour) use ($square): void {
            $booking = Booking::factory()->create(['sid' => $square->sid, 'status' => 'single']);
            BookingMeta::create(['bid' => $booking->bid, 'key' => 'notes', 'value' => 'Vereinsmeisterschaft']);
            Reservation::factory()->create([
                'bid' => $booking->bid,
                'date' => Carbon::today()->toDateString(),
                'time_start' => sprintf('%02d:00:00', $hour),
                'time_end' => sprintf('%02d:00:00', $hour + 1),
            ]);
        };

        $addBooking(9);
        $withOne = $countQueries();
        $addBooking(10);
        $addBooking(11);
        $addBooking(12);

        $this->assertSame($withOne, $countQueries());
    }

    #[Test]
    public function calendar_does_not_show_cancelled_bookings(): void
    {
        $owner = User::factory()->create(['alias' => 'Storniert User']);
        $square = Square::factory()->create();
        $booking = Booking::factory()->create([
            'uid' => $owner->uid,
            'sid' => $square->sid,
            'status' => 'cancelled',
        ]);
        Reservation::factory()->create([
            'bid' => $booking->bid,
            'date' => Carbon::today()->toDateString(),
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
        ]);

        $viewer = User::factory()->create();
        $this->actingAs($viewer)->get('/calendar?date='.Carbon::today()->format('Y-m-d'))
            ->assertDontSee('Storniert User');
    }

    #[Test]
    public function calendar_passes_reservations_keyed_by_square_sid(): void
    {
        $square = Square::factory()->create();
        $booking = Booking::factory()->create(['sid' => $square->sid, 'status' => 'single']);
        Reservation::factory()->create([
            'bid' => $booking->bid,
            'date' => Carbon::today()->toDateString(),
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
        ]);

        $response = $this->get('/calendar?date='.Carbon::today()->format('Y-m-d'));

        $reservationsBySquare = $response->viewData('reservationsBySquare');
        $this->assertArrayHasKey($square->sid, $reservationsBySquare);
        $this->assertCount(1, $reservationsBySquare[$square->sid]);
    }

    #[Test]
    public function calendar_renders_up_to_eight_days_forward(): void
    {
        $response = $this->get('/calendar?date=2026-07-10');

        $dates = $response->viewData('dates');
        $this->assertCount(8, $dates);
        $this->assertSame('2026-07-09', $dates[0]->format('Y-m-d'));                  // yesterday
        $this->assertSame('2026-07-10', $dates[1]->format('Y-m-d'));                  // today
        $this->assertSame('2026-07-16', $dates[count($dates) - 1]->format('Y-m-d')); // today+6
    }

    #[Test]
    public function calendar_window_spans_yesterday_through_six_days_ahead(): void
    {
        $response = $this->get('/calendar?date=2026-07-10');

        $byDate = $response->viewData('reservationsByDate');
        $this->assertArrayHasKey('2026-07-09', $byDate); // yesterday in window
        $this->assertArrayHasKey('2026-07-16', $byDate); // today+6 in window
        $this->assertArrayNotHasKey('2026-07-17', $byDate); // today+7 out of window
    }

    #[Test]
    public function calendar_marks_optional_days_as_hidden_by_default(): void
    {
        $this->get('/calendar?date=2026-07-10')
            ->assertOk()
            ->assertSee('cal-extra-day', false)
            ->assertSee('data-day="1"', false);
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
        $owner = User::factory()->create(['alias' => 'Nur Heute']);
        $square = Square::factory()->create();
        $booking = Booking::factory()->create(['uid' => $owner->uid, 'sid' => $square->sid, 'status' => 'single']);

        Reservation::factory()->create([
            'bid' => $booking->bid,
            'date' => Carbon::today()->addDays(10)->toDateString(),
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
        ]);

        $viewer = User::factory()->create();
        $this->actingAs($viewer)->get('/calendar?date='.Carbon::today()->format('Y-m-d'))
            ->assertDontSee('Nur Heute');
    }

    #[Test]
    public function guest_does_not_see_booking_text(): void
    {
        $owner = User::factory()->create(['alias' => 'Geheim Mitglied']);
        $square = Square::factory()->create();
        $booking = Booking::factory()->create(['uid' => $owner->uid, 'sid' => $square->sid, 'status' => 'single']);
        Reservation::factory()->create([
            'bid' => $booking->bid,
            'date' => Carbon::today()->toDateString(),
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
        ]);

        // Not authenticated → booking owner name must not appear
        $this->get('/calendar?date='.Carbon::today()->format('Y-m-d'))
            ->assertOk()
            ->assertDontSee('Geheim Mitglied');
    }

    #[Test]
    public function guest_sees_events(): void
    {
        $square = Square::factory()->create();
        $event = Event::factory()->create([
            'sid' => $square->sid,
            'status' => 'enabled',
            'datetime_start' => Carbon::today()->setTime(10, 0),
            'datetime_end' => Carbon::today()->setTime(11, 0),
        ]);
        EventMeta::create(['eid' => $event->eid, 'key' => 'name', 'value' => 'Stadtmeisterschaft']);

        $this->get('/calendar?date='.Carbon::today()->format('Y-m-d'))
            ->assertOk()
            ->assertSee('Stadtmeisterschaft');
    }

    #[Test]
    public function admin_can_open_existing_event_from_calendar(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $square = Square::factory()->create();
        $event = Event::factory()->create([
            'sid' => $square->sid,
            'status' => 'enabled',
            'datetime_start' => Carbon::today()->setTime(10, 0),
            'datetime_end' => Carbon::today()->setTime(11, 0),
        ]);
        EventMeta::create(['eid' => $event->eid, 'key' => 'name', 'value' => 'Admin Turnier']);

        // Event editing is only offered while admin mode is on (cookie `admin_mode`).
        $this->actingAs($admin)->withUnencryptedCookie('admin_mode', '1')->get('/calendar?date='.Carbon::today()->format('Y-m-d'))
            ->assertOk()
            ->assertSee('data-action="event-edit"', false)
            ->assertSee(route('admin.events.edit', $event), false);
    }

    #[Test]
    public function guest_does_not_get_event_edit_link(): void
    {
        $square = Square::factory()->create();
        $event = Event::factory()->create([
            'sid' => $square->sid,
            'status' => 'enabled',
            'datetime_start' => Carbon::today()->setTime(10, 0),
            'datetime_end' => Carbon::today()->setTime(11, 0),
        ]);
        EventMeta::create(['eid' => $event->eid, 'key' => 'name', 'value' => 'Nur Text Event']);

        $this->get('/calendar?date='.Carbon::today()->format('Y-m-d'))
            ->assertOk()
            ->assertSee('Nur Text Event')
            ->assertDontSee('data-action="event-edit"', false)
            ->assertDontSee(route('admin.events.edit', $event), false);
    }

    #[Test]
    public function admin_clickable_booking_contains_edit_url_for_popup(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $owner = User::factory()->create(['alias' => 'Edit Popup Owner']);
        $square = Square::factory()->create();
        $booking = Booking::factory()->create(['uid' => $owner->uid, 'sid' => $square->sid, 'status' => 'single']);
        $targetDate = Carbon::tomorrow();
        Reservation::factory()->create([
            'bid' => $booking->bid,
            'date' => $targetDate->toDateString(),
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
        ]);

        $this->actingAs($admin)->withUnencryptedCookie('admin_mode', '1')->get('/calendar?date='.$targetDate->format('Y-m-d'))
            ->assertOk()
            ->assertSee('data-edit-url', false)
            ->assertSee('/admin/bookings/'.$booking->bid.'/edit', false);
    }

    #[Test]
    public function admin_clickable_series_booking_contains_occurrence_query_param(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $owner = User::factory()->create(['alias' => 'Serie Owner']);
        $square = Square::factory()->create();
        $booking = Booking::factory()->create(['uid' => $owner->uid, 'sid' => $square->sid, 'status' => 'subscription']);
        $targetDate = Carbon::tomorrow();
        Reservation::factory()->create([
            'bid' => $booking->bid, 'date' => $targetDate->toDateString(), 'time_start' => '10:00:00', 'time_end' => '11:00:00',
        ]);
        Reservation::factory()->create([
            'bid' => $booking->bid, 'date' => $targetDate->copy()->addWeek()->toDateString(), 'time_start' => '10:00:00', 'time_end' => '11:00:00',
        ]);

        $this->actingAs($admin)->withUnencryptedCookie('admin_mode', '1')->get('/calendar?date='.$targetDate->format('Y-m-d'))
            ->assertOk()
            ->assertSee('/admin/bookings/'.$booking->bid.'/edit?occurrence='.$targetDate->format('Y-m-d'), false);
    }

    #[Test]
    public function admin_booking_popup_contains_delete_action(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $owner = User::factory()->create(['alias' => 'Delete Popup Owner']);
        $square = Square::factory()->create();
        $booking = Booking::factory()->create(['uid' => $owner->uid, 'sid' => $square->sid, 'status' => 'single']);
        $targetDate = Carbon::tomorrow();
        Reservation::factory()->create([
            'bid' => $booking->bid,
            'date' => $targetDate->toDateString(),
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
        ]);

        $this->actingAs($admin)->withUnencryptedCookie('admin_mode', '1')->get('/calendar?date='.$targetDate->format('Y-m-d'))
            ->assertOk()
            ->assertSee('data-delete-url', false)
            ->assertSee('/admin/bookings/'.$booking->bid, false);
    }

    #[Test]
    public function admin_without_admin_mode_cookie_sees_normal_booking_form_trigger(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $square = Square::factory()->create();

        $this->actingAsWithoutAdminMode($admin)->get('/calendar?date='.Carbon::tomorrow()->format('Y-m-d'))
            ->assertOk()
            ->assertDontSee('data-action="admin-book"', false)
            ->assertDontSee(route('admin.bookings.create'), false);
    }

    #[Test]
    public function admin_with_admin_mode_cookie_sees_admin_booking_form_trigger(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $square = Square::factory()->create();

        $this->actingAs($admin)->withUnencryptedCookie('admin_mode', '1')
            ->get('/calendar?date='.Carbon::tomorrow()->format('Y-m-d'))
            ->assertOk()
            ->assertSee('data-action="admin-book"', false);
    }

    #[Test]
    public function non_admin_cannot_bypass_permission_via_admin_mode_cookie(): void
    {
        $member = User::factory()->create(['status' => 'enabled']);
        $square = Square::factory()->create();

        $this->actingAs($member)->withUnencryptedCookie('admin_mode', '1')
            ->get('/calendar?date='.Carbon::tomorrow()->format('Y-m-d'))
            ->assertOk()
            ->assertDontSee('data-action="admin-book"', false);
    }

    #[Test]
    public function admin_mode_toggle_button_shown_only_to_admins(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $member = User::factory()->create(['status' => 'enabled']);

        $this->actingAsWithoutAdminMode($admin)->get('/calendar')
            ->assertOk()
            ->assertSee(route('admin-mode.set', ['state' => 'on']), false);

        $this->actingAs($member)->get('/calendar')
            ->assertOk()
            ->assertDontSee('admin-mode', false);
    }

    #[Test]
    public function calendar_header_shows_locale_switch_links(): void
    {
        $this->get('/calendar')
            ->assertOk()
            ->assertSee('href="'.route('lang.switch', ['locale' => 'en']).'"', false)
            ->assertDontSee('href="'.route('lang.switch', ['locale' => 'de']).'"', false);
    }
}
