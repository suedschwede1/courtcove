<?php

namespace Tests\Feature\Calendar;

use App\Models\Booking;
use App\Models\BookingMeta;
use App\Models\Event;
use App\Models\EventMeta;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\SquareMeta;
use App\Models\User;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\QueriesHtml;
use Tests\TestCase;

final class CalendarCellStatesTest extends TestCase
{
    use LazilyRefreshDatabase;
    use QueriesHtml;

    private Square $court;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-04 12:30'));

        $this->court = Square::factory()->create(['name' => '1', 'priority' => 1]);
        SquareMeta::create(['sid' => $this->court->sid, 'key' => 'alias', 'value' => 'Court A']);
    }

    private function calendar(?User $viewer = null, bool $adminMode = false): string
    {
        $request = $viewer ? $this->actingAs($viewer) : $this;
        if ($adminMode) {
            $request = $request->withUnencryptedCookie('admin_mode', '1');
        }

        return (string) $request->get('/calendar?date=2026-11-04')->assertOk()->getContent();
    }

    private function cell(DOMXPath $xp, int $hour): DOMElement
    {
        $node = $xp->query('(//section[@data-day="1"]//div[contains(@class,"cal-row")])['.($hour - 7).']/*[contains(@class,"calendar-cell") or contains(@class,"event-cell")][1]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $node, 'cell '.$hour);

        return $node;
    }

    private function text(DOMXPath $xp, DOMElement $cell, string $class): string
    {
        $node = $xp->query('.//span[contains(concat(" ", @class, " "), " '.$class.' ")]', $cell)->item(0);
        $this->assertNotNull($node, $class);

        return trim((string) preg_replace('/\s+/u', ' ', $node->textContent));
    }

    private function norm(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** @param list<string> $players */
    private function booking(User $owner, int $hour, string $status = 'single', array $players = [], ?string $notes = null): Booking
    {
        $booking = Booking::factory()->create(['uid' => $owner->uid, 'sid' => $this->court->sid, 'status' => $status, 'quantity' => 2]);
        if ($players !== []) {
            $entries = [];
            foreach ($players as $index => $name) {
                $entries[] = ['name' => 'sb-player-name-'.($index + 2), 'value' => $name];
            }
            BookingMeta::create(['bid' => $booking->bid, 'key' => 'player-names', 'value' => serialize($entries)]);
        }
        if ($notes !== null) {
            BookingMeta::create(['bid' => $booking->bid, 'key' => 'notes', 'value' => $notes]);
        }
        Reservation::factory()->create([
            'bid' => $booking->bid,
            'date' => '2026-11-04',
            'time_start' => sprintf('%02d:00:00', $hour),
            'time_end' => sprintf('%02d:00:00', $hour + 1),
        ]);

        return $booking->fresh(['meta', 'user']) ?? $booking;
    }

    #[Test]
    public function free_cell_is_a_real_link_with_an_aria_label(): void
    {
        $member = User::factory()->create(['status' => 'enabled']);
        $html = $this->calendar($member);
        $cell = $this->cell($this->xpath($html), 15);

        $this->assertSame('a', $cell->nodeName);
        $this->assertSame('free', $cell->getAttribute('data-state'));
        $this->assertStringStartsWith('calendar-cell cc-free', $cell->getAttribute('class'));
        $this->assertSame('Court A, 15:00 Uhr buchen', $cell->getAttribute('aria-label'));

        $href = $cell->getAttribute('href');
        $this->assertSame(route('bookings.create', ['sid' => $this->court->sid, 'date' => '2026-11-04', 'time_start' => 15]), $href);
        $this->assertStringContainsString("@click.prevent=\"\$dispatch('open-booking'", $html);

        $this->get($href)->assertOk();
    }

    #[Test]
    public function guest_free_cell_links_to_login(): void
    {
        $cell = $this->cell($this->xpath($this->calendar()), 15);

        $this->assertSame('free', $cell->getAttribute('data-state'));
        $this->assertStringContainsString('guest-login-cell', $cell->getAttribute('class'));
        $this->assertSame('Court A, 15:00 Uhr – zum Buchen anmelden', $cell->getAttribute('aria-label'));
        $this->assertStringStartsWith(route('login'), $cell->getAttribute('href'));
    }

    #[Test]
    public function admin_mode_free_cell_href_is_the_admin_create_url(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $cell = $this->cell($this->xpath($this->calendar($admin, true)), 15);

        $this->assertSame('free', $cell->getAttribute('data-state'));
        $this->assertNotSame('', $cell->getAttribute('data-create-url'));
        $this->assertSame($cell->getAttribute('data-create-url'), $cell->getAttribute('href'));
        $this->assertStringContainsString('booking-trigger', $cell->getAttribute('class'));
        $this->assertSame('admin-book', $cell->getAttribute('data-action'));
        $this->assertSame('Court A, 15:00 Uhr buchen', $cell->getAttribute('aria-label'));
    }

    #[Test]
    public function past_empty_cell_is_a_hatched_span(): void
    {
        $member = User::factory()->create(['status' => 'enabled']);
        $cell = $this->cell($this->xpath($this->calendar($member)), 9);

        $this->assertSame('span', $cell->nodeName);
        $this->assertSame('past', $cell->getAttribute('data-state'));
        $classes = explode(' ', $cell->getAttribute('class'));
        $this->assertContains('cc-over', $classes);
        $this->assertContains('slot-cell--past', $classes);
        $this->assertFalse($cell->hasAttribute('href'));
        $this->assertFalse($cell->hasAttribute('aria-label'));
    }

    #[Test]
    public function own_booking_shows_two_lines_and_the_phone_label(): void
    {
        $member = User::factory()->create(['status' => 'enabled', 'alias' => 'Example Member']);
        $booking = $this->booking($member, 15, players: ['Example Partner']);

        $html = $this->calendar($member);
        $xp = $this->xpath($html);
        $cell = $this->cell($xp, 15);

        $this->assertSame('a', $cell->nodeName);
        $this->assertSame('own', $cell->getAttribute('data-state'));
        $this->assertStringStartsWith('calendar-cell cc-own', $cell->getAttribute('class'));
        $this->assertSame($booking->owner_label, $this->text($xp, $cell, 'cc-label-primary'));
        $this->assertSame($booking->player_names_label, $this->text($xp, $cell, 'cc-label-secondary'));
        $this->assertSame('Example Partner', $this->text($xp, $cell, 'cc-label-secondary'));
        $this->assertSame('Meine', $this->text($xp, $cell, 'cc-label-own'));
        $this->assertStringContainsString("\$dispatch('open-cancel'", $html);
    }

    #[Test]
    public function others_booking_is_a_single_future_span(): void
    {
        $other = User::factory()->create(['status' => 'enabled', 'alias' => 'Other Member']);
        $this->booking($other, 15);

        $member = User::factory()->create(['status' => 'enabled']);
        $xp = $this->xpath($this->calendar($member));
        $cell = $this->cell($xp, 15);

        $this->assertSame('span', $cell->nodeName);
        $this->assertSame('single_future', $cell->getAttribute('data-state'));
        $this->assertContains('cc-single-future', explode(' ', $cell->getAttribute('class')));
        $this->assertSame('Other Member', $this->text($xp, $cell, 'cc-label-primary'));
    }

    #[Test]
    public function series_booking_shows_the_series_hint(): void
    {
        $other = User::factory()->create(['status' => 'enabled', 'alias' => 'Other Member']);
        $booking = $this->booking($other, 15, 'subscription');
        $this->assertTrue($booking->isSubscription());

        $member = User::factory()->create(['status' => 'enabled']);
        $xp = $this->xpath($this->calendar($member));
        $cell = $this->cell($xp, 15);

        $this->assertSame('series', $cell->getAttribute('data-state'));
        $classes = explode(' ', $cell->getAttribute('class'));
        $this->assertContains('cc-single-future', $classes);
        $this->assertContains('cc-series', $classes);
        $this->assertSame('Abo', $this->text($xp, $cell, 'cc-label-secondary'));
    }

    #[Test]
    public function championship_booking_is_a_tournament_cell(): void
    {
        $other = User::factory()->create(['status' => 'enabled']);
        $this->booking($other, 15, notes: 'Vereinsmeisterschaft');

        $member = User::factory()->create(['status' => 'enabled']);
        $cell = $this->cell($this->xpath($this->calendar($member)), 15);

        $this->assertSame('tournament', $cell->getAttribute('data-state'));
        $this->assertContains('cc-turnier', explode(' ', $cell->getAttribute('class')));
    }

    #[Test]
    public function past_own_booking_keeps_its_cancel_behaviour(): void
    {
        $member = User::factory()->create(['status' => 'enabled']);
        $this->booking($member, 10);

        $html = $this->calendar($member);
        $cell = $this->cell($this->xpath($html), 10);

        $this->assertSame('a', $cell->nodeName);
        $this->assertSame('past', $cell->getAttribute('data-state'));
        $classes = explode(' ', $cell->getAttribute('class'));
        $this->assertContains('cc-own', $classes);
        $this->assertContains('slot-cell--past', $classes);
        // DOMDocument drops the Alpine "@click.prevent" attribute, so check the raw tag.
        $this->assertMatchesRegularExpression(
            '/<a\b[^>]*class="calendar-cell cc-own slot-cell--past"[^>]*@click\.prevent="\$dispatch\(\'open-cancel\'/',
            $html,
        );
    }

    #[Test]
    public function event_parts_show_name_and_time_range(): void
    {
        $event = Event::factory()->create([
            'sid' => $this->court->sid,
            'status' => 'enabled',
            'datetime_start' => Carbon::parse('2026-11-04 14:00'),
            'datetime_end' => Carbon::parse('2026-11-04 16:00'),
        ]);
        EventMeta::create(['eid' => $event->eid, 'key' => 'name', 'value' => 'Example Training']);

        $member = User::factory()->create(['status' => 'enabled']);
        $xp = $this->xpath($this->calendar($member));

        $start = $this->cell($xp, 14);
        $this->assertStringContainsString('event-cell--start', $start->getAttribute('class'));
        $this->assertSame('event', $start->getAttribute('data-state'));
        $this->assertStringContainsString('Example Training', $start->textContent);
        $this->assertStringContainsString('14 – 16 Uhr', $start->textContent);

        $end = $this->cell($xp, 15);
        $this->assertStringContainsString('event-cell--end', $end->getAttribute('class'));
        $label = $xp->query('.//span[contains(@class,"event-label")]', $end)->item(0);
        $this->assertInstanceOf(DOMElement::class, $label);
        $this->assertSame('true', $label->getAttribute('aria-hidden'));
        $this->assertStringNotContainsString('14 – 16 Uhr', $end->textContent);
    }

    #[Test]
    public function owner_name_is_not_repeated_in_the_detail_line(): void
    {
        $member = User::factory()->create(['status' => 'enabled', 'alias' => 'Example Member']);
        $this->booking($member, 15, players: ['Example Member']);
        $this->booking($member, 16, players: ['  example member ', 'Example Partner']);

        $html = $this->calendar($member);
        $xp = $this->xpath($html);

        $only = $this->cell($xp, 15);
        $this->assertSame('Example Member', $this->text($xp, $only, 'cc-label-primary'));
        $this->assertSame(0, $xp->query('.//span[contains(concat(" ", @class, " "), " cc-label-secondary ")]', $only)->length);
        $this->assertSame(1, substr_count($this->norm($only->textContent), 'Example Member'));

        $both = $this->cell($xp, 16);
        $this->assertSame('Example Partner', $this->text($xp, $both, 'cc-label-secondary'));
    }

    #[Test]
    public function others_booking_and_series_do_not_repeat_the_owner(): void
    {
        $other = User::factory()->create(['status' => 'enabled', 'alias' => 'Other Member']);
        $this->booking($other, 15, players: ['Other Member']);
        $this->booking($other, 16, 'subscription', ['Other Member']);

        $xp = $this->xpath($this->calendar(User::factory()->create(['status' => 'enabled'])));

        $single = $this->cell($xp, 15);
        $this->assertSame(1, substr_count($this->norm($single->textContent), 'Other Member'));
        $this->assertSame(0, $xp->query('.//span[contains(concat(" ", @class, " "), " cc-label-secondary ")]', $single)->length);

        // A series without other players shows the series hint instead.
        $series = $this->cell($xp, 16);
        $this->assertSame(1, substr_count($this->norm($series->textContent), 'Other Member'));
        $this->assertSame('Abo', $this->text($xp, $series, 'cc-label-secondary'));
    }

    #[Test]
    public function admin_booking_for_someone_else_shows_that_member_once(): void
    {
        $admin = User::factory()->create(['status' => 'admin', 'alias' => 'Example Admin']);
        $booking = $this->booking($admin, 15, players: ['Example Admin', 'Example Guest']);
        BookingMeta::create(['bid' => $booking->bid, 'key' => 'owner-name', 'value' => 'Example Guest']);

        $xp = $this->xpath($this->calendar($admin, true));
        $cell = $this->cell($xp, 15);

        $this->assertSame('Example Guest', $this->text($xp, $cell, 'cc-label-primary'));
        $this->assertSame('Example Admin', $this->text($xp, $cell, 'cc-label-secondary'));
    }

    #[Test]
    public function closure_event_renders_as_blocked_and_plain_event_as_event(): void
    {
        $closure = Event::factory()->create([
            'sid' => $this->court->sid, 'status' => 'enabled',
            'datetime_start' => Carbon::parse('2026-11-04 14:00'), 'datetime_end' => Carbon::parse('2026-11-04 16:00'),
        ]);
        EventMeta::create(['eid' => $closure->eid, 'key' => 'name', 'value' => 'Resurfacing']);
        EventMeta::create(['eid' => $closure->eid, 'key' => 'kind', 'value' => 'closure']);

        $plain = Event::factory()->create([
            'sid' => $this->court->sid, 'status' => 'enabled',
            'datetime_start' => Carbon::parse('2026-11-04 17:00'), 'datetime_end' => Carbon::parse('2026-11-04 18:00'),
        ]);
        EventMeta::create(['eid' => $plain->eid, 'key' => 'name', 'value' => 'Example Training']);

        $xp = $this->xpath($this->calendar(User::factory()->create(['status' => 'enabled'])));

        foreach ([14 => 'start', 15 => 'end'] as $hour => $part) {
            $cell = $this->cell($xp, $hour);
            $this->assertSame('blocked', $cell->getAttribute('data-state'));
            $classes = explode(' ', $cell->getAttribute('class'));
            $this->assertContains('event-cell--blocked', $classes);
            $this->assertContains('event-cell--'.$part, $classes);
        }

        $event = $this->cell($xp, 17);
        $this->assertSame('event', $event->getAttribute('data-state'));
        $this->assertNotContains('event-cell--blocked', explode(' ', $event->getAttribute('class')));
    }

    #[Test]
    public function event_named_like_a_closure_is_blocked_unless_its_kind_says_event(): void
    {
        foreach ([14 => null, 16 => 'event'] as $hour => $kind) {
            $event = Event::factory()->create([
                'sid' => $this->court->sid, 'status' => 'enabled',
                'datetime_start' => Carbon::parse(sprintf('2026-11-04 %02d:00', $hour)),
                'datetime_end' => Carbon::parse(sprintf('2026-11-04 %02d:00', $hour + 1)),
            ]);
            EventMeta::create(['eid' => $event->eid, 'key' => 'name', 'value' => 'Platzsperre']);
            if ($kind !== null) {
                EventMeta::create(['eid' => $event->eid, 'key' => 'kind', 'value' => $kind]);
            }
        }

        $xp = $this->xpath($this->calendar(User::factory()->create(['status' => 'enabled'])));

        $this->assertSame('blocked', $this->cell($xp, 14)->getAttribute('data-state'));
        $this->assertSame('event', $this->cell($xp, 16)->getAttribute('data-state'));
    }

    #[Test]
    public function guests_never_get_names_in_labels(): void
    {
        $other = User::factory()->create(['status' => 'enabled', 'alias' => 'Hidden Member']);
        $this->booking($other, 15, players: ['Hidden Partner']);

        $html = $this->calendar();
        $this->assertStringNotContainsString('Hidden Member', $html);
        $this->assertStringNotContainsString('Hidden Partner', $html);

        $xp = $this->xpath($html);
        $this->assertSame('Belegt', $this->text($xp, $this->cell($xp, 15), 'cc-label-primary'));
    }
}
