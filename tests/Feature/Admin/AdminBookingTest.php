<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminBookingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    /** A valid create/update payload; pass `booked_for`/`sid`/dates via overrides. */
    private function bookingPayload(array $overrides = []): array
    {
        return array_merge([
            'date' => '2026-07-02',
            'date_end' => '2026-07-02',
            'repeat_type' => 'once',
            'time_start' => '10:00',
            'time_end' => '11:00',
            'quantity' => 2,
            'status' => 'single',
            'player_name_2' => 'Partner Mustermann',
        ], $overrides);
    }

    #[Test]
    public function regular_member_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'enabled']))->get('/admin/bookings')->assertForbidden();
    }

    #[Test]
    public function index_lists_active_bookings(): void
    {
        $owner = User::factory()->create(['alias' => 'Bucher Mitglied']);
        $b = Booking::factory()->create(['uid' => $owner->uid, 'status' => 'single']);
        Reservation::factory()->create(['bid' => $b->bid, 'date' => Carbon::today()->toDateString()]);

        $this->actingAs($this->admin())->get('/admin/bookings')
            ->assertOk()->assertSee('Bucher Mitglied');
    }

    #[Test]
    public function index_excludes_cancelled_bookings(): void
    {
        $owner = User::factory()->create(['alias' => 'Storno Mitglied']);
        Booking::factory()->create(['uid' => $owner->uid, 'status' => 'cancelled']);

        $this->actingAs($this->admin())->get('/admin/bookings')->assertOk()->assertDontSee('Storno Mitglied');
    }

    #[Test]
    public function admin_booking_is_blocked_by_global_event(): void
    {
        $owner = User::factory()->create();
        $square = Square::factory()->create();
        Event::factory()->create([
            'sid' => null,
            'status' => 'enabled',
            'datetime_start' => '2026-07-01 10:00:00',
            'datetime_end' => '2026-07-01 11:00:00',
        ]);

        $this->actingAs($this->admin())->post('/admin/bookings', $this->bookingPayload([
            'booked_for' => $owner->alias,
            'sid' => $square->sid,
            'date' => '2026-07-01',
            'date_end' => '2026-07-01',
        ]))->assertSessionHasErrors(['booking']);

        $this->assertDatabaseMissing('bs_bookings', ['uid' => $owner->uid, 'sid' => $square->sid]);
    }

    #[Test]
    public function store_resolves_booked_for_to_matching_member(): void
    {
        $member = User::factory()->create(['alias' => 'Sabrina Genshofer']);
        $square = Square::factory()->create();

        $this->actingAs($this->admin())->post('/admin/bookings', $this->bookingPayload([
            'booked_for' => 'Sabrina Genshofer',
            'sid' => $square->sid,
        ]))->assertRedirect();

        $this->assertDatabaseHas('bs_bookings', ['uid' => $member->uid, 'sid' => $square->sid]);
        $booking = Booking::where('uid', $member->uid)->where('sid', $square->sid)->firstOrFail();
        $this->assertDatabaseMissing('bs_bookings_meta', ['bid' => $booking->bid, 'key' => 'owner-name']);
    }

    #[Test]
    public function store_keeps_free_text_owner_when_no_member_matches(): void
    {
        $admin = $this->admin();
        $square = Square::factory()->create();

        $this->actingAs($admin)->post('/admin/bookings', $this->bookingPayload([
            'booked_for' => 'Externer Gast',
            'sid' => $square->sid,
        ]))->assertRedirect();

        $booking = Booking::where('sid', $square->sid)->firstOrFail();
        $this->assertSame($admin->uid, $booking->uid);
        $this->assertDatabaseHas('bs_bookings_meta', [
            'bid' => $booking->bid, 'key' => 'owner-name', 'value' => 'Externer Gast',
        ]);
    }

    #[Test]
    public function store_treats_ambiguous_alias_as_free_text(): void
    {
        User::factory()->create(['alias' => 'Doppel Name']);
        User::factory()->create(['alias' => 'Doppel Name']);
        $admin = $this->admin();
        $square = Square::factory()->create();

        $this->actingAs($admin)->post('/admin/bookings', $this->bookingPayload([
            'booked_for' => 'Doppel Name',
            'sid' => $square->sid,
        ]))->assertRedirect();

        $booking = Booking::where('sid', $square->sid)->firstOrFail();
        $this->assertSame($admin->uid, $booking->uid);
        $this->assertDatabaseHas('bs_bookings_meta', [
            'bid' => $booking->bid, 'key' => 'owner-name', 'value' => 'Doppel Name',
        ]);
    }

    #[Test]
    public function store_requires_booked_for(): void
    {
        $square = Square::factory()->create();

        $this->actingAs($this->admin())->post('/admin/bookings', $this->bookingPayload([
            'sid' => $square->sid,
        ]))->assertSessionHasErrors(['booked_for']);
    }

    #[Test]
    public function update_switching_to_member_clears_free_text_owner(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create(['alias' => 'Neuer Eigentuemer']);
        $square = Square::factory()->create();
        $booking = Booking::factory()->create([
            'uid' => $admin->uid, 'sid' => $square->sid, 'status' => 'single', 'quantity' => 2,
        ]);
        $booking->meta()->create(['key' => 'owner-name', 'value' => 'Alter Gast']);
        Reservation::factory()->create([
            'bid' => $booking->bid, 'date' => '2026-07-03', 'time_start' => '10:00:00', 'time_end' => '11:00:00',
        ]);

        $this->actingAs($admin)->put("/admin/bookings/{$booking->bid}", $this->bookingPayload([
            'booked_for' => 'Neuer Eigentuemer',
            'sid' => $square->sid,
            'date' => '2026-07-03',
            'date_end' => '2026-07-03',
        ]))->assertRedirect(route('admin.bookings.index'));

        $this->assertSame($member->uid, $booking->fresh()->uid);
        $this->assertDatabaseMissing('bs_bookings_meta', ['bid' => $booking->bid, 'key' => 'owner-name']);
    }

    #[Test]
    public function index_shows_free_text_owner_name(): void
    {
        $admin = $this->admin();
        $booking = Booking::factory()->create(['uid' => $admin->uid, 'status' => 'single']);
        $booking->meta()->create(['key' => 'owner-name', 'value' => 'Gast Walzer']);
        Reservation::factory()->create(['bid' => $booking->bid, 'date' => Carbon::today()->toDateString()]);

        $this->actingAs($admin)->get('/admin/bookings')->assertOk()->assertSee('Gast Walzer');
    }

    #[Test]
    public function store_defaults_billing_status_to_pending_without_input(): void
    {
        $square = Square::factory()->create();

        $this->actingAs($this->admin())->post('/admin/bookings', $this->bookingPayload([
            'booked_for' => 'Externer Gast',
            'sid' => $square->sid,
        ]))->assertRedirect();

        $booking = Booking::where('sid', $square->sid)->firstOrFail();
        $this->assertSame('pending', $booking->status_billing);
    }

    #[Test]
    public function edit_page_has_no_billing_status_field(): void
    {
        $booking = Booking::factory()->create(['status' => 'single']);
        Reservation::factory()->create([
            'bid' => $booking->bid, 'date' => '2026-07-03', 'time_start' => '10:00:00', 'time_end' => '11:00:00',
        ]);

        $this->actingAs($this->admin())->get("/admin/bookings/{$booking->bid}/edit")
            ->assertOk()
            ->assertDontSee('Abrechnungsstatus')
            ->assertDontSee('name="status_billing"', false);
    }

    #[Test]
    public function update_keeps_existing_billing_status(): void
    {
        $admin = $this->admin();
        $square = Square::factory()->create();
        $booking = Booking::factory()->create([
            'uid' => $admin->uid, 'sid' => $square->sid, 'status' => 'single',
            'status_billing' => 'paid', 'quantity' => 2,
        ]);
        Reservation::factory()->create([
            'bid' => $booking->bid, 'date' => '2026-07-03', 'time_start' => '10:00:00', 'time_end' => '11:00:00',
        ]);

        $this->actingAs($admin)->put("/admin/bookings/{$booking->bid}", $this->bookingPayload([
            'booked_for' => $admin->alias,
            'sid' => $square->sid,
            'date' => '2026-07-03',
            'date_end' => '2026-07-03',
        ]))->assertRedirect(route('admin.bookings.index'));

        $this->assertSame('paid', $booking->fresh()->status_billing);
    }

    #[Test]
    public function edit_page_has_cancel_link_back_to_list(): void
    {
        $booking = Booking::factory()->create(['status' => 'single']);
        Reservation::factory()->create([
            'bid' => $booking->bid, 'date' => '2026-07-03', 'time_start' => '10:00:00', 'time_end' => '11:00:00',
        ]);

        $this->actingAs($this->admin())->get("/admin/bookings/{$booking->bid}/edit")
            ->assertOk()
            ->assertSee('Abbrechen')
            ->assertSee(route('admin.bookings.index'), false);
    }

    #[Test]
    public function edit_detects_weekly_repeat_from_reservations(): void
    {
        $admin = $this->admin();
        $square = Square::factory()->create();
        $booking = Booking::factory()->create([
            'uid' => $admin->uid, 'sid' => $square->sid, 'status' => 'subscription', 'quantity' => 2,
        ]);
        foreach (['2026-07-06', '2026-07-13', '2026-07-20'] as $date) {
            Reservation::factory()->create([
                'bid' => $booking->bid, 'date' => $date, 'time_start' => '10:00:00', 'time_end' => '11:00:00',
            ]);
        }

        $response = $this->actingAs($admin)->get("/admin/bookings/{$booking->bid}/edit");

        $response->assertOk();
        $this->assertSame('weekly', $response->viewData('repeatType'));
    }

    #[Test]
    public function admin_can_cancel_any_booking(): void
    {
        $b = Booking::factory()->create(['status' => 'single']);
        $this->actingAs($this->admin())->post("/admin/bookings/{$b->bid}/cancel")->assertRedirect(route('admin.bookings.index'));
        $this->assertSame('cancelled', $b->fresh()->status);
    }


    #[Test]
    public function popup_edit_page_contains_calendar_redirect(): void
    {
        $booking = Booking::factory()->create(['status' => 'single']);
        Reservation::factory()->create([
            'bid' => $booking->bid, 'date' => '2026-07-03', 'time_start' => '10:00:00', 'time_end' => '11:00:00',
        ]);

        $this->actingAs($this->admin())->get("/admin/bookings/{$booking->bid}/edit?popup=1")
            ->assertOk()
            ->assertSee('name="redirect_to"', false)
            ->assertSee('/calendar?date=2026-07-03', false);
    }

    #[Test]
    public function popup_update_redirects_back_to_calendar(): void
    {
        $admin = $this->admin();
        $square = Square::factory()->create();
        $booking = Booking::factory()->create([
            'uid' => $admin->uid, 'sid' => $square->sid, 'status' => 'single', 'quantity' => 2,
        ]);
        Reservation::factory()->create([
            'bid' => $booking->bid, 'date' => '2026-07-03', 'time_start' => '10:00:00', 'time_end' => '11:00:00',
        ]);

        $this->actingAs($admin)->put("/admin/bookings/{$booking->bid}", $this->bookingPayload([
            'booked_for' => $admin->alias,
            'sid' => $square->sid,
            'date' => '2026-07-03',
            'date_end' => '2026-07-03',
            'redirect_to' => '/calendar?date=2026-07-03',
        ]))->assertRedirect('/calendar?date=2026-07-03');
    }

    #[Test]
    public function popup_cancel_redirects_back_to_calendar(): void
    {
        $booking = Booking::factory()->create(['status' => 'single']);

        $this->actingAs($this->admin())->post("/admin/bookings/{$booking->bid}/cancel", [
            'redirect_to' => '/calendar?date=2026-07-03',
        ])->assertRedirect('/calendar?date=2026-07-03');

        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    #[Test]
    public function admin_can_delete_a_booking(): void
    {
        $b = Booking::factory()->create(['status' => 'single']);
        $this->actingAs($this->admin())->delete("/admin/bookings/{$b->bid}")->assertRedirect(route('admin.bookings.index'));
        $this->assertDatabaseMissing('bs_bookings', ['bid' => $b->bid]);
    }
}
