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
            'mitspieler' => 'Partner Mustermann',
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

        $this->actingAs($this->admin())->get('/admin/bookings?searched=1')
            ->assertOk()->assertSee('Bucher Mitglied');
    }

    #[Test]
    public function index_excludes_cancelled_bookings(): void
    {
        $owner = User::factory()->create(['alias' => 'Storno Mitglied']);
        Booking::factory()->create(['uid' => $owner->uid, 'status' => 'cancelled']);

        $this->actingAs($this->admin())->get('/admin/bookings?searched=1')->assertOk()->assertDontSee('Storno Mitglied');
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
    public function admin_cannot_create_overlapping_booking_even_when_capacity_allows_it(): void
    {
        // One booking per cell: capacity/heterogenic no longer permit slot sharing.
        $owner = User::factory()->create();
        $square = Square::factory()->create([
            'capacity' => 4,
            'capacity_heterogenic' => 1,
        ]);
        $existing = Booking::factory()->create([
            'sid' => $square->sid,
            'status' => 'single',
            'visibility' => 'public',
            'quantity' => 2,
        ]);
        Reservation::factory()->create([
            'bid' => $existing->bid,
            'date' => '2026-07-02',
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
        ]);

        $this->actingAs($this->admin())->post('/admin/bookings', $this->bookingPayload([
            'booked_for' => $owner->alias,
            'sid' => $square->sid,
            'quantity' => 2,
        ]))->assertSessionHasErrors(['booking']);

        $this->assertDatabaseMissing('bs_bookings', [
            'uid' => $owner->uid,
            'sid' => $square->sid,
        ]);
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

        $this->actingAs($admin)->get('/admin/bookings?searched=1')->assertOk()->assertSee('Gast Walzer');
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

    /** @return array{0: Booking, list<Reservation>} */
    private function subscriptionBooking(User $owner, Square $square, array $dates): Booking
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
    public function update_with_single_occurrence_scope_only_changes_that_occurrence(): void
    {
        $admin = $this->admin();
        $square = Square::factory()->create();
        $booking = $this->subscriptionBooking($admin, $square, ['2026-07-06', '2026-07-13', '2026-07-20']);

        $this->actingAs($admin)->put("/admin/bookings/{$booking->bid}", $this->bookingPayload([
            'booked_for' => $admin->alias,
            'sid' => $square->sid,
            'date' => '2026-07-13',
            'date_end' => '2026-07-13',
            'time_start' => '14:00',
            'time_end' => '15:00',
            'occurrence' => '2026-07-13',
            'edit_scope' => 'single_occurrence',
        ]))->assertRedirect(route('admin.bookings.index'));

        $booking->refresh();
        $this->assertSame('subscription', $booking->status);
        $this->assertEqualsCanonicalizing(['2026-07-06', '2026-07-20'], $booking->reservations()->pluck('date')->all());

        $newBooking = Booking::where('bid', '!=', $booking->bid)->where('sid', $square->sid)->firstOrFail();
        $this->assertSame('single', $newBooking->status);
        $reservation = $newBooking->reservations()->firstOrFail();
        $this->assertSame('2026-07-13', $reservation->date);
        $this->assertSame('14:00:00', $reservation->time_start);
    }

    #[Test]
    public function update_with_this_and_following_scope_splits_off_the_tail(): void
    {
        $admin = $this->admin();
        $square = Square::factory()->create();
        $booking = $this->subscriptionBooking($admin, $square, ['2026-07-06', '2026-07-13', '2026-07-20']);

        $this->actingAs($admin)->put("/admin/bookings/{$booking->bid}", $this->bookingPayload([
            'booked_for' => $admin->alias,
            'sid' => $square->sid,
            'date' => '2026-07-13',
            'date_end' => '2026-07-20',
            'repeat_type' => 'weekly',
            'occurrence' => '2026-07-13',
            'edit_scope' => 'this_and_following',
        ]))->assertRedirect(route('admin.bookings.index'));

        $booking->refresh();
        $this->assertEqualsCanonicalizing(['2026-07-06'], $booking->reservations()->pluck('date')->all());

        $newBooking = Booking::where('bid', '!=', $booking->bid)->where('sid', $square->sid)->firstOrFail();
        $this->assertEqualsCanonicalizing(['2026-07-13', '2026-07-20'], $newBooking->reservations()->pluck('date')->all());
    }

    #[Test]
    public function cancel_with_single_occurrence_scope_only_cancels_that_occurrence(): void
    {
        $admin = $this->admin();
        $square = Square::factory()->create();
        $booking = $this->subscriptionBooking($admin, $square, ['2026-07-06', '2026-07-13']);

        $this->actingAs($admin)->post("/admin/bookings/{$booking->bid}/cancel", [
            'occurrence' => '2026-07-13',
            'edit_scope' => 'single_occurrence',
        ])->assertRedirect(route('admin.bookings.index'));

        $this->assertSame('single', $booking->fresh()->status);
        $cancelled = Booking::where('status', 'cancelled')->firstOrFail();
        $this->assertSame('2026-07-13', $cancelled->reservations()->firstOrFail()->date);
    }

    #[Test]
    public function destroy_with_this_and_following_scope_only_removes_tail_occurrences(): void
    {
        $admin = $this->admin();
        $square = Square::factory()->create();
        $booking = $this->subscriptionBooking($admin, $square, ['2026-07-06', '2026-07-13', '2026-07-20']);

        $this->actingAs($admin)->delete("/admin/bookings/{$booking->bid}", [
            'occurrence' => '2026-07-13',
            'edit_scope' => 'this_and_following',
        ])->assertRedirect(route('admin.bookings.index'));

        $this->assertDatabaseHas('bs_bookings', ['bid' => $booking->bid]);
        $this->assertEqualsCanonicalizing(['2026-07-06'], $booking->fresh()->reservations()->pluck('date')->all());
        $this->assertDatabaseMissing('bs_reservations', ['date' => '2026-07-20']);
    }

    #[Test]
    public function update_without_occurrence_still_edits_the_whole_series_as_before(): void
    {
        $admin = $this->admin();
        $square = Square::factory()->create();
        $booking = $this->subscriptionBooking($admin, $square, ['2026-07-06', '2026-07-13']);

        $this->actingAs($admin)->put("/admin/bookings/{$booking->bid}", $this->bookingPayload([
            'booked_for' => $admin->alias,
            'sid' => $square->sid,
            'date' => '2026-07-06',
            'date_end' => '2026-07-13',
            'repeat_type' => 'weekly',
        ]))->assertRedirect(route('admin.bookings.index'));

        $booking->refresh();
        $this->assertSame('subscription', $booking->status);
        $this->assertEqualsCanonicalizing(['2026-07-06', '2026-07-13'], $booking->reservations()->pluck('date')->all());
        $this->assertEquals(1, Booking::count());
    }

    #[Test]
    public function edit_with_occurrence_param_defaults_to_single_occurrence_scope(): void
    {
        $admin = $this->admin();
        $square = Square::factory()->create();
        $booking = $this->subscriptionBooking($admin, $square, ['2026-07-06', '2026-07-13', '2026-07-20']);

        $response = $this->actingAs($admin)->get("/admin/bookings/{$booking->bid}/edit?occurrence=2026-07-13");

        $response->assertOk();
        $this->assertTrue($response->viewData('showScopeChoice'));
        $this->assertSame('2026-07-13', $response->viewData('reservation')->date);
        $this->assertSame('once', $response->viewData('repeatType'));
        $this->assertSame('2026-07-13', $response->viewData('repeatEndDate'));
        $this->assertSame('weekly', $response->viewData('followingRepeatType'));
        $this->assertSame('2026-07-20', $response->viewData('followingRepeatEndDate'));
    }

    #[Test]
    public function edit_without_occurrence_param_does_not_show_scope_choice(): void
    {
        $admin = $this->admin();
        $square = Square::factory()->create();
        $booking = $this->subscriptionBooking($admin, $square, ['2026-07-06', '2026-07-13']);

        $response = $this->actingAs($admin)->get("/admin/bookings/{$booking->bid}/edit");

        $response->assertOk();
        $this->assertFalse($response->viewData('showScopeChoice'));
        $this->assertSame('2026-07-06', $response->viewData('reservation')->date);
    }

    #[Test]
    public function edit_with_occurrence_param_on_single_booking_does_not_show_scope_choice(): void
    {
        $booking = Booking::factory()->create(['status' => 'single']);
        Reservation::factory()->create(['bid' => $booking->bid, 'date' => '2026-07-06']);

        $response = $this->actingAs($this->admin())->get("/admin/bookings/{$booking->bid}/edit?occurrence=2026-07-06");

        $response->assertOk();
        $this->assertFalse($response->viewData('showScopeChoice'));
    }

    #[Test]
    public function edit_page_shows_scope_radios_when_occurrence_given_for_subscription(): void
    {
        $admin = $this->admin();
        $square = Square::factory()->create();
        $booking = $this->subscriptionBooking($admin, $square, ['2026-07-06', '2026-07-13', '2026-07-20']);

        $this->actingAs($admin)->get("/admin/bookings/{$booking->bid}/edit?occurrence=2026-07-13")
            ->assertOk()
            ->assertSee('name="edit_scope"', false)
            ->assertSee('value="single_occurrence"', false)
            ->assertSee('value="this_and_following"', false)
            ->assertSee('name="occurrence" value="2026-07-13"', false);
    }

    #[Test]
    public function edit_page_hides_scope_radios_without_occurrence(): void
    {
        $admin = $this->admin();
        $square = Square::factory()->create();
        $booking = $this->subscriptionBooking($admin, $square, ['2026-07-06', '2026-07-13']);

        $this->actingAs($admin)->get("/admin/bookings/{$booking->bid}/edit")
            ->assertOk()
            ->assertDontSee('name="edit_scope"', false);
    }
}
