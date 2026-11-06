<?php

declare(strict_types=1);

namespace Tests\Feature\PlayerExchange;

use App\Exceptions\PlayerExchangeException;
use App\Models\Booking;
use App\Models\PlayerSearch;
use App\Models\PlayerSignup;
use App\Models\Square;
use App\Models\User;
use App\Services\BookingService;
use App\Services\PlayerExchangeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JoinSearchTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Carbon $start;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-06 08:00:00'));
        $this->start = Carbon::parse('2026-11-08 10:00:00');
    }

    private function exchange(): PlayerExchangeService
    {
        return app(PlayerExchangeService::class);
    }

    /** @param array<int, string> $names slot => name */
    private function searchingBooking(int $quantity, int $needed, array $names = [], ?Square $square = null, ?Carbon $start = null): Booking
    {
        $owner = User::factory()->create(['alias' => 'Olga Owner']);
        $start ??= $this->start;
        $meta = app(BookingService::class)->buildPlayerMeta($names);

        return app(BookingService::class)->createSingle(
            $owner, $square ?? Square::factory()->create(), $quantity, $start, $start->copy()->addHour(), $meta, $needed,
        );
    }

    private function member(string $alias = 'Max Muster'): User
    {
        $user = User::factory()->create(['alias' => $alias]);
        $this->exchange()->optIn($user);

        return $user;
    }

    private function assertRefused(callable $action, string $key): void
    {
        try {
            $action();
            $this->fail('Expected PlayerExchangeException: '.$key);
        } catch (PlayerExchangeException $e) {
            $this->assertSame(__('booking.messages.player_exchange.'.$key), $e->getMessage());
        }
    }

    #[Test]
    public function member_can_join_and_name_appears_in_booking_players(): void
    {
        $booking = $this->searchingBooking(4, 2, [2 => 'Paula Partner']);
        $member = $this->member('Max Muster');

        $this->actingAs($member)
            ->post(route('player-exchange.join', $booking))
            ->assertRedirect(route('calendar.index', ['date' => '2026-11-08']))
            ->assertSessionHas('success', __('booking.messages.player_exchange.joined'));

        $signup = PlayerSignup::query()->sole();
        $this->assertSame($member->uid, (int) $signup->uid);
        $this->assertSame(3, (int) $signup->slot, 'first free slot after the named partner');

        $fresh = $booking->fresh(['meta']);
        $this->assertSame(['Paula Partner', 'Max Muster'], $fresh->player_names);
        $search = PlayerSearch::query()->where('bid', $booking->bid)->sole();
        $this->assertSame(PlayerSearch::OPEN, $search->status);
        $this->assertSame(1, $search->remaining());

        // Second member takes the last place: slot 4, search filled, AJAX answer.
        $other = $this->member('Erika Beispiel');
        $this->actingAs($other)
            ->postJson(route('player-exchange.join', $booking))
            ->assertOk()
            ->assertExactJson(['ok' => true]);

        $this->assertSame(4, (int) PlayerSignup::query()->where('uid', $other->uid)->value('slot'));
        $this->assertSame(['Paula Partner', 'Max Muster', 'Erika Beispiel'], $booking->fresh(['meta'])->player_names);
        $this->assertSame(PlayerSearch::FILLED, $search->fresh()->status);
    }

    #[Test]
    public function last_place_second_join_fails(): void
    {
        $booking = $this->searchingBooking(2, 1);
        $first = $this->member('Max Muster');
        $second = $this->member('Erika Beispiel');

        $this->exchange()->join($first, $booking);
        $this->assertSame(PlayerSearch::FILLED, PlayerSearch::query()->where('bid', $booking->bid)->value('status'));

        $this->actingAs($second)
            ->postJson(route('player-exchange.join', $booking))
            ->assertStatus(422)
            ->assertExactJson(['error' => __('booking.messages.player_exchange.full')]);

        $this->assertSame(1, PlayerSignup::query()->count());
        $this->assertSame(['Max Muster'], $booking->fresh(['meta'])->player_names);

        // The same member twice is refused as well.
        $this->assertRefused(fn () => $this->exchange()->join($first, $booking), 'already_joined');
    }

    #[Test]
    public function join_is_refused_for_non_opted_in_owner_overlapping_booking_other_court_overlapping_signup_after_deadline(): void
    {
        $booking = $this->searchingBooking(4, 3);
        $owner = $booking->user;

        // Not opted in.
        $stranger = User::factory()->create();
        $this->assertRefused(fn () => $this->exchange()->join($stranger, $booking), 'not_opted_in');

        // Opted in but blocked.
        $blocked = $this->member('Bernd Gesperrt');
        $blocked->forceFill(['status' => 'blocked'])->save();
        $this->assertRefused(fn () => $this->exchange()->join($blocked->fresh(), $booking), 'inactive');

        // The booker himself.
        $this->exchange()->optIn($owner);
        $this->assertRefused(fn () => $this->exchange()->join($owner, $booking), 'own_booking');

        // Own active booking on another court, overlapping by 30 minutes.
        $busy = $this->member('Clara Doppelt');
        app(BookingService::class)->createSingle(
            $busy, Square::factory()->create(), 2, $this->start->copy()->addMinutes(30), $this->start->copy()->addMinutes(90),
        );
        $this->assertRefused(fn () => $this->exchange()->join($busy, $booking), 'busy');

        // A cancelled own booking does not count; one ending exactly at the start neither.
        $free = $this->member('Dora Frei');
        $cancelled = app(BookingService::class)->createSingle(
            $free, Square::factory()->create(), 2, $this->start->copy(), $this->start->copy()->addHour(),
        );
        $cancelled->update(['status' => 'cancelled']);
        app(BookingService::class)->createSingle(
            $free, Square::factory()->create(), 2, $this->start->copy()->subHour(), $this->start->copy(),
        );
        $this->assertFalse($this->exchange()->userBusy($free, $this->start->copy(), $this->start->copy()->addHour()));
        $this->exchange()->join($free, $booking);

        // Signed up elsewhere at an overlapping time.
        $elsewhere = $this->searchingBooking(2, 1, [], null, $this->start->copy()->addMinutes(45));
        $signedElsewhere = $this->member('Emil Woanders');
        $this->exchange()->join($signedElsewhere, $elsewhere);
        $this->assertRefused(fn () => $this->exchange()->join($signedElsewhere, $booking), 'busy');

        // Already signed up here.
        $this->assertRefused(fn () => $this->exchange()->join($free, $booking), 'already_joined');

        // After the deadline (one hour before the start).
        $late = $this->member('Fritz Spaet');
        $this->travelTo($this->start->copy()->subMinutes(60));
        $this->assertRefused(fn () => $this->exchange()->join($late, $booking), 'deadline_passed');

        $this->assertSame(2, PlayerSignup::query()->count());
    }

    #[Test]
    public function leave_reopens_a_filled_search(): void
    {
        $booking = $this->searchingBooking(3, 1, [2 => 'Paula Partner']);
        $first = $this->member('Max Muster');
        $this->exchange()->join($first, $booking);
        $search = PlayerSearch::query()->where('bid', $booking->bid)->sole();
        $this->assertSame(PlayerSearch::FILLED, $search->fresh()->status);
        $this->assertSame(['Paula Partner', 'Max Muster'], $booking->fresh(['meta'])->player_names);

        $this->actingAs($first)
            ->delete(route('player-exchange.leave', $booking))
            ->assertRedirect(route('calendar.index', ['date' => '2026-11-08']))
            ->assertSessionHas('success', __('booking.messages.player_exchange.left'));

        $this->assertSame(0, PlayerSignup::query()->count());
        $this->assertSame(PlayerSearch::OPEN, $search->fresh()->status);
        $this->assertSame(['Paula Partner'], $booking->fresh(['meta'])->player_names, 'only the slot of the leaver is cleared');

        // Not signed up any more: leaving again is refused.
        $this->assertRefused(fn () => $this->exchange()->leave($first, $booking), 'not_joined');

        // The free slot is taken again by the next member.
        $next = $this->member('Erika Beispiel');
        $this->exchange()->join($next, $booking);
        $this->assertSame(3, (int) PlayerSignup::query()->sole()->slot);
    }

    #[Test]
    public function leave_after_deadline_is_refused(): void
    {
        $booking = $this->searchingBooking(2, 1);
        $member = $this->member('Max Muster');
        $this->exchange()->join($member, $booking);

        $this->travelTo($this->start->copy()->subMinutes(30));

        $this->actingAs($member)
            ->from('/calendar')
            ->deleteJson(route('player-exchange.leave', $booking))
            ->assertStatus(422)
            ->assertExactJson(['error' => __('booking.messages.player_exchange.leave_too_late')]);

        $this->actingAs($member)
            ->delete(route('player-exchange.leave', $booking))
            ->assertRedirect(route('calendar.index', ['date' => '2026-11-08']))
            ->assertSessionHas('error', __('booking.messages.player_exchange.leave_too_late'));

        $this->assertSame(1, PlayerSignup::query()->count());
        $this->assertSame(['Max Muster'], $booking->fresh(['meta'])->player_names);
        $this->assertSame(PlayerSearch::FILLED, PlayerSearch::query()->where('bid', $booking->bid)->value('status'));
    }

    #[Test]
    public function owner_can_end_search_and_booking_stays(): void
    {
        $booking = $this->searchingBooking(4, 3);
        $member = $this->member('Max Muster');
        $this->exchange()->join($member, $booking);

        // Only the booker may end it.
        $this->actingAs($member)
            ->postJson(route('player-exchange.end', $booking))
            ->assertStatus(422)
            ->assertExactJson(['error' => __('booking.messages.player_exchange.not_owner')]);

        $this->actingAs($booking->user)
            ->post(route('player-exchange.end', $booking))
            ->assertRedirect(route('calendar.index', ['date' => '2026-11-08']))
            ->assertSessionHas('success', __('booking.messages.player_exchange.ended'));

        $this->assertSame(PlayerSearch::ENDED, PlayerSearch::query()->where('bid', $booking->bid)->value('status'));
        $this->assertSame('single', $booking->fresh()->status);
        $this->assertSame(['Max Muster'], $booking->fresh(['meta'])->player_names, 'joined players stay');
        $this->assertSame(1, PlayerSignup::query()->count());

        // Ended: nobody can join, ending twice is refused.
        $this->assertRefused(fn () => $this->exchange()->join($this->member('Erika Beispiel'), $booking), 'not_open');
        $this->assertRefused(fn () => $this->exchange()->endSearch($booking->user, $booking), 'not_open');

        // A signup in an ended search still makes the member busy at that time.
        $this->assertTrue($this->exchange()->userBusy($member, $this->start->copy(), $this->start->copy()->addHour()));
        $this->assertFalse($this->exchange()->userBusy($member, $this->start->copy(), $this->start->copy()->addHour(), (int) $booking->bid));
    }

    #[Test]
    public function guests_are_sent_to_the_login(): void
    {
        $booking = $this->searchingBooking(2, 1);

        $this->post(route('player-exchange.join', $booking))->assertRedirect(route('login'));
        $this->assertSame(0, PlayerSignup::query()->count());
    }
}
