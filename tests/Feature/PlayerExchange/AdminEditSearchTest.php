<?php

declare(strict_types=1);

namespace Tests\Feature\PlayerExchange;

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
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The admin edit of a booking (Admin\BookingController::update -> BookingService::updateSeries)
 * keeps a player search intact: members who joined keep slot and name, the capacity rule of the
 * owner edit applies, and date, time, repeat and booker stay fixed while the search runs or
 * members are signed up (the joined members agreed to that time). The court may change.
 */
class AdminEditSearchTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Carbon $start;

    private User $owner;

    private User $admin;

    private Square $square;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-06 08:00:00'));
        $this->start = Carbon::parse('2026-11-08 10:00:00');
        $this->owner = User::factory()->create(['alias' => 'Olga Owner']);
        $this->admin = User::factory()->create(['status' => 'admin', 'alias' => 'Adam Admin']);
        $this->square = Square::factory()->create(['capacity' => 4]);
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
            $this->owner, $this->square, $quantity, $this->start, $this->start->copy()->addHour(), $meta, $needed,
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

    /** @return array{0: string, 1: string, 2: string} date, start, end of the only reservation */
    private function slotOf(Booking $booking): array
    {
        $reservation = $booking->fresh(['reservations'])->reservations->sole();

        return [Carbon::parse($reservation->date)->format('Y-m-d'), (string) $reservation->time_start, (string) $reservation->time_end];
    }

    /**
     * The full-page admin edit form, sent as the browser does: the stored player names in
     * player_name_2..4 (by position), unchanged unless overridden.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function adminEdits(Booking $booking, array $overrides = []): TestResponse
    {
        $names = $booking->fresh(['meta'])->player_names;

        return $this->actingAs($this->admin)->put("/admin/bookings/{$booking->bid}", array_merge([
            'booked_for' => 'Olga Owner',
            'sid' => $this->square->sid,
            'date' => '2026-11-08',
            'date_end' => '2026-11-08',
            'repeat_type' => 'once',
            'time_start' => '10:00',
            'time_end' => '11:00',
            'quantity' => (int) $booking->fresh()->quantity,
            'player_name_2' => $names[0] ?? '',
            'player_name_3' => $names[1] ?? '',
            'player_name_4' => $names[2] ?? '',
        ], $overrides));
    }

    #[Test]
    public function admin_adds_a_partner_and_moves_the_court_and_the_joined_member_stays(): void
    {
        $booking = $this->searchingBooking(4, 3);
        $max = $this->member('Max Muster');
        $this->exchange()->join($max, $booking);
        $other = Square::factory()->create(['capacity' => 4]);

        $this->adminEdits($booking, ['sid' => $other->sid, 'player_name_3' => 'Paula Partner'])
            ->assertRedirect(route('admin.bookings.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame((int) $other->sid, (int) $booking->fresh()->sid);
        $this->assertSame([2 => 'Max Muster', 3 => 'Paula Partner'], $this->namesBySlot($booking));
        $this->assertSame([(int) $max->uid => 2], $this->signupSlots());
        $this->assertSame(1, $this->search($booking)->remaining(), 'the typed partner takes a searched place');
        $this->assertSame(['2026-11-08', '10:00:00', '11:00:00'], $this->slotOf($booking));
    }

    #[Test]
    public function an_unchanged_form_keeps_a_joined_member_in_his_slot_even_when_the_form_lists_him_first(): void
    {
        $booking = $this->searchingBooking(4, 3);
        $erika = $this->member('Erika Beispiel');
        $max = $this->member('Max Muster');
        $this->exchange()->join($erika, $booking);
        $this->exchange()->join($max, $booking);
        $this->exchange()->leave($erika, $booking);
        $this->assertSame([3 => 'Max Muster'], $this->namesBySlot($booking));

        // The form shows the names by position: Max lands in player_name_2.
        $this->adminEdits($booking)->assertSessionHasNoErrors();

        $this->assertSame([3 => 'Max Muster'], $this->namesBySlot($booking));
        $this->assertSame([(int) $max->uid => 3], $this->signupSlots());
        $this->assertSame(2, $this->search($booking)->remaining());
    }

    #[Test]
    public function admin_cannot_remove_or_rename_a_joined_member(): void
    {
        $booking = $this->searchingBooking(4, 3);
        $this->exchange()->join($this->member('Max Muster'), $booking);

        $this->adminEdits($booking, ['player_name_2' => 'Someone Else'])
            ->assertSessionHasErrors(['booking' => __('booking.messages.player_exchange.admin_edit_joined', ['names' => 'Max Muster'])]);

        $this->assertSame([2 => 'Max Muster'], $this->namesBySlot($booking));
        $this->assertSame(1, PlayerSignup::query()->count());
    }

    #[Test]
    public function admin_cannot_move_a_booking_with_joined_members_to_another_time_date_or_series(): void
    {
        $booking = $this->searchingBooking(4, 3);
        $this->exchange()->join($this->member('Max Muster'), $booking);
        $locked = __('booking.messages.player_exchange.admin_edit_locked');

        $this->adminEdits($booking, ['time_start' => '12:00', 'time_end' => '13:00'])
            ->assertSessionHasErrors(['booking' => $locked]);
        $this->adminEdits($booking, ['time_end' => '11:30'])
            ->assertSessionHasErrors(['booking' => $locked]);
        $this->adminEdits($booking, ['date' => '2026-11-09', 'date_end' => '2026-11-09'])
            ->assertSessionHasErrors(['booking' => $locked]);
        $this->adminEdits($booking, ['repeat_type' => 'weekly', 'date_end' => '2026-11-22'])
            ->assertSessionHasErrors(['booking' => $locked]);

        $this->assertSame(['2026-11-08', '10:00:00', '11:00:00'], $this->slotOf($booking));
        $this->assertSame('single', $booking->fresh()->status);
        $this->assertSame(1, PlayerSignup::query()->count());
    }

    #[Test]
    public function admin_cannot_move_a_booking_whose_search_is_still_open_without_signups(): void
    {
        $booking = $this->searchingBooking(2, 1, []);

        // The admin form needs a partner name (existing validation), so one is typed here.
        $this->adminEdits($booking, ['time_start' => '12:00', 'time_end' => '13:00', 'player_name_2' => 'Paula Partner'])
            ->assertSessionHasErrors(['booking' => __('booking.messages.player_exchange.admin_edit_locked')]);

        $this->assertSame(['2026-11-08', '10:00:00', '11:00:00'], $this->slotOf($booking));
    }

    #[Test]
    public function admin_cannot_hand_a_search_booking_to_another_booker(): void
    {
        $booking = $this->searchingBooking(4, 3);
        $this->exchange()->join($this->member('Max Muster'), $booking);
        User::factory()->create(['alias' => 'Neue Buchende']);

        $this->adminEdits($booking, ['booked_for' => 'Neue Buchende'])
            ->assertSessionHasErrors(['booking' => __('booking.messages.player_exchange.admin_edit_locked')]);

        $this->assertSame((int) $this->owner->uid, (int) $booking->fresh()->uid);
    }

    #[Test]
    public function admin_cannot_cut_the_places_below_the_joined_members(): void
    {
        $booking = $this->searchingBooking(4, 2, [2 => 'Paula Partner']);
        $this->exchange()->join($this->member('Max Muster'), $booking);

        $this->adminEdits($booking, ['quantity' => 2])
            ->assertSessionHasErrors(['booking' => __('booking.messages.player_exchange.edit_too_many')]);

        $this->assertSame(4, (int) $booking->fresh()->quantity);
        $this->assertSame([2 => 'Paula Partner', 3 => 'Max Muster'], $this->namesBySlot($booking));
    }

    #[Test]
    public function an_ended_search_without_signups_and_a_booking_without_search_move_as_before(): void
    {
        $ended = $this->searchingBooking(2, 1);
        $this->exchange()->endSearch($this->owner, $ended);

        $this->adminEdits($ended, ['time_start' => '12:00', 'time_end' => '13:00', 'player_name_2' => 'Paula Partner'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['2026-11-08', '12:00:00', '13:00:00'], $this->slotOf($ended));
        $this->assertSame([2 => 'Paula Partner'], $this->namesBySlot($ended));

        $plain = app(BookingService::class)->createSingle(
            $this->owner, Square::factory()->create(), 2, Carbon::parse('2026-11-08 15:00'), Carbon::parse('2026-11-08 16:00'),
            app(BookingService::class)->buildPlayerMeta([2 => 'Paula Partner']),
        );
        $this->adminEdits($plain, [
            'sid' => $plain->sid, 'date' => '2026-11-09', 'date_end' => '2026-11-09', 'time_start' => '15:00', 'time_end' => '16:00',
            'player_name_2' => 'Peter Partner',
        ])->assertSessionHasNoErrors();
        $this->assertSame(['2026-11-09', '15:00:00', '16:00:00'], $this->slotOf($plain));
        $this->assertSame([2 => 'Peter Partner'], $this->namesBySlot($plain));
    }
}
