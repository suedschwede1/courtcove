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
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\QueriesHtml;
use Tests\TestCase;

/** "Suche beenden" in the cancel dialog of the booker's own booking. */
final class EndSearchButtonTest extends TestCase
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

    private function calendar(User $viewer): string
    {
        return (string) $this->actingAs($viewer)->get('/calendar?date=2026-11-04')->assertOk()->getContent();
    }

    /** Alpine attributes (@click) do not survive the DOM parser, so read the raw opening tags. */
    private function clicks(string $html, string $state): array
    {
        preg_match_all('/<a\s[^>]*data-state="'.$state.'"[^>]*>/s', $html, $tags);
        $clicks = [];
        foreach ($tags[0] as $tag) {
            $this->assertSame(1, preg_match('/@click\.prevent="([^"]*)"/s', $tag, $m));
            $clicks[] = html_entity_decode($m[1], ENT_QUOTES);
        }

        return $clicks;
    }

    private function booking(User $owner, int $hour = 15, int $quantity = 4): Booking
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

    private function search(Booking $booking, int $needed = 2, string $status = PlayerSearch::OPEN): PlayerSearch
    {
        return PlayerSearch::create([
            'bid' => $booking->bid,
            'needed' => $needed,
            'ends_at' => Carbon::parse('2026-11-04 14:00'),
            'status' => $status,
        ]);
    }

    private function member(): User
    {
        return User::factory()->create(['status' => 'enabled']);
    }

    /** The cancel dialog block: from its x-data wrapper to the edit dialog after it. */
    private function dialog(string $html): string
    {
        $start = strpos($html, 'id="cancel-dialog"');
        $this->assertNotFalse($start);
        $start = strrpos(substr($html, 0, $start), '<div x-data');
        $end = strpos($html, 'id="edit-dialog"', $start);
        $this->assertNotFalse($end);

        return html_entity_decode(substr($html, $start, $end - $start), ENT_QUOTES);
    }

    #[Test]
    public function own_cell_with_open_search_offers_end_search(): void
    {
        $owner = $this->member();
        $this->search($this->booking($owner), 3);

        $clicks = $this->clicks($this->calendar($owner), 'own');

        $this->assertCount(1, $clicks);
        $this->assertStringContainsString("\$dispatch('open-cancel'", $clicks[0]);
        $this->assertStringContainsString('searchOpen: true', $clicks[0]);
        $this->assertStringContainsString('searchRemaining: 3', $clicks[0]);
    }

    #[Test]
    public function own_cell_without_search_does_not(): void
    {
        $owner = $this->member();
        $this->booking($owner);

        $clicks = $this->clicks($this->calendar($owner), 'own');

        $this->assertCount(1, $clicks);
        $this->assertStringContainsString('searchOpen: false', $clicks[0]);
        $this->assertStringContainsString('searchRemaining: 0', $clicks[0]);
    }

    #[Test]
    public function ended_and_filled_searches_are_not_offered_for_ending(): void
    {
        $owner = $this->member();

        // Ended by the booker earlier: nothing left to end.
        $this->search($this->booking($owner, 15), 2, PlayerSearch::ENDED);

        // Open, but all places taken.
        $search = $this->search($this->booking($owner, 17, 2), 1);
        PlayerSignup::create(['search_id' => $search->id, 'uid' => $this->member()->uid, 'slot' => 2]);

        $clicks = $this->clicks($this->calendar($owner), 'own');

        $this->assertCount(2, $clicks);
        foreach ($clicks as $click) {
            $this->assertStringContainsString('searchOpen: false', $click);
        }
    }

    #[Test]
    public function other_members_never_get_the_cancel_dialog_for_a_search_cell(): void
    {
        $this->search($this->booking($this->member()));
        $viewer = $this->member();
        app(PlayerExchangeService::class)->optIn($viewer);

        $html = $this->calendar($viewer);

        $this->assertSame([], $this->clicks($html, 'own'));
        $seeking = $this->clicks($html, 'seeking');
        $this->assertCount(1, $seeking);
        $this->assertStringNotContainsString('open-cancel', $seeking[0]);
        $this->assertStringNotContainsString('searchOpen', $seeking[0]);
    }

    #[Test]
    public function cancel_dialog_has_the_end_search_button_wired_to_the_end_route(): void
    {
        $html = $this->calendar($this->member());
        $dialog = $this->dialog($html);
        $xp = $this->xpath($html);

        // Only while a search is open, secondary like "Buchung bearbeiten", locked while the request runs.
        $button = $xp->query('//*[@id="cancel-dialog"]//button[@data-end-search]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $button);
        $this->assertSame('searchOpen', $button->getAttribute('x-show'));
        $this->assertStringContainsString('ds-btn--secondary', $button->getAttribute('class'));
        $this->assertSame('loading', $button->getAttribute('x-bind:disabled'));
        $this->assertStringContainsString(__('booking.calendar.end.button'), $dialog);

        // POST to player-exchange.end as JSON with CSRF, then reload.
        $this->assertStringContainsString(trim(json_encode(route('player-exchange.end', ['booking' => '__BID__'])), '"'), $dialog);
        $this->assertStringContainsString("method: 'POST'", $dialog);
        $this->assertStringContainsString('X-CSRF-TOKEN', $dialog);
        $this->assertStringContainsString('application/json', $dialog);
        $this->assertStringContainsString('window.location.reload()', $dialog);

        // Double submit and close-while-loading behave like the join dialog.
        $this->assertMatchesRegularExpression('/async endSearch\(\) \{\s*if \(this\.loading\) return;/', $dialog);
        $this->assertMatchesRegularExpression('/close\(\) \{.*?if \(this\.loading\) \{\s*this\.closeAfterRequest = true;/s', $dialog);
        $this->assertMatchesRegularExpression('/finally \{.*?if \(this\.closeAfterRequest\) \{\s*window\.location\.reload\(\);/s', $dialog);

        // Result inside the dialog: controller message, refusal reason (error notice), generic failure.
        $this->assertStringContainsString(__('booking.messages.player_exchange.ended'), $dialog);
        $this->assertStringContainsString(__('booking.calendar.end.failed'), $dialog);
        $this->assertSame(1, $xp->query('//*[@id="cancel-dialog"]//*[contains(@class,"ds-notice--problem")]')->length, 'error notice');
        $this->assertSame(1, $xp->query('//*[@id="cancel-dialog"]//*[contains(concat(" ", @class, " "), " ds-success ")]')->length, 'success state');
    }

    #[Test]
    public function normal_cancel_form_is_unchanged(): void
    {
        $xp = $this->xpath($this->calendar($this->member()));

        $form = $xp->query('//*[@id="cancel-dialog"]//form[@method="POST"]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $form);
        $this->assertStringContainsString(url('/bookings'), str_replace('\/', '/', $form->getAttribute('x-bind:action')));
        $this->assertSame(__('booking.modal.cancel_booking'), trim($xp->query('.//button[@type="submit"]', $form)->item(0)->textContent));
    }

    #[Test]
    public function end_search_texts_exist_in_both_languages(): void
    {
        foreach (['de', 'en'] as $locale) {
            app()->setLocale($locale);
            foreach (['button', 'ending', 'hint', 'done_title', 'failed'] as $key) {
                $this->assertNotSame('booking.calendar.end.'.$key, __('booking.calendar.end.'.$key), $locale.' '.$key);
            }
        }
        app()->setLocale('en');
        $this->assertSame('End search', __('booking.calendar.end.button'));
        app()->setLocale('de');
        $this->assertSame('Suche beenden', __('booking.calendar.end.button'));
    }

    #[Test]
    public function the_cancel_dialog_says_what_cancelling_does_to_the_search_and_the_joined_players(): void
    {
        $owner = $this->member();
        // Open search; a filled one with a joined player; a booking without search.
        $this->search($this->booking($owner, 15), 2);
        $filled = $this->search($this->booking($owner, 17, 2), 1, PlayerSearch::FILLED);
        PlayerSignup::create(['search_id' => $filled->id, 'uid' => $this->member()->uid, 'slot' => 2]);
        $this->booking($owner, 19);

        $html = $this->calendar($owner);
        $clicks = $this->clicks($html, 'own');

        $this->assertCount(3, $clicks);
        $this->assertStringContainsString('searchSignups: 0', $clicks[0]);
        $this->assertStringContainsString('searchSignups: 1', $clicks[1]);
        $this->assertStringContainsString('searchSignups: 0', $clicks[2]);

        $dialog = $this->dialog($html);
        $this->assertMatchesRegularExpression(
            '/x-show="searchOpen \|\| searchSignups > 0"[^>]*>'.preg_quote(__('booking.calendar.end.cancel_effect'), '/').'/',
            $dialog,
        );
        $this->assertNotSame(__('booking.calendar.end.cancel_effect', [], 'de'), __('booking.calendar.end.cancel_effect', [], 'en'));
    }
}
