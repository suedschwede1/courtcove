<?php

declare(strict_types=1);

namespace Tests\Feature\PlayerExchange;

use App\Mail\BookingCancelled;
use App\Mail\PlayerSearchBookingCancelled;
use App\Mail\PlayerSearchExpired;
use App\Models\Booking;
use App\Models\PlayerSearch;
use App\Models\PlayerSignup;
use App\Models\Square;
use App\Models\User;
use App\Services\BookingService;
use App\Services\PlayerExchangeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verfall: one hour before the start an open search without signups lifts its booking (normal
 * cancel path, the booker gets the "search expired" mail instead of the cancel confirmation);
 * with signups the search only ends. Owner cancellations tell the joined players.
 */
class ExpireSearchTest extends TestCase
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

    private function searchingBooking(int $quantity = 4, int $needed = 2, ?Carbon $start = null, string $ownerEmail = 'owner@example.com'): Booking
    {
        $owner = User::factory()->create(['alias' => 'Olga Owner', 'email' => $ownerEmail]);
        $start ??= $this->start;

        return app(BookingService::class)->createSingle(
            $owner, Square::factory()->create(), $quantity, $start, $start->copy()->addHour(), [], $needed,
        );
    }

    private function member(string $alias, string $email): User
    {
        $user = User::factory()->create(['alias' => $alias, 'email' => $email]);
        $this->exchange()->optIn($user);

        return $user;
    }

    private function search(Booking $booking): PlayerSearch
    {
        return PlayerSearch::query()->where('bid', $booking->bid)->sole();
    }

    /** The deadline of the default booking: one hour before 10:00. */
    private function atDeadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-08 09:00:00'));
    }

    #[Test]
    public function search_without_signups_lapses_cancels_booking_and_mails_owner_once(): void
    {
        $booking = $this->searchingBooking();

        // One minute before the deadline nothing happens.
        Carbon::setTestNow(Carbon::parse('2026-11-08 08:59:00'));
        $this->assertSame(0, $this->exchange()->lapseExpired());
        $this->assertSame('single', $booking->fresh()->status);
        $this->assertSame(PlayerSearch::OPEN, $this->search($booking)->status);

        $this->atDeadline();
        $this->assertSame(1, $this->exchange()->lapseExpired());

        $fresh = $booking->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame('cancelled', $fresh->status_billing);
        $this->assertSame(PlayerSearch::LAPSED, $this->search($booking)->status);

        Mail::assertQueued(PlayerSearchExpired::class, 1);
        Mail::assertQueued(PlayerSearchExpired::class, fn (PlayerSearchExpired $m): bool => $m->hasTo('owner@example.com'));
        // No cancel confirmation for the booker, also not from deferred (after-response) jobs.
        $this->app->terminate();
        Mail::assertNotQueued(BookingCancelled::class);
        Mail::assertNotQueued(PlayerSearchBookingCancelled::class);
        Mail::assertQueued(PlayerSearchExpired::class, 1);
    }

    #[Test]
    public function search_with_signup_ends_but_booking_stays(): void
    {
        $booking = $this->searchingBooking(4, 2);
        $this->exchange()->join($this->member('Max Muster', 'max@example.com'), $booking);

        $this->atDeadline();
        $this->assertSame(0, $this->exchange()->lapseExpired());

        $this->assertSame('single', $booking->fresh()->status);
        $this->assertSame(PlayerSearch::ENDED, $this->search($booking)->status);
        $this->assertSame(1, PlayerSignup::query()->count());
        $this->assertSame(['Max Muster'], $booking->fresh(['meta'])->player_names);
        Mail::assertNotQueued(PlayerSearchExpired::class);
        Mail::assertNotQueued(PlayerSearchBookingCancelled::class);
    }

    #[Test]
    public function lapse_is_idempotent(): void
    {
        $booking = $this->searchingBooking();
        $filled = $this->searchingBooking(2, 1, null, 'filled@example.com');
        $this->exchange()->join($this->member('Max Muster', 'max@example.com'), $filled);

        $this->atDeadline();
        $this->assertSame(1, $this->exchange()->lapseExpired());
        $this->assertSame(0, $this->exchange()->lapseExpired());
        $this->assertSame(0, $this->exchange()->lapseExpired($booking->bid));

        $this->assertSame(PlayerSearch::LAPSED, $this->search($booking)->status);
        $this->assertSame('cancelled', $booking->fresh()->status);
        // A filled search is not open any more: it is left alone, the booking stays.
        $this->assertSame(PlayerSearch::FILLED, $this->search($filled)->status);
        $this->assertSame('single', $filled->fresh()->status);
        Mail::assertQueued(PlayerSearchExpired::class, 1);
    }

    #[Test]
    public function lapse_for_one_booking_leaves_the_others_alone(): void
    {
        $first = $this->searchingBooking();
        $second = $this->searchingBooking(4, 2, null, 'second@example.com');

        $this->atDeadline();
        $this->assertSame(1, $this->exchange()->lapseExpired($second->bid));

        $this->assertSame(PlayerSearch::OPEN, $this->search($first)->status);
        $this->assertSame('single', $first->fresh()->status);
        $this->assertSame(PlayerSearch::LAPSED, $this->search($second)->status);
        Mail::assertQueued(PlayerSearchExpired::class, fn (PlayerSearchExpired $m): bool => $m->hasTo('second@example.com'));
        Mail::assertQueued(PlayerSearchExpired::class, 1);
    }

    #[Test]
    public function without_mail_configuration_the_booking_still_lapses(): void
    {
        config(['mail.default' => 'array']);
        $booking = $this->searchingBooking();

        $this->atDeadline();
        $this->assertSame(1, $this->exchange()->lapseExpired());

        $this->assertSame('cancelled', $booking->fresh()->status);
        Mail::assertNothingOutgoing();
    }

    #[Test]
    public function command_runs_lapse(): void
    {
        $booking = $this->searchingBooking();
        $this->atDeadline();

        $this->artisan('player-exchange:expire')->assertSuccessful();

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(PlayerSearch::LAPSED, $this->search($booking)->status);
        Mail::assertQueued(PlayerSearchExpired::class, 1);

        // A second run (or an overlapping one) sends nothing more.
        $this->artisan('player-exchange:expire')->assertSuccessful();
        Mail::assertQueued(PlayerSearchExpired::class, 1);
    }

    #[Test]
    public function calendar_load_lapses_expired_searches(): void
    {
        $booking = $this->searchingBooking();
        $later = $this->searchingBooking(4, 2, Carbon::parse('2026-11-08 10:30:00'), 'later@example.com');
        $viewer = User::factory()->create();

        $this->atDeadline();
        $this->actingAs($viewer)->get(route('calendar.index', ['date' => '2026-11-08']))->assertOk();

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(PlayerSearch::LAPSED, $this->search($booking)->status);
        $this->assertSame(PlayerSearch::OPEN, $this->search($later)->status, 'its deadline is 09:30');
        $this->assertSame('single', $later->fresh()->status);
        Mail::assertQueued(PlayerSearchExpired::class, 1);
    }

    #[Test]
    public function calendar_lapse_check_runs_at_most_once_a_minute(): void
    {
        $booking = $this->searchingBooking();
        $viewer = User::factory()->create();

        $this->atDeadline();
        Cache::put('player-exchange.lapse', 1, 60);
        $this->actingAs($viewer)->get(route('calendar.index', ['date' => '2026-11-08']))->assertOk();
        $this->assertSame(PlayerSearch::OPEN, $this->search($booking)->status, 'the throttle key is set, no check');

        Cache::forget('player-exchange.lapse');
        $this->get(route('calendar.index', ['date' => '2026-11-08']))->assertOk();
        $this->assertSame(PlayerSearch::LAPSED, $this->search($booking)->status);
        $this->assertTrue(Cache::has('player-exchange.lapse'));
    }

    #[Test]
    public function a_join_after_the_deadline_lapses_the_search_first(): void
    {
        $booking = $this->searchingBooking();
        $member = $this->member('Max Muster', 'max@example.com');

        $this->atDeadline();
        $this->actingAs($member)
            ->postJson(route('player-exchange.join', $booking))
            ->assertStatus(422)
            ->assertExactJson(['error' => __('booking.messages.player_exchange.not_open')]);

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(PlayerSearch::LAPSED, $this->search($booking)->status);
        $this->assertSame(0, PlayerSignup::query()->count());
        Mail::assertQueued(PlayerSearchExpired::class, 1);
    }

    #[Test]
    public function owner_cancelling_mails_the_joined_players(): void
    {
        $booking = $this->searchingBooking(4, 3);
        $this->exchange()->join($this->member('Max Muster', 'max@example.com'), $booking);
        $erika = $this->member('Erika Beispiel', 'erika@example.com');
        $this->exchange()->join($erika, $booking);
        // Opting out later does not stop the mail about a booking the player joined.
        $this->exchange()->optOut($erika);

        app(BookingService::class)->cancelSingle($booking->fresh());
        $this->app->terminate();

        Mail::assertQueued(PlayerSearchBookingCancelled::class, 2);
        Mail::assertQueued(PlayerSearchBookingCancelled::class, fn ($m): bool => $m->hasTo('max@example.com'));
        Mail::assertQueued(PlayerSearchBookingCancelled::class, fn ($m): bool => $m->hasTo('erika@example.com'));
        Mail::assertQueued(BookingCancelled::class, fn ($m): bool => $m->hasTo('owner@example.com'));
        Mail::assertNotQueued(PlayerSearchExpired::class);
    }

    #[Test]
    public function owner_cancel_marks_search_cancelled(): void
    {
        $booking = $this->searchingBooking(4, 2);
        $this->exchange()->join($this->member('Max Muster', 'max@example.com'), $booking);

        $this->actingAs($booking->user)
            ->delete(route('bookings.destroy', $booking))
            ->assertRedirect(route('calendar.index'));

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(PlayerSearch::CANCELLED, $this->search($booking)->status);
        Mail::assertQueued(PlayerSearchBookingCancelled::class, 1);

        // A cancelled search never lapses and sends no further mail.
        $this->atDeadline();
        $this->assertSame(0, $this->exchange()->lapseExpired());
        $this->assertSame(PlayerSearch::CANCELLED, $this->search($booking)->status);
        Mail::assertNotQueued(PlayerSearchExpired::class);
        Mail::assertQueued(PlayerSearchBookingCancelled::class, 1);
    }

    #[Test]
    public function cancelling_a_booking_without_search_changes_nothing_in_the_exchange(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $booking = app(BookingService::class)->createSingle(
            $owner, Square::factory()->create(), 2, $this->start, $this->start->copy()->addHour(), [],
        );

        app(BookingService::class)->cancelSingle($booking);

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(0, PlayerSearch::query()->count());
        Mail::assertNotQueued(PlayerSearchBookingCancelled::class);
    }

    #[Test]
    public function expiry_and_cancel_mails_render_after_the_rows_are_gone(): void
    {
        $booking = $this->searchingBooking(4, 2);
        $max = $this->member('Max Muster', 'max@example.com');
        $search = $this->search($booking);
        $squareName = $booking->square->display_name;

        // Queue payloads: serialised now, rendered by a worker after the rows were deleted.
        $expiredPayload = serialize(new PlayerSearchExpired($search));
        $cancelledPayload = serialize(new PlayerSearchBookingCancelled($search, $max));
        $search->delete();
        app(BookingService::class)->deleteSingle($booking->fresh());
        $max->delete();
        User::query()->whereKey($booking->uid)->delete();

        $expired = unserialize($expiredPayload);
        $cancelled = unserialize($cancelledPayload);
        foreach ([$expired, $cancelled] as $mail) {
            $this->assertStringContainsString($squareName, $mail->envelope()->subject);
            $html = $mail->render();
            $this->assertStringContainsString('10:00', $html);
            $this->assertStringContainsString($squareName, $html);
        }
        $this->assertStringContainsString('Olga Owner', $expired->render());
        $this->assertStringContainsString('Max Muster', $cancelled->render());
    }
}
