<?php

declare(strict_types=1);

namespace Tests\Feature\PlayerExchange;

use App\Models\Booking;
use App\Models\PlayerSearch;
use App\Models\Square;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingFormSearchTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo('2026-07-01 10:00');
    }

    /** @return array<string, mixed> */
    private function payload(Square $square, array $override = []): array
    {
        return array_merge([
            'sid' => $square->sid,
            'date' => '2026-07-10',
            'time_start' => 36000,
            'time_end' => 39600,
            'quantity' => 2,
        ], $override);
    }

    private function ajaxPost(User $user, array $data)
    {
        return $this->actingAs($user)->post(route('bookings.store'), $data, ['X-Requested-With' => 'XMLHttpRequest']);
    }

    #[Test]
    public function store_with_search_players_creates_booking_and_search_without_mitspieler(): void
    {
        $user = User::factory()->create(['status' => 'enabled']);
        $square = Square::factory()->create();

        $this->ajaxPost($user, $this->payload($square, ['search_players' => 1]))
            ->assertOk()
            ->assertJsonPath('search', true);

        $booking = Booking::firstOrFail();
        $search = PlayerSearch::where('bid', $booking->bid)->firstOrFail();
        $this->assertSame(1, $search->needed);
        $this->assertSame(0, $booking->meta()->where('key', 'player-names')->count());
    }

    #[Test]
    public function store_without_mitspieler_and_without_search_is_still_refused(): void
    {
        $user = User::factory()->create(['status' => 'enabled']);
        $square = Square::factory()->create();

        $this->actingAs($user)->postJson(route('bookings.store'), $this->payload($square))
            ->assertStatus(422)->assertJsonValidationErrors('mitspieler');
        $this->assertSame(0, Booking::count());
    }

    #[Test]
    public function store_with_search_players_too_high_returns_422(): void
    {
        $user = User::factory()->create(['status' => 'enabled']);
        $square = Square::factory()->create();

        $this->ajaxPost($user, $this->payload($square, ['search_players' => 2]))
            ->assertStatus(422)
            ->assertJsonPath('error', __('booking.messages.player_exchange.too_many'));

        $this->ajaxPost($user, $this->payload($square, ['quantity' => 4, 'mitspieler' => 'Partner', 'search_players' => 3]))
            ->assertStatus(422)
            ->assertJsonPath('error', __('booking.messages.player_exchange.too_many'));

        $this->assertSame(0, Booking::count());
        $this->assertSame(0, PlayerSearch::count());
    }

    #[Test]
    public function store_with_search_for_booking_in_under_an_hour_returns_422(): void
    {
        $user = User::factory()->create(['status' => 'enabled']);
        $square = Square::factory()->create();

        $this->ajaxPost($user, $this->payload($square, ['date' => '2026-07-01', 'time_start' => 10 * 3600 + 1800, 'time_end' => 11 * 3600 + 1800, 'search_players' => 1]))
            ->assertStatus(422)
            ->assertJsonPath('error', __('booking.messages.player_exchange.too_late'));

        $this->assertSame(0, Booking::count());
    }

    #[Test]
    public function booking_modal_contains_the_search_option_markup(): void
    {
        $user = User::factory()->create(['status' => 'enabled']);
        Square::factory()->create();

        $html = $this->actingAs($user)->get('/calendar')->assertOk()->getContent();
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $x = new DOMXPath($dom);

        $this->assertSame(1, $x->query('//div[@id="booking-dialog"]//input[@type="checkbox"][@name="search"]')->length);
        $this->assertSame(1, $x->query('//div[@id="booking-dialog"]//label[contains(@class,"ds-option")][normalize-space()="'.__('booking.modal.search_players').'"]')->length);

        $select = $x->query('//div[@id="booking-dialog"]//select[@name="search_players"]');
        $this->assertSame(1, $select->length);
        $id = $select->item(0)->getAttribute('id');
        $this->assertSame(1, $x->query('//div[@id="booking-dialog"]//label[@for="'.$id.'"][normalize-space()="'.__('booking.modal.search_count').'"]')->length);
    }
}
