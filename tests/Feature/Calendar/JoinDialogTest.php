<?php

namespace Tests\Feature\Calendar;

use App\Models\Booking;
use App\Models\PlayerSearch;
use App\Models\PlayerSignup;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\SquareMeta;
use App\Models\User;
use App\Services\BookingService;
use App\Services\PlayerExchangeService;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\QueriesHtml;
use Tests\TestCase;

final class JoinDialogTest extends TestCase
{
    use LazilyRefreshDatabase;
    use QueriesHtml;

    private Square $court;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-04 12:30'));
        Cache::put('player-exchange.lapse', 1, 600);

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

    private function search(Booking $booking, int $hour, int $needed = 1): PlayerSearch
    {
        return PlayerSearch::create([
            'bid' => $booking->bid,
            'needed' => $needed,
            'ends_at' => Carbon::parse('2026-11-04')->setTime($hour - 1, 0),
            'status' => PlayerSearch::OPEN,
        ]);
    }

    private function member(bool $optedIn = false, array $attributes = []): User
    {
        $user = User::factory()->create(['status' => 'enabled', ...$attributes]);
        if ($optedIn) {
            app(PlayerExchangeService::class)->optIn($user);
        }

        return $user;
    }

    private function dialog(string $html): string
    {
        // The join dialog block: from the x-data wrapper that listens for open-join to the info dialog after it.
        $listener = strpos($html, '@open-join.window');
        $this->assertNotFalse($listener);
        $start = strrpos(substr($html, 0, $listener), '<div x-data');
        $end = strpos($html, '@open-join-info.window');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        return html_entity_decode(substr($html, $start, $end - $start), ENT_QUOTES);
    }

    #[Test]
    public function join_dialog_markup_is_rendered_for_logged_in_members(): void
    {
        $html = $this->calendar($this->member(true));
        $dialog = $this->dialog($html);

        $xp = $this->xpath($html);
        $node = $xp->query('//*[@id="join-dialog"]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $node);
        $this->assertSame('dialog', $node->getAttribute('role'));
        $this->assertSame('join-dialog-title', $node->getAttribute('aria-labelledby'));

        // Join (POST) and leave (DELETE) go to the routes of the player exchange controller.
        $this->assertStringContainsString(trim(json_encode(route('player-exchange.join', ['booking' => '__BID__'])), '"'), $dialog);
        $this->assertStringContainsString("'DELETE'", $dialog);
        $this->assertStringContainsString('X-CSRF-TOKEN', $dialog);
        $this->assertStringContainsString('application/json', $dialog);
        $this->assertStringContainsString('window.location.reload()', $dialog);
        // Closed while the request runs (Escape, backdrop, X): reload once the answer is in.
        $this->assertMatchesRegularExpression('/close\(\) \{.*?if \(this\.loading\) \{\s*this\.closeAfterRequest = true;/s', $dialog);
        $this->assertMatchesRegularExpression('/finally \{.*?if \(this\.closeAfterRequest\) \{\s*window\.location\.reload\(\);/s', $dialog);
        // Each visible primary action (Mitspielen / Austragen / Zu meinem Konto) can take the first focus.
        $this->assertSame(3, preg_match_all('/\sdata-modal-initial\b/', $dialog), 'join, leave, account');

        // Buttons and texts: join, leave, the hint for members outside the exchange, the result.
        $this->assertStringContainsString('Mitspielen', $dialog);
        $this->assertStringContainsString('Austragen', $dialog);
        $this->assertStringContainsString(route('account.edit'), $dialog);
        $this->assertStringContainsString(__('booking.messages.player_exchange.joined'), $dialog);
        $this->assertStringContainsString(__('booking.messages.player_exchange.left'), $dialog);
        $this->assertStringContainsString(__('booking.calendar.join.failed'), $dialog);
        $this->assertSame(1, $xp->query('//*[@id="join-dialog"]//*[@role="alert" or contains(@class,"ds-notice--problem")]')->length, 'error notice');
        $this->assertSame(1, $xp->query('//*[@id="join-dialog"]//*[contains(concat(" ", @class, " ")," ds-success ")]')->length, 'success state');
    }

    #[Test]
    public function guests_get_no_join_dialog(): void
    {
        $this->search($this->booking(User::factory()->create(), 15), 15);

        $this->assertStringNotContainsString('id="join-dialog"', $this->calendar());
    }

    #[Test]
    public function seeking_cell_dispatches_open_join_with_booking_data(): void
    {
        $owner = User::factory()->create(['alias' => 'Anna Bucher']);
        $booking = $this->booking($owner, 15, 4);
        $search = $this->search($booking, 15, 3);
        $joined = $this->member(true, ['alias' => 'Ben Mitspieler']);
        PlayerSignup::create(['search_id' => $search->id, 'uid' => $joined->uid, 'slot' => 2]);
        app(BookingService::class)->syncPlayerMeta($booking, [2 => $joined->name]);

        $html = $this->calendar($this->member(true));
        $cell = $this->cell($this->xpath($html), 15);
        $click = $this->click($html, 'seeking');

        $this->assertStringContainsString("\$dispatch('open-join'", $click);
        $this->assertStringContainsString("bid: '".$booking->bid."'", $click);
        $this->assertStringContainsString("owner: 'Anna Bucher'", $click);
        $this->assertStringContainsString('remaining: 2', $click);
        $this->assertStringContainsString('joined: false', $click);
        $this->assertStringContainsString('optedIn: true', $click);
        $this->assertMatchesRegularExpression('/players: JSON\.parse\(.*Ben Mitspieler.*\)/s', $click);
        $this->assertStringNotContainsString('Anna Bucher', substr($click, strpos($click, 'players:')));

        // Without JavaScript the link stays on the calendar day instead of a dead "#".
        $this->assertSame(route('calendar.index', ['date' => '2026-11-04']), $cell->getAttribute('href'));
    }

    #[Test]
    public function seeking_cell_without_joined_players_sends_an_empty_list(): void
    {
        $this->search($this->booking(User::factory()->create(), 15), 15);

        $click = $this->click($this->calendar($this->member(true)), 'seeking');

        $this->assertMatchesRegularExpression('/players: \[\]\s*\}\)$/', $click);
    }

    #[Test]
    public function own_cell_with_open_search_names_the_search_in_its_label(): void
    {
        $owner = $this->member(false, ['alias' => 'Olga Eigen']);
        $this->search($this->booking($owner, 15, 4), 15, 2);

        $cell = $this->cell($this->xpath($this->calendar($owner)), 15);
        $label = $cell->getAttribute('aria-label');

        $this->assertSame('own', $cell->getAttribute('data-state'));
        // The usual name (owner, "Meine") stays, the search is appended.
        $this->assertStringStartsWith('Olga Eigen – Meine – ', $label);
        $this->assertStringContainsString('Court A', $label);
        $this->assertStringContainsString('Sucht noch 2 Mitspieler', $label);
    }

    #[Test]
    public function dialog_texts_exist_in_english(): void
    {
        $this->assertSame('Join the game', __('booking.calendar.join.join', [], 'en'));
        $this->assertSame('Leave', __('booking.calendar.join.leave', [], 'en'));
        $this->assertSame('Mitspielen', __('booking.calendar.join.join', [], 'de'));
        $this->assertSame('Austragen', __('booking.calendar.join.leave', [], 'de'));
        $this->assertSame('2 more players wanted', trans_choice('booking.calendar.join.remaining', 2, [], 'en'));
        $this->assertSame('Noch 1 Mitspieler gesucht', trans_choice('booking.calendar.join.remaining', 1, [], 'de'));
    }
}
