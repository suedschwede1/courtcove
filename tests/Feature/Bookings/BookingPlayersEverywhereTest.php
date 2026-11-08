<?php

namespace Tests\Feature\Bookings;

use App\Mail\BookingConfirmed;
use App\Models\Booking;
use App\Models\BookingMeta;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The players of a booking show up in every list that shows the booking, members only. */
final class BookingPlayersEverywhereTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function booking(User $owner, array $players): Booking
    {
        $square = Square::factory()->create(['name' => '1', 'priority' => 1]);
        $booking = Booking::factory()->create(['uid' => $owner->uid, 'sid' => $square->sid, 'status' => 'single', 'quantity' => 2]);
        $entries = [];
        foreach ($players as $i => $name) {
            $entries[] = ['name' => 'sb-player-name-'.($i + 2), 'value' => $name];
        }
        BookingMeta::create(['bid' => $booking->bid, 'key' => 'player-names', 'value' => serialize($entries)]);
        $day = Carbon::now()->addDay()->toDateString();
        Reservation::factory()->create(['bid' => $booking->bid, 'date' => $day, 'time_start' => '17:00:00', 'time_end' => '18:00:00']);

        return $booking->fresh(['meta', 'user']);
    }

    #[Test]
    public function the_model_helpers_drop_the_booker_and_shorten_names(): void
    {
        $owner = User::factory()->create(['status' => 'enabled', 'alias' => 'Example Owner']);
        $booking = $this->booking($owner, ['example owner ', 'Helga Muster', 'Sandra B', 'Inge P', 'Dora D']);

        $this->assertSame(['Helga Muster', 'Sandra B', 'Inge P', 'Dora D'], $booking->other_players);
        $this->assertSame('Helga Muster, Sandra B, Inge P, Dora D', Booking::compactPlayerNames($booking->other_players));
        $this->assertSame('A, B, C, D +1', Booking::compactPlayerNames(['A', 'B', 'C', 'D', 'E']));
        $this->assertSame('', Booking::compactPlayerNames([]));
    }

    #[Test]
    public function one_field_with_several_names_counts_as_several_players(): void
    {
        $owner = User::factory()->create(['status' => 'enabled', 'alias' => 'Example Owner']);
        $booking = $this->booking($owner, ['Helga, Sandra; Inge und Dora', ' , ', 'helga']);

        $this->assertSame(['Helga', 'Sandra', 'Inge', 'Dora'], $booking->other_players);
        // The stored slots stay as they are for the edit forms.
        $this->assertCount(3, $booking->player_names);
    }

    #[Test]
    public function my_bookings_list_shows_the_players(): void
    {
        $owner = User::factory()->create(['status' => 'enabled', 'alias' => 'Example Owner']);
        $this->booking($owner, ['Helga Muster', 'Sandra Beispiel']);

        $this->actingAs($owner)->get('/my-bookings?searched=1')
            ->assertOk()->assertSee('Helga Muster, Sandra Beispiel');
    }

    #[Test]
    public function the_players_component_renders_nothing_for_guests(): void
    {
        $owner = User::factory()->create(['status' => 'enabled', 'alias' => 'Example Owner']);
        $booking = $this->booking($owner, ['Helga Muster']);

        $this->assertSame('', trim((string) view('components.booking.players', ['booking' => $booking])->render()));

        $this->actingAs(User::factory()->create(['status' => 'enabled']));
        $this->assertSame('Helga Muster', trim((string) view('components.booking.players', ['booking' => $booking])->render()));
    }

    #[Test]
    public function the_confirmation_mail_lists_the_teammates(): void
    {
        $owner = User::factory()->create(['status' => 'enabled', 'alias' => 'Example Owner']);
        $booking = $this->booking($owner, ['Helga Muster']);

        (new BookingConfirmed($booking))->assertSeeInHtml('Helga Muster');
    }
}
