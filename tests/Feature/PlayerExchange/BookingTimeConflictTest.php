<?php

declare(strict_types=1);

namespace Tests\Feature\PlayerExchange;

use App\Exceptions\BookingValidationException;
use App\Models\Booking;
use App\Models\PlayerSearch;
use App\Models\Square;
use App\Models\User;
use App\Services\BookingService;
use App\Services\MemberAvailability;
use App\Services\PlayerExchangeService;
use Carbon\Carbon;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * PX-02, the reverse direction of the join busy check: a member who joined a player search at a
 * time cannot book a court for himself at an overlapping time (BookingService::createSingle).
 * Join and booking use the same check (MemberAvailability), with the same overlap rule as the
 * court check: back-to-back slots stay allowed.
 */
class BookingTimeConflictTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Carbon $start;

    private Booking $searchBooking;

    private User $max;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-06 08:00:00'));
        $this->start = Carbon::parse('2026-11-08 10:00:00');

        $owner = User::factory()->create(['alias' => 'Olga Owner']);
        $this->searchBooking = app(BookingService::class)->createSingle(
            $owner, Square::factory()->create(['capacity' => 4]), 4, $this->start, $this->start->copy()->addHour(), [], 3,
        );

        $this->max = User::factory()->create(['alias' => 'Max Muster']);
        $this->exchange()->optIn($this->max);
    }

    private function exchange(): PlayerExchangeService
    {
        return app(PlayerExchangeService::class);
    }

    private function book(User $user, string $from, string $to): Booking
    {
        return app(BookingService::class)->createSingle(
            $user, Square::factory()->create(), 2, Carbon::parse($from), Carbon::parse($to), [], 0,
        );
    }

    private function assertBookingRefused(User $user, string $from, string $to): void
    {
        $before = Booking::query()->count();
        try {
            $this->book($user, $from, $to);
            $this->fail("booking {$from} - {$to} must be refused while the member plays elsewhere");
        } catch (BookingValidationException $e) {
            $this->assertSame(__('booking.messages.player_exchange.booking_busy'), $e->getMessage());
        }
        $this->assertSame($before, Booking::query()->count(), 'nothing was booked');
    }

    #[Test]
    public function a_member_who_joined_a_search_cannot_book_another_court_at_an_overlapping_time(): void
    {
        $this->exchange()->join($this->max, $this->searchBooking);

        $this->assertBookingRefused($this->max, '2026-11-08 10:00', '2026-11-08 11:00');
        $this->assertBookingRefused($this->max, '2026-11-08 10:30', '2026-11-08 11:30');
        $this->assertBookingRefused($this->max, '2026-11-08 09:30', '2026-11-08 10:30');
        $this->assertBookingRefused($this->max, '2026-11-08 09:00', '2026-11-08 12:00');
    }

    #[Test]
    public function back_to_back_slots_and_other_days_stay_bookable(): void
    {
        $this->exchange()->join($this->max, $this->searchBooking);

        $this->book($this->max, '2026-11-08 09:00', '2026-11-08 10:00');
        $this->book($this->max, '2026-11-08 11:00', '2026-11-08 12:00');
        $this->book($this->max, '2026-11-09 10:00', '2026-11-09 11:00');

        $this->assertSame(3, Booking::query()->where('uid', $this->max->uid)->count());
    }

    #[Test]
    public function the_booking_dialog_shows_why_the_booking_is_refused(): void
    {
        $this->exchange()->join($this->max, $this->searchBooking);
        $square = Square::factory()->create();
        $payload = [
            'sid' => $square->sid, 'date' => '2026-11-08', 'time_start' => 10 * 3600, 'time_end' => 11 * 3600,
            'quantity' => 2, 'mitspieler' => 'Paula Partner',
        ];

        $this->actingAs($this->max)
            ->post(route('bookings.store'), $payload, ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertExactJson(['error' => __('booking.messages.player_exchange.booking_busy')]);

        $this->actingAs($this->max)->post(route('bookings.store'), $payload)
            ->assertSessionHasErrors(['booking' => __('booking.messages.player_exchange.booking_busy')]);

        $this->assertSame(0, Booking::query()->where('uid', $this->max->uid)->count());
    }

    #[Test]
    public function filled_and_ended_searches_still_hold_the_member(): void
    {
        $owner = User::factory()->create(['alias' => 'Otto Anders']);
        $filled = app(BookingService::class)->createSingle(
            $owner, Square::factory()->create(), 2, Carbon::parse('2026-11-08 15:00'), Carbon::parse('2026-11-08 16:00'), [], 1,
        );
        $this->exchange()->join($this->max, $filled);
        $this->assertSame(PlayerSearch::FILLED, PlayerSearch::query()->where('bid', $filled->bid)->value('status'));
        $this->assertBookingRefused($this->max, '2026-11-08 15:00', '2026-11-08 16:00');

        $this->exchange()->join($this->max, $this->searchBooking);
        $this->exchange()->endSearch($this->searchBooking->user, $this->searchBooking);
        $this->assertSame(PlayerSearch::ENDED, PlayerSearch::query()->where('bid', $this->searchBooking->bid)->value('status'));
        $this->assertBookingRefused($this->max, '2026-11-08 10:00', '2026-11-08 11:00');
    }

    #[Test]
    public function a_signup_that_no_longer_holds_a_place_does_not_block(): void
    {
        // Left again: no signup.
        $this->exchange()->join($this->max, $this->searchBooking);
        $this->exchange()->leave($this->max, $this->searchBooking);
        $this->book($this->max, '2026-11-08 10:00', '2026-11-08 11:00');

        // Lapsed search (its booking stays active here only to prove the search status counts).
        $lapsed = app(BookingService::class)->createSingle(
            User::factory()->create(), Square::factory()->create(), 2, Carbon::parse('2026-11-08 13:00'), Carbon::parse('2026-11-08 14:00'), [], 1,
        );
        $this->exchange()->join($this->max, $lapsed);
        PlayerSearch::query()->where('bid', $lapsed->bid)->update(['status' => PlayerSearch::LAPSED]);
        $this->book($this->max, '2026-11-08 13:00', '2026-11-08 14:00');

        // Booking cancelled by its booker: search cancelled, booking cancelled.
        $cancelled = app(BookingService::class)->createSingle(
            User::factory()->create(), Square::factory()->create(), 2, Carbon::parse('2026-11-08 17:00'), Carbon::parse('2026-11-08 18:00'), [], 1,
        );
        $this->exchange()->join($this->max, $cancelled);
        app(BookingService::class)->cancelSingle($cancelled);
        $this->book($this->max, '2026-11-08 17:00', '2026-11-08 18:00');

        $this->assertSame(3, Booking::query()->where('uid', $this->max->uid)->count());
    }

    #[Test]
    public function bookings_without_signups_behave_as_before(): void
    {
        // No cross-court rule for own bookings: a member may still hold two courts at once,
        // and members who did not join anything are not affected by someone else's signup.
        $this->exchange()->join($this->max, $this->searchBooking);
        $erika = User::factory()->create(['alias' => 'Erika Beispiel']);

        $this->book($erika, '2026-11-08 10:00', '2026-11-08 11:00');
        $this->book($erika, '2026-11-08 10:00', '2026-11-08 11:00');

        $this->assertSame(2, Booking::query()->where('uid', $erika->uid)->count());
    }

    #[Test]
    public function join_and_booking_use_the_same_availability_check(): void
    {
        $availability = app(MemberAvailability::class);
        $from = Carbon::parse('2026-11-08 10:30');
        $to = Carbon::parse('2026-11-08 11:30');

        $this->assertFalse($availability->joinedAt($this->max, $from, $to));
        $this->exchange()->join($this->max, $this->searchBooking);
        $this->assertTrue($availability->joinedAt($this->max, $from, $to));
        $this->assertFalse($availability->joinedAt($this->max, $from, $to, (int) $this->searchBooking->bid), 'the booking itself is left out');
        $this->assertFalse($availability->joinedAt($this->max, Carbon::parse('2026-11-08 11:00'), Carbon::parse('2026-11-08 12:00')));

        // The join direction counts own bookings too.
        $this->assertTrue($availability->busyAt($this->max, $from, $to));
        $this->assertTrue($availability->busyAt($this->searchBooking->user, $from, $to));
        $this->assertFalse($availability->joinedAt($this->searchBooking->user, $from, $to));
        $this->assertSame(
            $availability->busyAt($this->max, $from, $to),
            $this->exchange()->userBusy($this->max, $from, $to),
        );
    }

    #[Test]
    public function the_booking_locks_the_court_and_then_the_member_row_before_it_reads(): void
    {
        $base = DB::transactionLevel();

        /** @var list<string> $log */
        $log = [];
        DB::listen(function (QueryExecuted $q) use (&$log, $base): void {
            if ($q->connection->transactionLevel() > $base) {
                $log[] = $q->sql;
            }
        });

        $this->book($this->max, '2026-11-08 12:00', '2026-11-08 13:00');

        // On MySQL both are FOR UPDATE (see BookingTimeConflictMysqlTest); a plain read before
        // them would fix a snapshot that misses a join committed while this booking waited.
        $this->assertMatchesRegularExpression('/^select \* from "bs_squares" where "bs_squares"\."sid" = \?/', $log[0] ?? '');
        $this->assertMatchesRegularExpression('/^select \* from "bs_users" where "bs_users"\."uid" = \?/', $log[1] ?? '');
    }
}
