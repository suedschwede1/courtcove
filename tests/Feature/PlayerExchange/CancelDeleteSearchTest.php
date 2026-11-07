<?php

declare(strict_types=1);

namespace Tests\Feature\PlayerExchange;

use App\Mail\BookingCancelled;
use App\Mail\PlayerSearchBookingCancelled;
use App\Mail\PlayerSearchExpired;
use App\Models\Booking;
use App\Models\PlayerSearch;
use App\Models\PlayerSignup;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use App\Services\BookingService;
use App\Services\PlayerExchangeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cancel and delete keep the player search and its signups consistent (PX-03): a deleted
 * booking leaves no search or signup rows behind, every joined player gets the cancel mail
 * exactly once (also after opting out of the exchange mails), a cancel that comes after the
 * lapse sends nothing more, and subscriptions never carry a search.
 */
class CancelDeleteSearchTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Carbon $start;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.com',
        ]);
        Carbon::setTestNow(Carbon::parse('2026-11-06 08:00:00'));
        $this->start = Carbon::parse('2026-11-08 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function exchange(): PlayerExchangeService
    {
        return app(PlayerExchangeService::class);
    }

    private function service(): BookingService
    {
        return app(BookingService::class);
    }

    private function searchingBooking(int $quantity = 4, int $needed = 2): Booking
    {
        $owner = User::factory()->create(['alias' => 'Olga Owner', 'email' => 'owner@example.com']);

        return $this->service()->createSingle(
            $owner, Square::factory()->create(), $quantity, $this->start, $this->start->copy()->addHour(), [], $needed,
        );
    }

    private function member(string $alias, string $email): User
    {
        $user = User::factory()->create(['alias' => $alias, 'email' => $email]);
        $this->exchange()->optIn($user);

        return $user;
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    private function assertNoExchangeRows(): void
    {
        $this->assertSame(0, PlayerSearch::query()->count(), 'no orphan search');
        $this->assertSame(0, PlayerSignup::query()->count(), 'no orphan signup');
    }

    #[Test]
    public function admin_delete_mails_every_joined_player_once_and_leaves_no_search_or_signup(): void
    {
        $booking = $this->searchingBooking(4, 3);
        $this->exchange()->join($this->member('Max Muster', 'max@example.com'), $booking);
        $erika = $this->member('Erika Beispiel', 'erika@example.com');
        $this->exchange()->join($erika, $booking);
        // Opting out only stops the advertising mails, not the mail about a booking he joined.
        $this->exchange()->optOut($erika);

        $this->actingAs($this->admin())
            ->delete("/admin/bookings/{$booking->bid}")
            ->assertRedirect(route('admin.bookings.index'));

        $this->assertDatabaseMissing('bs_bookings', ['bid' => $booking->bid]);
        $this->assertNoExchangeRows();
        Mail::assertQueued(PlayerSearchBookingCancelled::class, 2);
        Mail::assertQueued(PlayerSearchBookingCancelled::class, fn ($m): bool => $m->hasTo('max@example.com'));
        Mail::assertQueued(PlayerSearchBookingCancelled::class, fn ($m): bool => $m->hasTo('erika@example.com'));
        Mail::assertQueued(PlayerSearchBookingCancelled::class, fn (PlayerSearchBookingCancelled $m): bool => $m->details['date'] === '2026-11-08'
            && $m->details['timeStart'] === '10:00');
        Mail::assertNotQueued(BookingCancelled::class);
    }

    #[Test]
    public function the_delete_mail_renders_after_the_rows_are_gone(): void
    {
        $booking = $this->searchingBooking(4, 2);
        $this->exchange()->join($this->member('Max Muster', 'max@example.com'), $booking);

        $this->service()->deleteSingle($booking->fresh());

        $sent = null;
        Mail::assertQueued(PlayerSearchBookingCancelled::class, function (PlayerSearchBookingCancelled $m) use (&$sent): bool {
            $sent = $m;

            return true;
        });
        $this->assertNotNull($sent);
        $mail = unserialize(serialize($sent));
        $this->assertStringContainsString('Max Muster', $mail->render());
    }

    #[Test]
    public function deleting_a_booking_whose_search_is_already_cancelled_or_lapsed_sends_nothing_and_removes_the_rows(): void
    {
        $booking = $this->searchingBooking(4, 2);
        $this->exchange()->join($this->member('Max Muster', 'max@example.com'), $booking);
        $this->service()->cancelSingle($booking->fresh());
        Mail::assertQueued(PlayerSearchBookingCancelled::class, 1);

        $this->service()->deleteSingle($booking->fresh());

        $this->assertNoExchangeRows();
        Mail::assertQueued(PlayerSearchBookingCancelled::class, 1);

        // Lapsed: the booker already got the "search expired" mail.
        $lapsing = $this->searchingBooking(2, 1);
        Carbon::setTestNow(Carbon::parse('2026-11-08 09:00:00'));
        $this->assertSame(1, $this->exchange()->lapseExpired());

        $this->service()->deleteSingle($lapsing->fresh());

        $this->assertNoExchangeRows();
        Mail::assertQueued(PlayerSearchExpired::class, 1);
        Mail::assertQueued(PlayerSearchBookingCancelled::class, 1);
    }

    #[Test]
    public function the_lapse_stays_idempotent_after_a_delete(): void
    {
        $booking = $this->searchingBooking(2, 1);
        $this->service()->deleteSingle($booking->fresh());

        Carbon::setTestNow(Carbon::parse('2026-11-08 09:00:00'));
        $this->assertSame(0, $this->exchange()->lapseExpired());
        $this->assertSame(0, $this->exchange()->lapseExpired());

        $this->assertNoExchangeRows();
        Mail::assertNotQueued(PlayerSearchExpired::class);
        Mail::assertNotQueued(PlayerSearchBookingCancelled::class);
    }

    #[Test]
    public function cancelling_twice_mails_each_joined_player_and_the_booker_only_once(): void
    {
        $booking = $this->searchingBooking(4, 2);
        $this->exchange()->join($this->member('Max Muster', 'max@example.com'), $booking);
        $stale = $booking->fresh();

        $this->assertTrue($this->service()->cancelSingle($booking->fresh()));
        // A second request still holding the booking as it was before the cancel.
        $this->assertFalse($this->service()->cancelSingle($stale));
        $this->app->terminate();

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(PlayerSearch::CANCELLED, PlayerSearch::query()->sole()->status);
        Mail::assertQueued(PlayerSearchBookingCancelled::class, 1);
        Mail::assertQueued(BookingCancelled::class, 1);
    }

    #[Test]
    public function a_cancel_after_the_lapse_sends_no_cancel_confirmation_on_top_of_the_expiry_mail(): void
    {
        $booking = $this->searchingBooking(2, 1);
        $stale = $booking->fresh();
        Carbon::setTestNow(Carbon::parse('2026-11-08 09:00:00'));
        $this->assertSame(1, $this->exchange()->lapseExpired());

        $this->assertFalse($this->service()->cancelSingle($stale));
        $this->app->terminate();

        $this->assertSame(PlayerSearch::LAPSED, PlayerSearch::query()->sole()->status);
        Mail::assertQueued(PlayerSearchExpired::class, 1);
        Mail::assertNotQueued(BookingCancelled::class);
        Mail::assertNotQueued(PlayerSearchBookingCancelled::class);
    }

    #[Test]
    public function the_owner_cancelling_a_lapsed_booking_is_told_it_is_already_cancelled_and_gets_no_cancel_mail(): void
    {
        $booking = $this->searchingBooking(2, 1);
        // The court allows cancelling up to 30 minutes before the start, so the booker may still
        // cancel after the lapse (one hour before the start).
        $booking->square->update(['range_cancel' => 1800]);
        Carbon::setTestNow(Carbon::parse('2026-11-08 09:00:00'));
        $this->assertSame(1, $this->exchange()->lapseExpired());

        $this->actingAs($booking->user)
            ->delete(route('bookings.destroy', $booking))
            ->assertSessionHasErrors(['booking' => __('booking.messages.booking_already_cancelled')]);

        Mail::assertQueued(PlayerSearchExpired::class, 1);
        Mail::assertNotQueued(BookingCancelled::class);
    }

    #[Test]
    public function an_admin_cancelling_a_lapsed_booking_is_told_it_is_already_cancelled(): void
    {
        $booking = $this->searchingBooking(2, 1);
        Carbon::setTestNow(Carbon::parse('2026-11-08 09:00:00'));
        $this->assertSame(1, $this->exchange()->lapseExpired());

        $this->actingAs($this->admin())
            ->post(route('admin.bookings.cancel', $booking))
            ->assertRedirect(route('admin.bookings.index'))
            ->assertSessionHas('error', __('booking.messages.booking_already_cancelled'))
            ->assertSessionMissing('success');

        $this->app->terminate();
        Mail::assertQueued(PlayerSearchExpired::class, 1);
        Mail::assertNotQueued(BookingCancelled::class);
    }

    #[Test]
    public function the_lapse_after_a_cancel_lifts_nothing_and_mails_no_expiry(): void
    {
        $booking = $this->searchingBooking(2, 1);
        $this->assertTrue($this->service()->cancelSingle($booking->fresh()));

        Carbon::setTestNow(Carbon::parse('2026-11-08 09:00:00'));
        $this->assertSame(0, $this->exchange()->lapseExpired());
        $this->app->terminate();

        $this->assertSame(PlayerSearch::CANCELLED, PlayerSearch::query()->sole()->status);
        Mail::assertNotQueued(PlayerSearchExpired::class);
        Mail::assertQueued(BookingCancelled::class, 1);
    }

    #[Test]
    public function a_cancelled_booking_whose_search_was_missed_still_tells_the_joined_players_once(): void
    {
        // Legacy state: the booking was cancelled without the exchange noticing.
        $booking = $this->searchingBooking(4, 2);
        $this->exchange()->join($this->member('Max Muster', 'max@example.com'), $booking);
        Booking::query()->whereKey($booking->bid)->update(['status' => 'cancelled', 'status_billing' => 'cancelled']);

        $this->assertFalse($this->service()->cancelSingle($booking->fresh()));
        $this->assertFalse($this->service()->cancelSingle($booking->fresh()));
        $this->app->terminate();

        $this->assertSame(PlayerSearch::CANCELLED, PlayerSearch::query()->sole()->status);
        Mail::assertQueued(PlayerSearchBookingCancelled::class, 1);
        Mail::assertNotQueued(BookingCancelled::class);
    }

    #[Test]
    public function a_subscription_never_gets_a_player_search(): void
    {
        [$subscription] = $this->subscription();

        $this->expectException(\LogicException::class);
        $this->exchange()->openSearch($subscription, 1);
    }

    #[Test]
    public function cancelling_or_deleting_one_occurrence_of_a_subscription_leaves_a_search_of_the_series_alone(): void
    {
        // Forged state (a series can never open a search): the series keeps running, so its
        // search must not be cancelled through the split-off occurrence.
        [$subscription] = $this->subscription();
        $search = PlayerSearch::query()->create([
            'bid' => $subscription->bid, 'needed' => 1, 'ends_at' => '2026-11-15 09:00:00', 'status' => PlayerSearch::OPEN,
        ]);
        $max = $this->member('Max Muster', 'max@example.com');
        PlayerSignup::query()->create(['search_id' => $search->id, 'uid' => $max->uid, 'slot' => 2]);

        $this->assertTrue($this->service()->cancelSingle($subscription->fresh(), '2026-11-22', 'single_occurrence'));
        $this->service()->deleteSingle($subscription->fresh(), '2026-11-29', 'single_occurrence');

        $this->assertSame('subscription', $subscription->fresh()->status);
        $this->assertSame(PlayerSearch::OPEN, $search->fresh()->status);
        $this->assertSame(1, PlayerSignup::query()->count());
        Mail::assertNotQueued(PlayerSearchBookingCancelled::class);

        // Cancelling the whole series does cancel it and tells the player once.
        $this->assertTrue($this->service()->cancelSingle($subscription->fresh()));
        $this->assertSame(PlayerSearch::CANCELLED, $search->fresh()->status);
        Mail::assertQueued(PlayerSearchBookingCancelled::class, 1);
    }

    #[Test]
    public function a_booking_without_search_is_cancelled_and_deleted_as_before(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $square = Square::factory()->create();
        $cancel = $this->service()->createSingle($owner, $square, 2, $this->start, $this->start->copy()->addHour(), []);
        $delete = $this->service()->createSingle(
            $owner, $square, 2, $this->start->copy()->addHours(2), $this->start->copy()->addHours(3), [],
        );

        // Admin delete first: every test request runs the after-response jobs queued so far again.
        $this->actingAs($this->admin())
            ->delete("/admin/bookings/{$delete->bid}")
            ->assertRedirect(route('admin.bookings.index'));
        $this->actingAs($owner)
            ->delete(route('bookings.destroy', $cancel))
            ->assertRedirect(route('calendar.index'))
            ->assertSessionHas('success', __('booking.messages.booking_cancelled'));

        $this->assertSame('cancelled', $cancel->fresh()->status);
        $this->assertDatabaseMissing('bs_bookings', ['bid' => $delete->bid]);
        $this->assertDatabaseMissing('bs_reservations', ['bid' => $delete->bid]);
        Mail::assertQueued(BookingCancelled::class, 1);
        Mail::assertQueued(BookingCancelled::class, fn ($m): bool => $m->hasTo('owner@example.com'));
        Mail::assertNotQueued(PlayerSearchBookingCancelled::class);
    }

    /** @return array{0: Booking} a weekly series of four occurrences from 2026-11-15 */
    private function subscription(): array
    {
        $owner = User::factory()->create(['alias' => 'Sabine Serie', 'email' => 'serie@example.com']);
        $booking = Booking::factory()->create([
            'uid' => $owner->uid, 'sid' => Square::factory()->create()->sid, 'status' => 'subscription', 'quantity' => 2,
        ]);
        foreach (['2026-11-15', '2026-11-22', '2026-11-29', '2026-12-06'] as $date) {
            Reservation::factory()->create([
                'bid' => $booking->bid, 'date' => $date, 'time_start' => '10:00:00', 'time_end' => '11:00:00',
            ]);
        }

        return [$booking->fresh(['reservations', 'meta'])];
    }
}
