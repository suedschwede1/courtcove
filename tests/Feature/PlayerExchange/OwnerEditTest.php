<?php

declare(strict_types=1);

namespace Tests\Feature\PlayerExchange;

use App\Exceptions\BookingValidationException;
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
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The booker edits partner names and Einzel/Doppel of a booking that has a player search
 * (BookingController::update -> BookingService::updateSinglePlayers). Members who joined via
 * the exchange keep their slot and name; the booker only changes his own typed partners, and
 * a smaller quantity is refused while it would leave a joined member without a place.
 */
class OwnerEditTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Carbon $start;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-06 08:00:00'));
        $this->start = Carbon::parse('2026-11-08 10:00:00');
        $this->owner = User::factory()->create(['alias' => 'Olga Owner']);
    }

    private function exchange(): PlayerExchangeService
    {
        return app(PlayerExchangeService::class);
    }

    /** @param array<int, string> $names slot => name */
    private function searchingBooking(int $quantity, int $needed, array $names = []): Booking
    {
        $meta = app(BookingService::class)->buildPlayerMeta($names);

        return app(BookingService::class)->createSingle(
            $this->owner, Square::factory()->create(['capacity' => 4]), $quantity, $this->start, $this->start->copy()->addHour(), $meta, $needed,
        );
    }

    private function member(string $alias): User
    {
        $user = User::factory()->create(['alias' => $alias]);
        $this->exchange()->optIn($user);

        return $user;
    }

    private function search(Booking $booking): PlayerSearch
    {
        return PlayerSearch::query()->where('bid', $booking->bid)->sole();
    }

    /** @return array<int, string> slot => name, read straight from the stored meta */
    private function namesBySlot(Booking $booking): array
    {
        $value = $booking->fresh(['meta'])->meta->firstWhere('key', 'player-names')?->value;
        $names = [];
        foreach (is_string($value) ? (array) unserialize($value, ['allowed_classes' => false]) : [] as $player) {
            $names[(int) substr((string) $player['name'], -1)] = (string) $player['value'];
        }
        ksort($names);

        return $names;
    }

    /** @return array<int, int> uid => slot */
    private function signupSlots(): array
    {
        return PlayerSignup::query()->orderBy('slot')->get()
            ->mapWithKeys(fn (PlayerSignup $s) => [(int) $s->uid => (int) $s->slot])->all();
    }

    /** @param array<string, mixed> $data */
    private function ownerEdits(Booking $booking, array $data): TestResponse
    {
        return $this->actingAs($this->owner)->put(route('bookings.update', $booking), $data);
    }

    #[Test]
    public function owner_typed_partner_never_overwrites_a_joined_member(): void
    {
        $booking = $this->searchingBooking(4, 3);
        $max = $this->member('Max Muster');
        $this->exchange()->join($max, $booking);

        $this->ownerEdits($booking, ['quantity' => 4, 'mitspieler' => 'Paula Partner'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame([2 => 'Max Muster', 3 => 'Paula Partner'], $this->namesBySlot($booking));
        $this->assertSame([(int) $max->uid => 2], $this->signupSlots(), 'the joined member keeps his slot');
        $search = $this->search($booking);
        $this->assertSame(PlayerSearch::OPEN, $search->status);
        $this->assertSame(1, $search->remaining(), 'the typed partner takes one of the searched places');

        // The next member gets the last free slot; nobody is overwritten.
        $erika = $this->member('Erika Beispiel');
        $this->exchange()->join($erika, $booking);
        $this->assertSame([2 => 'Max Muster', 3 => 'Paula Partner', 4 => 'Erika Beispiel'], $this->namesBySlot($booking));
        $this->assertSame(PlayerSearch::FILLED, $search->fresh()->status);
    }

    #[Test]
    public function the_edit_form_shows_only_the_owner_typed_partner_and_lists_joined_members(): void
    {
        $booking = $this->searchingBooking(4, 3);
        $this->exchange()->join($this->member('Max Muster'), $booking);

        $this->actingAs($this->owner)->get(route('bookings.edit', $booking))
            ->assertOk()
            ->assertDontSee('value="Max Muster"', false)
            ->assertSee(__('booking.messages.player_exchange.edit_signed_up', ['names' => 'Max Muster']));
    }

    #[Test]
    public function owner_may_clear_his_own_partner_when_the_booking_has_a_search(): void
    {
        $booking = $this->searchingBooking(4, 2, [2 => 'Paula Partner']);
        $max = $this->member('Max Muster');
        $this->exchange()->join($max, $booking);

        $this->ownerEdits($booking, ['quantity' => 4, 'mitspieler' => ''])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame([3 => 'Max Muster'], $this->namesBySlot($booking));
        $this->assertSame([(int) $max->uid => 3], $this->signupSlots());
        $this->assertSame(1, $this->search($booking)->remaining(), 'the searched number is not raised by an edit');
    }

    #[Test]
    public function after_ending_a_search_without_signups_the_partner_is_required_again(): void
    {
        $booking = $this->searchingBooking(2, 1);
        $this->exchange()->endSearch($this->owner, $booking);

        // Nobody plays instead of the partner: no partner and no search is not a booking.
        $this->ownerEdits($booking, ['quantity' => 2, 'mitspieler' => ''])->assertSessionHasErrors('mitspieler');

        $this->ownerEdits($booking, ['quantity' => 2, 'mitspieler' => 'Paula Partner'])->assertSessionHasNoErrors();
        $this->assertSame([2 => 'Paula Partner'], $this->namesBySlot($booking));
    }

    #[Test]
    public function an_ended_search_with_a_joined_member_still_lets_the_owner_clear_his_partner(): void
    {
        $booking = $this->searchingBooking(4, 2, [2 => 'Paula Partner']);
        $this->exchange()->join($this->member('Max Muster'), $booking);
        $this->exchange()->endSearch($this->owner, $booking);

        $this->ownerEdits($booking, ['quantity' => 4, 'mitspieler' => ''])->assertSessionHasNoErrors();
        $this->assertSame([3 => 'Max Muster'], $this->namesBySlot($booking));
    }

    #[Test]
    public function reducing_to_single_below_the_occupied_places_is_refused_and_nothing_changes(): void
    {
        $booking = $this->searchingBooking(4, 3);
        $this->exchange()->join($this->member('Max Muster'), $booking);
        $this->exchange()->join($this->member('Erika Beispiel'), $booking);

        $this->ownerEdits($booking, ['quantity' => 2, 'mitspieler' => ''])
            ->assertSessionHasErrors(['booking' => __('booking.messages.player_exchange.edit_too_many')]);

        $this->actingAs($this->owner)
            ->put(route('bookings.update', $booking), ['quantity' => 2, 'mitspieler' => ''], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertExactJson(['error' => __('booking.messages.player_exchange.edit_too_many')]);

        $this->assertSame(4, (int) $booking->fresh()->quantity);
        $this->assertSame([2 => 'Max Muster', 3 => 'Erika Beispiel'], $this->namesBySlot($booking));
        $this->assertSame(2, PlayerSignup::query()->count());
        $this->assertSame(PlayerSearch::OPEN, $this->search($booking)->status);
    }

    #[Test]
    public function a_typed_partner_plus_a_joined_member_do_not_fit_a_single(): void
    {
        $booking = $this->searchingBooking(4, 2, [2 => 'Paula Partner']);
        $this->exchange()->join($this->member('Max Muster'), $booking);

        $this->ownerEdits($booking, ['quantity' => 2, 'mitspieler' => 'Paula Partner'])
            ->assertSessionHasErrors(['booking' => __('booking.messages.player_exchange.edit_too_many')]);

        $this->assertSame(4, (int) $booking->fresh()->quantity);
        $this->assertSame([2 => 'Paula Partner', 3 => 'Max Muster'], $this->namesBySlot($booking));
    }

    #[Test]
    public function reducing_to_single_moves_the_joined_member_into_the_remaining_slot_and_fills_the_search(): void
    {
        $booking = $this->searchingBooking(4, 2, [2 => 'Paula Partner']);
        $max = $this->member('Max Muster');
        $this->exchange()->join($max, $booking);

        $this->ownerEdits($booking, ['quantity' => 2, 'mitspieler' => ''])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(2, (int) $booking->fresh()->quantity);
        $this->assertSame([2 => 'Max Muster'], $this->namesBySlot($booking));
        $this->assertSame([(int) $max->uid => 2], $this->signupSlots(), 'name and signup slot stay together');
        $search = $this->search($booking);
        $this->assertSame(0, $search->remaining());
        $this->assertSame(PlayerSearch::FILLED, $search->status);

        // Leaving still removes exactly his own name and opens the place again.
        $this->exchange()->leave($max, $booking);
        $this->assertSame([], $this->namesBySlot($booking));
        $this->assertSame(PlayerSearch::OPEN, $search->fresh()->status);
        $this->assertSame(1, $search->fresh()->remaining());
    }

    #[Test]
    public function raising_to_double_keeps_a_filled_search_filled_and_an_open_search_at_its_number(): void
    {
        $filled = $this->searchingBooking(2, 1);
        $this->exchange()->join($this->member('Max Muster'), $filled);
        $this->ownerEdits($filled, ['quantity' => 4, 'mitspieler' => ''])->assertSessionHasNoErrors();

        $this->assertSame(4, (int) $filled->fresh()->quantity);
        $this->assertSame([2 => 'Max Muster'], $this->namesBySlot($filled));
        $this->assertSame(PlayerSearch::FILLED, $this->search($filled)->status);
        $this->assertSame(0, $this->search($filled)->remaining());

        $this->start = Carbon::parse('2026-11-08 12:00:00');
        $open = $this->searchingBooking(2, 1);
        $this->ownerEdits($open, ['quantity' => 4, 'mitspieler' => ''])->assertSessionHasNoErrors();

        $this->assertSame(PlayerSearch::OPEN, $this->search($open)->status);
        $this->assertSame(1, $this->search($open)->remaining());
    }

    #[Test]
    public function owner_typing_the_last_partner_without_signups_ends_the_search_so_it_does_not_lapse(): void
    {
        $booking = $this->searchingBooking(2, 1);

        $this->ownerEdits($booking, ['quantity' => 2, 'mitspieler' => 'Paula Partner'])->assertSessionHasNoErrors();

        $this->assertSame([2 => 'Paula Partner'], $this->namesBySlot($booking));
        $this->assertSame(PlayerSearch::ENDED, $this->search($booking)->status);

        $this->travelTo(Carbon::parse('2026-11-08 09:30:00'));
        $this->assertSame(0, $this->exchange()->lapseExpired());
        $this->assertSame('single', $booking->fresh()->status, 'the booking with a partner stays');
    }

    #[Test]
    public function an_ended_search_keeps_its_joined_members_on_edit(): void
    {
        $booking = $this->searchingBooking(4, 3);
        $max = $this->member('Max Muster');
        $this->exchange()->join($max, $booking);
        $this->exchange()->endSearch($this->owner, $booking);

        $this->ownerEdits($booking, ['quantity' => 4, 'mitspieler' => 'Paula Partner'])->assertSessionHasNoErrors();

        $this->assertSame([2 => 'Max Muster', 3 => 'Paula Partner'], $this->namesBySlot($booking));
        $this->assertSame(PlayerSearch::ENDED, $this->search($booking)->status);
        $this->assertSame([(int) $max->uid => 2], $this->signupSlots());
    }

    #[Test]
    public function when_the_first_of_two_joined_members_leaves_the_second_stays_intact(): void
    {
        $booking = $this->searchingBooking(4, 3);
        $max = $this->member('Max Muster');
        $erika = $this->member('Erika Beispiel');
        $this->exchange()->join($max, $booking);
        $this->exchange()->join($erika, $booking);

        $this->exchange()->leave($max, $booking);

        $this->assertSame([3 => 'Erika Beispiel'], $this->namesBySlot($booking));
        $this->assertSame([(int) $erika->uid => 3], $this->signupSlots());
        $this->assertSame((int) $this->owner->uid, (int) $booking->fresh()->uid, 'the booker stays');
    }

    #[Test]
    public function leaving_with_duplicate_names_removes_only_the_own_slot(): void
    {
        $booking = $this->searchingBooking(4, 2, [2 => 'Max Muster']);
        $first = $this->member('Max Muster');
        $second = $this->member('Max Muster');
        $this->exchange()->join($first, $booking);
        $this->exchange()->join($second, $booking);
        $this->assertSame([2 => 'Max Muster', 3 => 'Max Muster', 4 => 'Max Muster'], $this->namesBySlot($booking));

        $this->exchange()->leave($first, $booking);

        $this->assertSame([2 => 'Max Muster', 4 => 'Max Muster'], $this->namesBySlot($booking), 'typed partner and the other member stay');
        $this->assertSame([(int) $second->uid => 4], $this->signupSlots());

        $this->exchange()->leave($second, $booking);
        $this->assertSame([2 => 'Max Muster'], $this->namesBySlot($booking), 'the typed partner with the same name stays');
    }

    #[Test]
    public function an_edit_that_arrives_after_the_lapse_cancelled_the_booking_changes_nothing(): void
    {
        $booking = $this->searchingBooking(4, 2, [2 => 'Paula Partner']);
        // The request loaded the booking (single, live search), then the lapse cancelled it
        // before the edit took the booking row lock.
        $stale = Booking::query()->with(['square', 'reservations', 'meta'])->findOrFail($booking->bid);
        $this->travelTo(Carbon::parse('2026-11-08 09:00:00'));
        $this->assertSame(1, $this->exchange()->lapseExpired());
        $this->travelTo(Carbon::parse('2026-11-06 08:00:00'));

        try {
            app(BookingService::class)->updateSinglePlayers($stale, $this->owner, 2, [2 => null]);
            $this->fail('a cancelled booking must not be edited');
        } catch (BookingValidationException $e) {
            $this->assertSame(__('booking.messages.booking_not_editable'), $e->getMessage());
        }

        $fresh = $booking->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame(4, (int) $fresh->quantity, 'quantity untouched');
        $this->assertSame([2 => 'Paula Partner'], $this->namesBySlot($booking), 'names untouched');
    }

    #[Test]
    public function a_booking_without_search_is_edited_exactly_as_before(): void
    {
        $square = Square::factory()->create(['capacity' => 4, 'range_cancel' => 0]);
        $booking = Booking::factory()->create(['uid' => $this->owner->uid, 'sid' => $square->sid, 'status' => 'single', 'quantity' => 4]);
        Reservation::factory()->create(['bid' => $booking->bid, 'date' => '2026-11-09', 'time_start' => '10:00:00', 'time_end' => '11:00:00']);
        app(BookingService::class)->syncPlayerMeta($booking, [2 => 'Alt Eins', 3 => 'Alt Zwei', 4 => 'Alt Drei']);

        // The partner field stays required without a search.
        $this->ownerEdits($booking, ['quantity' => 2, 'mitspieler' => ''])->assertSessionHasErrors(['mitspieler']);

        $this->ownerEdits($booking, ['quantity' => 2, 'mitspieler' => 'Neu Zwei'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(2, (int) $booking->fresh()->quantity);
        $this->assertSame([2 => 'Neu Zwei'], $this->namesBySlot($booking), 'names are replaced wholesale as before');
        $this->assertSame(0, PlayerSearch::query()->count());
    }
}
