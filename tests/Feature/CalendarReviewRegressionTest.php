<?php

declare(strict_types=1);
use App\Models\Booking;
use App\Models\Event;
use App\Models\Option;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use App\Services\BookingRules;
use App\Services\BookingService;
use App\Services\CalendarSegments;
use App\Services\EventService;
use App\Services\SquareValidator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CalendarReviewRegressionTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-09 10:00:00');
        config(['mail.default' => 'array', 'queue.default' => 'sync']);
    }

    private function booking(User $user, Square $square, string $date, string $start, string $end, string $status = 'single'): Booking
    {
        $b = Booking::create(['uid' => $user->uid, 'sid' => $square->sid, 'status' => $status, 'visibility' => 'public', 'quantity' => 2, 'status_billing' => 'pending', 'created' => now()]);
        $b->reservations()->create(['date' => $date, 'time_start' => $start, 'time_end' => $end]);

        return $b;
    }

    private function ownCellCount(string $html): int
    {
        $dom = new DOMDocument;
        @$dom->loadHTML($html);

        return (new DOMXPath($dom))->query('//a[@data-state="own"]')->length;
    }

    private function series(): array
    {
        $user = User::factory()->create(['status' => 'admin']);
        $square = Square::factory()->create();
        $b = $this->booking($user, $square, '2026-11-16', '10:00:00', '11:00:00', 'subscription');
        foreach (['2026-11-23', '2026-11-30'] as $date) {
            $b->reservations()->create(['date' => $date, 'time_start' => '10:00:00', 'time_end' => '11:00:00']);
        }
        $this->actingAs($user);

        return [$b, $user, $square];
    }

    #[Test]
    public function completed_bookings_do_not_consume_the_future_booking_limit(): void
    {
        $user = User::factory()->create();
        $square = Square::factory()->create();
        Option::updateOrCreate(['key' => 'booking.max_active_bookings', 'locale' => null], ['value' => '1']);
        $this->booking($user, $square, '2026-11-09', '08:00:00', '09:00:00');
        $result = app(SquareValidator::class)->validate($square, $user, 2, Carbon::parse('2026-11-10 10:00'), Carbon::parse('2026-11-10 11:00'));
        $this->assertTrue($result->isValid(), $result->getError() ?? '');
    }

    #[Test]
    public function a_reversed_interval_is_rejected_before_minimum_duration_normalization(): void
    {
        $user = User::factory()->create();
        $square = Square::factory()->create(['time_block_bookable' => 1800]);
        Option::updateOrCreate(['key' => BookingRules::TIME_BLOCK_MIN, 'locale' => null], ['value' => '1800']);
        $this->actingAs($user)->post('/bookings', ['sid' => $square->sid, 'date' => '2026-11-10', 'time_start' => 36000, 'time_end' => 32400, 'quantity' => 2, 'mitspieler' => 'Review partner'])->assertSessionHasErrors('booking');
        $this->assertSame(0, Reservation::count());
    }

    #[Test]
    public function a_half_hour_booking_is_visible_with_exact_times(): void
    {
        $owner = User::factory()->create(['alias' => 'ReviewHalfHourOwner']);
        $square = Square::factory()->create();
        $this->booking($owner, $square, '2026-11-10', '10:00:00', '10:30:00');
        $response = $this->actingAs(User::factory()->create())->get('/calendar?date=2026-11-10')->assertOk();
        $slots = $response->viewData('reservationsBySlot');
        $this->assertArrayHasKey(10, $slots['2026-11-10'][$square->sid]);
        $response->assertSee('ReviewHalfHourOwner');
        $response->assertSee('10:00–10:30');
    }

    #[Test]
    public function all_occupied_hours_of_a_fractional_booking_are_visible(): void
    {
        $owner = User::factory()->create();
        $square = Square::factory()->create();
        $b = $this->booking($owner, $square, '2026-11-10', '10:30:00', '11:30:00');
        $response = $this->get('/calendar?date=2026-11-10')->assertOk();
        $slots = $response->viewData('reservationsBySlot');
        $this->assertSame($b->bid, $slots['2026-11-10'][$square->sid][10]->bid);
        $this->assertArrayHasKey(11, $slots['2026-11-10'][$square->sid]);
        $response->assertSee('11:00–11:30');
    }

    #[Test]
    public function repeated_occurrence_cancellation_preserves_the_remaining_series(): void
    {
        [$b] = $this->series();
        $payload = ['occurrence' => '2026-11-23', 'edit_scope' => 'single_occurrence'];
        $this->post('/admin/bookings/'.$b->bid.'/cancel', $payload)->assertSessionHasNoErrors();
        $this->assertSame('subscription', $b->fresh()->status);
        $this->assertSame(2, $b->reservations()->count());
        $this->post('/admin/bookings/'.$b->bid.'/cancel', $payload)->assertSessionHasErrors('booking');
        $this->assertSame('subscription', $b->fresh()->status);
        $this->assertSame(2, $b->reservations()->count());
    }

    #[Test]
    public function a_missing_occurrence_cannot_delete_the_series(): void
    {
        [$b] = $this->series();
        $this->delete('/admin/bookings/'.$b->bid, ['occurrence' => '2026-11-24', 'edit_scope' => 'single_occurrence'])->assertSessionHasErrors('booking');
        $this->assertNotNull($b->fresh());
        $this->assertSame(3, Reservation::count());
    }

    #[Test]
    public function a_stale_occurrence_cannot_replace_the_series(): void
    {
        [$b,$user,$square] = $this->series();
        $this->put('/admin/bookings/'.$b->bid, ['occurrence' => '2026-11-24', 'edit_scope' => 'single_occurrence', 'booked_for' => $user->alias, 'sid' => $square->sid, 'date' => '2026-11-24', 'time_start' => '12:00', 'time_end' => '13:00', 'repeat_type' => 'once', 'quantity' => 2, 'mitspieler' => 'Review partner'])->assertSessionHasErrors('booking');
        $this->assertSame('subscription', $b->fresh()->status);
        $this->assertSame(['2026-11-16', '2026-11-23', '2026-11-30'], $b->reservations()->orderBy('date')->pluck('date')->all());
    }

    #[Test]
    public function irregular_series_metadata_updates_preserve_reservation_ids_and_meta(): void
    {
        [$b,$user,$square] = $this->series();
        $b->reservations()->where('date', '2026-11-30')->update(['date' => '2026-12-01']);
        $ids = $b->reservations()->orderBy('rid')->pluck('rid')->all();
        $b->reservations()->first()->meta()->create(['key' => 'review', 'value' => 'preserve']);
        $this->assertSame('custom', app(BookingService::class)->detectRepeatType($b->reservations()->orderBy('date')->get()->all()));
        $this->put('/admin/bookings/'.$b->bid, ['booked_for' => $user->alias, 'sid' => $square->sid, 'date' => '2026-11-16', 'date_end' => '2026-12-01', 'time_start' => '10:00', 'time_end' => '11:00', 'repeat_type' => 'custom', 'quantity' => 2, 'mitspieler' => 'Corrected partner'])->assertSessionHasNoErrors();
        $this->assertSame(['Corrected partner'], $b->fresh()->player_names);
        $this->assertSame(3, $b->reservations()->count());
        $this->assertSame($ids, $b->reservations()->orderBy('rid')->pluck('rid')->all());
        $this->assertDatabaseHas('bs_reservations_meta', ['key' => 'review', 'value' => 'preserve']);
    }

    #[Test]
    public function a_closure_conflict_is_rejected_with_a_resolution_list(): void
    {
        $owner = User::factory()->create(['alias' => 'ReviewClosureOwner']);
        $square = Square::factory()->create();
        $b = $this->booking($owner, $square, '2026-11-10', '10:00:00', '11:00:00');
        $before = $this->actingAs($owner)->get('/calendar?date=2026-11-10')->assertOk();
        $this->assertGreaterThan(0, $this->ownCellCount($before->getContent()));
        $this->actingAs(User::factory()->create(['status' => 'admin']))->post('/admin/events', [
            'sid' => $square->sid, 'kind' => 'closure', 'name' => 'Review closure', 'date_start' => '2026-11-10', 'date_end' => '2026-11-10', 'time_start' => '10:00', 'time_end' => '11:00',
        ])->assertSessionHasErrors('time_start')->assertSessionHas('event_conflicts');
        $this->assertSame(0, Event::count());
        $this->assertSame('single', $b->fresh()->status);
        $response = $this->actingAs($owner)->get('/calendar?date=2026-11-10')->assertOk()->assertDontSee('Review closure');
        $this->assertGreaterThan(0, $this->ownCellCount($response->getContent()));
        $this->assertSame(1, $b->reservations()->count());
    }

    #[Test]
    public function old_start_times_cannot_bypass_booking_limits(): void
    {
        $this->travelTo('2026-11-09 15:00:00');
        $user = User::factory()->create();
        $square = Square::factory()->create();
        foreach ([BookingRules::SHORT_BOOKING_WINDOW => 900, BookingRules::TIME_BLOCK_MAX => 3600, 'booking.max_active_bookings' => 1] as $key => $value) {
            Option::updateOrCreate(['key' => $key, 'locale' => null], ['value' => (string) $value]);
        }
        $this->booking($user, $square, '2026-11-10', '10:00:00', '11:00:00');
        $this->actingAs($user)->post('/bookings', ['sid' => $square->sid, 'date' => '2026-11-09', 'time_start' => 28800, 'time_end' => 57600, 'quantity' => 2, 'mitspieler' => 'Review partner'])->assertSessionHasErrors('booking');
        $this->assertSame(1, Booking::where('uid', $user->uid)->count());
        $this->assertDatabaseMissing('bs_reservations', ['date' => '2026-11-09', 'time_start' => '08:00:00', 'time_end' => '16:00:00']);
    }

    #[Test]
    public function unchanged_ambiguous_names_preserve_the_booking_owner(): void
    {
        $owner = User::factory()->create(['alias' => 'ReviewDuplicateOwner']);
        User::factory()->create(['alias' => 'ReviewDuplicateOwner']);
        $admin = User::factory()->create(['status' => 'admin', 'alias' => 'ReviewAdmin']);
        $square = Square::factory()->create();
        $b = $this->booking($owner, $square, '2026-11-10', '10:00:00', '11:00:00');
        $this->actingAs($admin)->put('/admin/bookings/'.$b->bid, [
            'booked_for' => $owner->alias, 'sid' => $square->sid, 'date' => '2026-11-10', 'time_start' => '10:00', 'time_end' => '11:00', 'repeat_type' => 'once', 'quantity' => 2, 'mitspieler' => 'Corrected partner',
        ])->assertSessionHasNoErrors();
        $this->assertSame($owner->uid, $b->fresh()->uid);
        $this->assertSame('ReviewDuplicateOwner', $b->fresh()->owner_label);
        $this->actingAs($owner)->get('/bookings/'.$b->bid.'/edit')->assertOk();
    }

    #[Test]
    public function adjacent_half_hours_remain_visible_and_a_short_gap_is_not_offered_as_bookable(): void
    {
        $square = Square::factory()->create();
        $user = User::factory()->create(['alias' => 'First half']);
        $other = User::factory()->create(['alias' => 'Second half']);
        $this->booking($user, $square, '2026-11-10', '10:00:00', '10:30:00');
        $this->booking($other, $square, '2026-11-10', '10:30:00', '11:00:00');
        $response = $this->actingAs($user)->get('/calendar?date=2026-11-10')->assertOk()->assertSee('First half')->assertSee('Second half');
        $dom = new DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new DOMXPath($dom);
        $this->assertSame(0, $xpath->query('//section[@data-day="1"]//div[@data-hour="10"]//a[@data-state="free"]')->length);
        // Default minimum is an hour: a half-hour gap cannot be offered before an occupied hour.
        $this->booking($other, $square, '2026-11-10', '12:30:00', '13:00:00');
        $reservations = Reservation::where('date', '2026-11-10')->get();
        $parts = app(CalendarSegments::class)->forHour($reservations, $square, 12);
        $this->assertTrue($parts[0]['tooShort']);
        $this->assertSame(43200, $parts[0]['start']);
    }

    #[Test]
    public function half_hour_free_gaps_use_exact_times_and_the_no_script_fallback_keeps_them(): void
    {
        $square = Square::factory()->create(['time_block_bookable' => 1800]);
        Option::updateOrCreate(['key' => BookingRules::TIME_BLOCK_MIN, 'locale' => null], ['value' => '1800']);
        $user = User::factory()->create();
        $this->booking($user, $square, '2026-11-10', '10:00:00', '10:30:00');
        $response = $this->actingAs($user)->get('/calendar?date=2026-11-10')->assertOk();
        $response->assertSee('timeStart: 37800', false)->assertSee('timeEnd: 39600', false);
        $this->get('/bookings/create?sid='.$square->sid.'&date=2026-11-10&time_start=10&slot_start=37800&slot_end=39600')->assertOk()
            ->assertSee('value="37800"', false)->assertSee('10:30 – 11:00');
    }

    #[Test]
    public function a_missing_occurrence_is_rejected_even_after_a_series_has_only_one_remaining_date(): void
    {
        [$booking] = $this->series();
        foreach (['2026-11-23', '2026-11-30'] as $date) {
            $this->post('/admin/bookings/'.$booking->bid.'/cancel', ['occurrence' => $date, 'edit_scope' => 'single_occurrence'])->assertSessionHasNoErrors();
        }
        $this->assertSame('single', $booking->fresh()->status);
        $this->delete('/admin/bookings/'.$booking->bid, ['occurrence' => '2026-11-30', 'edit_scope' => 'single_occurrence'])->assertSessionHasErrors('booking');
        $this->assertSame('single', $booking->fresh()->status);
        $this->assertSame(['2026-11-16'], $booking->reservations()->pluck('date')->all());
    }

    #[Test]
    public function irregular_series_reject_schedule_changes_and_keep_exact_intervals_on_a_court_move(): void
    {
        [$booking, $user, $square] = $this->series();
        $booking->reservations()->where('date', '2026-11-30')->update(['date' => '2026-12-01', 'time_end' => '11:30:00']);
        $ids = $booking->reservations()->orderBy('rid')->pluck('rid')->all();
        $target = Square::factory()->create();
        $data = ['booked_for' => $user->alias, 'sid' => $target->sid, 'date' => '2026-11-16', 'date_end' => '2026-12-01', 'time_start' => '10:00', 'time_end' => '11:00', 'repeat_type' => 'custom', 'quantity' => 2, 'mitspieler' => 'Partner'];
        $this->put('/admin/bookings/'.$booking->bid, array_replace($data, ['time_start' => '10:30']))->assertSessionHasErrors('booking');
        $this->put('/admin/bookings/'.$booking->bid, $data)->assertSessionHasNoErrors();
        $this->assertSame($target->sid, $booking->fresh()->sid);
        $this->assertSame($ids, $booking->reservations()->orderBy('rid')->pluck('rid')->all());
        $this->assertDatabaseHas('bs_reservations', ['bid' => $booking->bid, 'date' => '2026-12-01', 'time_end' => '11:30:00']);
    }

    #[Test]
    public function short_booking_override_includes_the_current_hour_but_has_a_future_upper_boundary(): void
    {
        $this->travelTo('2026-11-09 10:30:00');
        $user = User::factory()->create();
        $square = Square::factory()->create();
        foreach ([BookingRules::SHORT_BOOKING_WINDOW => 900, 'booking.max_active_bookings' => 1] as $key => $value) {
            Option::updateOrCreate(['key' => $key, 'locale' => null], ['value' => (string) $value]);
        }
        $this->booking($user, $square, '2026-11-10', '10:00:00', '11:00:00');
        $validator = app(SquareValidator::class);
        foreach (['10:00:00' => true, '10:45:00' => true, '10:45:01' => false, '09:59:59' => false] as $time => $expected) {
            $start = Carbon::parse('2026-11-09 '.$time);
            $result = $validator->validate($square, $user, 2, $start, $start->copy()->addHour());
            $this->assertSame($expected, $result->isValid(), $time);
        }
    }

    #[Test]
    public function completed_peak_occurrences_do_not_consume_the_peak_limit_but_ongoing_ones_do(): void
    {
        $user = User::factory()->create();
        $square = Square::factory()->create();
        foreach (['peak_limit.enabled' => '1', 'peak_limit.max' => '1', BookingRules::SHORT_BOOKING_WINDOW => '0'] as $key => $value) {
            Option::updateOrCreate(['key' => $key, 'locale' => null], ['value' => $value]);
        }
        $square->meta()->create(['key' => 'peak_limit_enabled', 'value' => '1']);
        $booking = $this->booking($user, $square, '2026-11-09', '08:00:00', '10:00:00');
        $validator = app(SquareValidator::class);
        $this->assertTrue($validator->validate($square, $user, 2, Carbon::parse('2026-11-10 09:00'), Carbon::parse('2026-11-10 10:00'))->isValid());
        $booking->reservations()->update(['time_end' => '10:00:01']);
        $this->assertFalse($validator->validate($square, $user, 2, Carbon::parse('2026-11-10 09:00'), Carbon::parse('2026-11-10 10:00'))->isValid());
    }

    #[Test]
    public function event_updates_are_atomic_global_closures_check_all_courts_and_touching_edges_are_allowed(): void
    {
        $user = User::factory()->create(['status' => 'admin']);
        $square = Square::factory()->create();
        $this->actingAs($user);
        $booking = $this->booking($user, $square, '2026-11-10', '10:00:00', '11:00:00');
        $data = ['sid' => null, 'name' => 'Original event', 'date_start' => '2026-11-10', 'date_end' => '2026-11-10', 'time_start' => '11:00', 'time_end' => '12:00', 'kind' => 'closure'];
        $service = app(EventService::class);
        $event = $service->create($data);
        $this->put('/admin/events/'.$event->eid, array_replace($data, ['name' => 'Rejected change', 'time_start' => '10:30']))->assertSessionHasErrors('time_start');
        $this->assertSame('11:00:00', $event->fresh()->datetime_start->format('H:i:s'));
        $this->assertSame('Original event', $event->fresh()->meta->firstWhere('key', 'name')->value);
        $booking->update(['status' => 'cancelled']);
        $this->put('/admin/events/'.$event->eid, array_replace($data, ['time_start' => '10:30']))->assertSessionHasNoErrors();
        $this->assertSame('10:30:00', $event->fresh()->datetime_start->format('H:i:s'));
    }

    #[Test]
    public function malformed_occurrence_scopes_cannot_mutate_a_series(): void
    {
        [$booking] = $this->series();
        $this->delete('/admin/bookings/'.$booking->bid, ['occurrence' => ['2026-11-23'], 'edit_scope' => 'single_occurrence'])->assertSessionHasErrors('occurrence');
        $this->post('/admin/bookings/'.$booking->bid.'/cancel', ['occurrence' => '2026-11-23', 'edit_scope' => 'unknown'])->assertSessionHasErrors('edit_scope');
        $this->assertSame('subscription', $booking->fresh()->status);
        $this->assertSame(3, $booking->reservations()->count());
    }

    #[Test]
    public function zero_length_is_rejected_but_positive_short_requests_still_expand_to_the_minimum(): void
    {
        $user = User::factory()->create();
        $square = Square::factory()->create(['time_block_bookable' => 1800]);
        $payload = ['sid' => $square->sid, 'date' => '2026-11-10', 'time_start' => 36000, 'time_end' => 36000, 'quantity' => 2, 'mitspieler' => 'Partner'];
        $this->actingAs($user)->post('/bookings', $payload)->assertSessionHasErrors('booking');
        $this->assertSame(0, Reservation::count());
        $this->post('/bookings', array_replace($payload, ['time_end' => 36900]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bs_reservations', ['date' => '2026-11-10', 'time_start' => '10:00:00', 'time_end' => '10:30:00']);
    }
}
