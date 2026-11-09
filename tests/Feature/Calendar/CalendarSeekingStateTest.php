<?php

namespace Tests\Feature\Calendar;

use App\Models\Booking;
use App\Models\PlayerSearch;
use App\Models\PlayerSignup;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\SquareMeta;
use App\Models\User;
use App\Services\PlayerExchangeService;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\QueriesHtml;
use Tests\TestCase;

final class CalendarSeekingStateTest extends TestCase
{
    use LazilyRefreshDatabase;
    use QueriesHtml;

    private Square $court;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-04 12:30'));
        // Keep the lazy lapse out of the way; the seeking state checks the deadline itself.
        Cache::put('player-exchange.lapse', 1, 600);
        Cache::put('notifications.lazy-drain', 1, 600);

        $this->court = Square::factory()->create(['name' => '1', 'priority' => 1]);
        SquareMeta::create(['sid' => $this->court->sid, 'key' => 'alias', 'value' => 'Court A']);
    }

    private function calendar(?User $viewer = null): string
    {
        $request = $viewer ? $this->actingAs($viewer) : $this;

        return (string) $request->get('/calendar?date=2026-11-04')->assertOk()->getContent();
    }

    private function cell(DOMXPath $xp, int $hour): DOMElement
    {
        $node = $xp->query('(//section[@data-day="1"]//div[contains(@class,"cal-row")])['.($hour - 7).']/*[contains(@class,"calendar-cell")][1]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $node, 'cell '.$hour);

        return $node;
    }

    /** Alpine attributes (@click) do not survive the DOM parser, so read the raw opening tag. */
    private function click(string $html, string $state): string
    {
        preg_match_all('/<a\s[^>]*data-state="'.$state.'"[^>]*>/s', $html, $tags);
        $this->assertCount(1, $tags[0], 'one '.$state.' link');
        $this->assertSame(1, preg_match('/@click\.prevent="([^"]*)"/s', $tags[0][0], $m));

        return html_entity_decode($m[1], ENT_QUOTES);
    }

    private function norm(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function booking(User $owner, int $hour, int $quantity = 2): Booking
    {
        $booking = Booking::factory()->create(['uid' => $owner->uid, 'sid' => $this->court->sid, 'status' => 'single', 'quantity' => $quantity]);
        Reservation::factory()->create([
            'bid' => $booking->bid,
            'date' => '2026-11-04',
            'time_start' => sprintf('%02d:00:00', $hour),
            'time_end' => sprintf('%02d:00:00', $hour + 1),
        ]);

        return $booking;
    }

    private function search(Booking $booking, int $hour, int $needed = 1, string $status = PlayerSearch::OPEN): PlayerSearch
    {
        return PlayerSearch::create([
            'bid' => $booking->bid,
            'needed' => $needed,
            'ends_at' => Carbon::parse('2026-11-04')->setTime($hour - 1, 0),
            'status' => $status,
        ]);
    }

    private function member(bool $optedIn = false): User
    {
        $user = User::factory()->create(['status' => 'enabled']);
        if ($optedIn) {
            app(PlayerExchangeService::class)->optIn($user);
        }

        return $user;
    }

    #[Test]
    public function open_search_cell_has_seeking_state_and_text(): void
    {
        $owner = User::factory()->create(['alias' => 'Anna Bucher']);
        $search = $this->search($this->booking($owner, 15, 4), 15, 3);
        PlayerSignup::create(['search_id' => $search->id, 'uid' => $this->member()->uid, 'slot' => 2]);

        $cell = $this->cell($this->xpath($this->calendar($this->member())), 15);

        $this->assertSame('seeking', $cell->getAttribute('data-state'));
        $this->assertStringContainsString('cc-seeking', $cell->getAttribute('class'));
        $this->assertStringContainsString('Sucht noch 2', $this->norm($cell->textContent));
        $this->assertStringContainsString('Anna Bucher', $this->norm($cell->textContent));
    }

    #[Test]
    public function own_booking_with_search_keeps_own_state_and_shows_remaining(): void
    {
        $owner = $this->member();
        $this->search($this->booking($owner, 15), 15);

        $html = $this->calendar($owner);
        $cell = $this->cell($this->xpath($html), 15);

        $this->assertSame('own', $cell->getAttribute('data-state'));
        $this->assertStringContainsString('cc-own', $cell->getAttribute('class'));
        $this->assertStringNotContainsString('cc-seeking', $cell->getAttribute('class'));
        $this->assertStringContainsString('Sucht noch 1 Mitspieler', $this->norm($cell->textContent));
        $this->assertStringContainsString("\$dispatch('open-cancel'", $this->click($html, 'own'));
    }

    #[Test]
    public function filled_search_cell_is_a_normal_booking(): void
    {
        $owner = User::factory()->create();
        $this->search($this->booking($owner, 15), 15, 1, PlayerSearch::FILLED);
        // Open status but every place taken (a stale status must not keep the marker).
        $search = $this->search($this->booking($owner, 16), 16);
        PlayerSignup::create(['search_id' => $search->id, 'uid' => $this->member()->uid, 'slot' => 2]);

        // The same for a member in the player exchange and for one outside it.
        foreach (['opted in' => $this->member(true), 'not opted in' => $this->member()] as $viewerLabel => $viewer) {
            $xp = $this->xpath($this->calendar($viewer));

            foreach ([15, 16] as $hour) {
                $cell = $this->cell($xp, $hour);
                $this->assertSame('single_future', $cell->getAttribute('data-state'), $viewerLabel.', hour '.$hour);
                $this->assertStringNotContainsString('Sucht noch', $this->norm($cell->textContent), $viewerLabel.', hour '.$hour);
            }
        }
    }

    #[Test]
    public function expired_or_past_search_is_not_seeking(): void
    {
        $owner = User::factory()->create();
        // Deadline 12:00 already passed (lapse not run yet) and a slot that is over.
        $this->search($this->booking($owner, 13), 13);
        $this->search($this->booking($owner, 11), 12);

        $xp = $this->xpath($this->calendar($this->member(true)));

        $this->assertSame('single_future', $this->cell($xp, 13)->getAttribute('data-state'));
        $this->assertStringNotContainsString('Sucht noch', $this->norm($this->cell($xp, 13)->textContent));
        $this->assertSame('past', $this->cell($xp, 11)->getAttribute('data-state'));
    }

    #[Test]
    public function legend_lists_seeking_for_logged_in_users(): void
    {
        $xp = $this->xpath($this->calendar($this->member()));
        $item = $xp->query('//ul[contains(@class,"cal-legend")]/li[@data-state="seeking"]')->item(0);

        $this->assertNotNull($item);
        $this->assertSame(1, $xp->query('.//span[@aria-hidden="true"][contains(@class,"cal-swatch--seeking")]', $item)->length);
        $this->assertSame('Sucht Mitspieler', $this->norm($item->textContent));
    }

    #[Test]
    public function opted_in_member_click_dispatches_open_join_with_booking_data(): void
    {
        $booking = $this->booking(User::factory()->create(), 15, 4);
        $search = $this->search($booking, 15, 2);
        $viewer = $this->member(true);
        PlayerSignup::create(['search_id' => $search->id, 'uid' => $viewer->uid, 'slot' => 2]);

        $html = $this->calendar($viewer);
        $cell = $this->cell($this->xpath($html), 15);

        $this->assertSame('a', $cell->nodeName);
        $click = $this->click($html, 'seeking');
        $this->assertStringContainsString("\$dispatch('open-join'", $click);
        $this->assertStringContainsString("bid: '".$booking->bid."'", $click);
        $this->assertStringContainsString('remaining: 1', $click);
        $this->assertStringContainsString('joined: true', $click);
        $this->assertStringContainsString('optedIn: true', $click);
        $this->assertStringContainsString('Court A', $click);
        $this->assertStringContainsString('15:00', $click);
    }

    #[Test]
    public function member_outside_the_exchange_click_opens_the_join_dialog_to_join_with_one_click(): void
    {
        $this->search($this->booking(User::factory()->create(), 15), 15);

        $html = $this->calendar($this->member());
        $cell = $this->cell($this->xpath($html), 15);

        $this->assertSame('seeking', $cell->getAttribute('data-state'));
        $click = $this->click($html, 'seeking');
        $this->assertStringContainsString("\$dispatch('open-join'", $click);
        $this->assertStringContainsString('optedIn: false', $click);
        $this->assertStringNotContainsString('open-join-info', $html);
        $this->assertStringNotContainsString('join-info-dialog', $html);
    }

    #[Test]
    public function guest_seeking_cell_links_to_login(): void
    {
        $this->search($this->booking(User::factory()->create(), 15), 15);

        $cell = $this->cell($this->xpath($this->calendar()), 15);

        $this->assertSame('seeking', $cell->getAttribute('data-state'));
        $this->assertStringStartsWith(route('login'), $cell->getAttribute('href'));
        $this->assertStringContainsString('Sucht noch 1', $this->norm($cell->textContent));
        $this->assertStringContainsString('Belegt', $this->norm($cell->textContent));
    }

    #[Test]
    public function english_texts_exist_with_plural_forms(): void
    {
        $this->assertSame('Looking for 1 more player', trans_choice('booking.calendar.seeking_line', 1, [], 'en'));
        $this->assertSame('Looking for 2 more players', trans_choice('booking.calendar.seeking_line', 2, [], 'en'));
        $this->assertSame('Needs 2 more', trans_choice('booking.calendar.seeking_short', 2, [], 'en'));
        $this->assertSame('Looking for players', __('booking.calendar.legend.seeking', [], 'en'));
        $this->assertSame('Sucht noch 3 Mitspieler', trans_choice('booking.calendar.seeking_line', 3, [], 'de'));
    }

    #[Test]
    public function several_seeking_cells_do_not_add_queries_per_booking(): void
    {
        $viewer = $this->member(true);
        $countQueries = function () use ($viewer): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($viewer)->get('/calendar?date=2026-11-04')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $addSeeking = function (int $hour): void {
            $search = $this->search($this->booking(User::factory()->create(), $hour, 4), $hour, 3);
            PlayerSignup::create(['search_id' => $search->id, 'uid' => User::factory()->create()->uid, 'slot' => 2]);
        };

        $addSeeking(15);
        $withOne = $countQueries();
        $addSeeking(16);
        $addSeeking(17);
        $addSeeking(18);

        $this->assertSame($withOne, $countQueries());
    }

    #[Test]
    public function search_and_series_differ_by_shape_not_only_by_colour(): void
    {
        $css = (string) file_get_contents(resource_path('css/calendar-grid.css'));
        $toolbar = (string) file_get_contents(resource_path('css/calendar-toolbar.css'));

        // Search: 2px ring and a person-plus symbol before the search text.
        $this->assertMatchesRegularExpression('/\.cal-row \.cc-seeking \{[^}]*inset 0 0 0 2px var\(--cal-seeking-line\)/s', $css);
        $this->assertStringContainsString('.cal-row .cc-label-seeking::before', $css);
        // Series: a bar on the left edge.
        $this->assertMatchesRegularExpression('/\.cc-series:not\(\.slot-cell--past\):not\(\.cc-turnier\) \{[^}]*inset 4px 0 0 0 var\(--cal-series-label\)/s', $css);
        // The legend explains both.
        $this->assertStringContainsString('.cal-swatch--series', $toolbar);
    }
}
