<?php

declare(strict_types=1);

namespace Tests\Feature\PlayerExchange;

use App\Exceptions\BookingValidationException;
use App\Models\Booking;
use App\Models\PlayerSearch;
use App\Models\Square;
use App\Models\User;
use App\Services\BookingService;
use App\Services\PlayerExchangeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OpenSearchTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function service(): BookingService
    {
        return app(BookingService::class);
    }

    #[Test]
    public function single_with_one_searched_player_creates_open_search_with_deadline_one_hour_before_start(): void
    {
        $square = Square::factory()->create();
        $user = User::factory()->create();
        $start = Carbon::now()->addDays(2)->setTime(10, 0);

        $booking = $this->service()->createSingle($user, $square, 2, $start, $start->copy()->addHour(), [], 1);

        $search = PlayerSearch::where('bid', $booking->bid)->firstOrFail();
        $this->assertSame(PlayerSearch::OPEN, $search->status);
        $this->assertSame(1, $search->needed);
        $this->assertTrue($search->ends_at->equalTo($start->copy()->subMinutes(60)));
    }

    #[Test]
    public function doubles_with_one_named_partner_allows_at_most_two_searched(): void
    {
        $square = Square::factory()->create();
        $user = User::factory()->create();
        $meta = [[
            'key' => 'player-names',
            'value' => serialize([['name' => 'sb-player-name-2', 'value' => 'Partner']]),
        ]];
        $start = Carbon::now()->addDays(2)->setTime(10, 0);

        $this->assertSame(2, app(PlayerExchangeService::class)->maxSearchable(4, $meta));

        try {
            $this->service()->createSingle($user, $square, 4, $start, $start->copy()->addHour(), $meta, 3);
            $this->fail('Expected BookingValidationException');
        } catch (BookingValidationException) {
            $this->assertSame(0, Booking::count());
            $this->assertSame(0, PlayerSearch::count());
        }

        $booking = $this->service()->createSingle($user, $square, 4, $start, $start->copy()->addHour(), $meta, 2);
        $this->assertSame(2, PlayerSearch::where('bid', $booking->bid)->value('needed'));
    }

    #[Test]
    public function search_for_a_booking_starting_in_under_an_hour_is_rejected_and_creates_no_booking(): void
    {
        $square = Square::factory()->create();
        $user = User::factory()->create();
        $start = Carbon::now()->addMinutes(30);

        $this->expectException(BookingValidationException::class);
        try {
            $this->service()->createSingle($user, $square, 2, $start, $start->copy()->addHour(), [], 1);
        } finally {
            $this->assertSame(0, Booking::count());
            $this->assertSame(0, PlayerSearch::count());
        }
    }

    #[Test]
    public function zero_search_players_creates_no_search_row(): void
    {
        $square = Square::factory()->create();
        $user = User::factory()->create();
        $start = Carbon::now()->addDays(2)->setTime(10, 0);

        $this->service()->createSingle($user, $square, 2, $start, $start->copy()->addHour());

        $this->assertSame(1, Booking::count());
        $this->assertSame(0, PlayerSearch::count());
    }
}
