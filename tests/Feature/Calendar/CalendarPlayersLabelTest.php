<?php

namespace Tests\Feature\Calendar;

use App\Models\Booking;
use App\Models\BookingMeta;
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

/**
 * Players in the calendar cell: the phone shows them as first names under the name (the desktop keeps the
 * full detail line), tapping someone else's booking lists everyone, and guests never get a name.
 */
final class CalendarPlayersLabelTest extends TestCase
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

    private function calendar(?User $viewer = null): string
    {
        $request = $viewer ? $this->actingAs($viewer) : $this;

        return (string) $request->get('/calendar?date=2026-11-04')->assertOk()->getContent();
    }

    private function cell(DOMXPath $xp, int $hour): DOMElement
    {
        $node = $xp->query('(//section[@data-day="1"]//div[contains(@class,"cal-row")])['.($hour - 7).']/*[contains(@class,"calendar-cell") or contains(@class,"event-cell")][1]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $node, 'cell '.$hour);

        return $node;
    }

    private function labels(DOMXPath $xp, DOMElement $cell, string $class): int
    {
        return $xp->query('.//span[contains(concat(" ", @class, " "), " '.$class.' ")]', $cell)->length;
    }

    private function text(DOMXPath $xp, DOMElement $cell, string $class): string
    {
        $node = $xp->query('.//span[contains(concat(" ", @class, " "), " '.$class.' ")]', $cell)->item(0);
        $this->assertNotNull($node, $class);

        return trim((string) preg_replace('/\s+/u', ' ', $node->textContent));
    }

    /** @param list<string> $players */
    private function booking(User $owner, int $hour, array $players): Booking
    {
        $booking = Booking::factory()->create(['uid' => $owner->uid, 'sid' => $this->court->sid, 'status' => 'single', 'quantity' => 2]);
        $entries = [];
        foreach ($players as $index => $name) {
            $entries[] = ['name' => 'sb-player-name-'.($index + 2), 'value' => $name];
        }
        if ($entries !== []) {
            BookingMeta::create(['bid' => $booking->bid, 'key' => 'player-names', 'value' => serialize($entries)]);
        }
        Reservation::factory()->create([
            'bid' => $booking->bid,
            'date' => '2026-11-04',
            'time_start' => sprintf('%02d:00:00', $hour),
            'time_end' => sprintf('%02d:00:00', $hour + 1),
        ]);

        return $booking;
    }

    #[Test]
    public function a_members_view_of_someone_elses_booking_shows_first_names_and_a_players_dialog(): void
    {
        $owner = User::factory()->create(['status' => 'enabled', 'alias' => 'Example Owner']);
        $this->booking($owner, 17, ['Example Owner', 'Helga Muster', 'Sandra Beispiel', 'Inge Probe']);

        $html = $this->calendar(User::factory()->create(['status' => 'enabled']));
        $xp = $this->xpath($html);
        $cell = $this->cell($xp, 17);

        $this->assertSame('a', $cell->nodeName);
        $this->assertSame('Helga Muster, Sandra Beispiel, Inge Probe', $this->text($xp, $cell, 'cc-label-players'));
        // The full detail line stays for the desktop.
        $this->assertSame('Helga Muster, Sandra Beispiel, Inge Probe', $this->text($xp, $cell, 'cc-label-secondary'));
        $this->assertStringContainsString("\$dispatch('open-players'", $html);
        $this->assertStringContainsString('Example Owner, Helga Muster, Sandra Beispiel, Inge Probe', $cell->getAttribute('aria-label'));
        $this->assertStringContainsString('@open-players.window', $html);
    }

    #[Test]
    public function more_than_four_players_are_counted_with_a_plus(): void
    {
        $owner = User::factory()->create(['status' => 'enabled', 'alias' => 'Example Owner']);
        $this->booking($owner, 17, ['Anna A', 'Berta B', 'Clara C', 'Dora D', 'Emma E']);

        $xp = $this->xpath($this->calendar(User::factory()->create(['status' => 'enabled'])));

        $this->assertSame('Anna A, Berta B, Clara C, Dora D +1', $this->text($xp, $this->cell($xp, 17), 'cc-label-players'));
    }

    #[Test]
    public function guests_get_no_player_names_anywhere(): void
    {
        $owner = User::factory()->create(['status' => 'enabled', 'alias' => 'Example Owner']);
        $this->booking($owner, 17, ['Helga Muster', 'Sandra Beispiel']);

        $html = $this->calendar();
        $xp = $this->xpath($html);
        $cell = $this->cell($xp, 17);

        $this->assertSame('span', $cell->nodeName);
        $this->assertSame(0, $this->labels($xp, $cell, 'cc-label-players'));
        $this->assertStringNotContainsString('Helga', $html);
        $this->assertStringNotContainsString('Sandra', $html);
        $this->assertStringNotContainsString('open-players', $html);
    }

    #[Test]
    public function an_own_booking_shows_the_players_instead_of_mine_and_keeps_mine_without_players(): void
    {
        $member = User::factory()->create(['status' => 'enabled', 'alias' => 'Example Member']);
        $this->booking($member, 15, ['Example Member', 'Helga Muster']);
        $this->booking($member, 16, ['Example Member']);

        $html = $this->calendar($member);
        $xp = $this->xpath($html);

        $withPlayers = $this->cell($xp, 15);
        $this->assertSame('Helga Muster', $this->text($xp, $withPlayers, 'cc-label-players'));
        // "Mine" stays in the markup; the phone CSS hides it while the players are shown.
        $this->assertMatchesRegularExpression('/\.cc-own:has\(\.cc-label-players\) \.cc-label-own \{\s*display: none;/', (string) file_get_contents(resource_path('css/calendar-grid.css')));
        // The cancel dialog gets the names too.
        $this->assertMatchesRegularExpression('/open-cancel.{0,900}players:[^)]*Helga Muster/s', $html);

        $alone = $this->cell($xp, 16);
        $this->assertSame(0, $this->labels($xp, $alone, 'cc-label-players'));
        $this->assertSame(1, $this->labels($xp, $alone, 'cc-label-own'));
    }

    #[Test]
    public function legacy_rows_with_several_names_in_one_field_are_split(): void
    {
        $owner = User::factory()->create(['status' => 'enabled', 'alias' => 'Example Owner']);
        $this->booking($owner, 17, ['Helga, Sandra, Inge']);

        $html = $this->calendar(User::factory()->create(['status' => 'enabled']));
        $xp = $this->xpath($html);
        $cell = $this->cell($xp, 17);

        $this->assertSame('Helga, Sandra, Inge', $this->text($xp, $cell, 'cc-label-players'));
        $this->assertSame('Helga, Sandra, Inge', $this->text($xp, $cell, 'cc-label-secondary'));
        $this->assertSame('Example Owner, Helga, Sandra, Inge', $cell->getAttribute('title'));
    }

    #[Test]
    public function the_phone_rule_shows_the_players_label_and_it_is_hidden_by_default(): void
    {
        $css = (string) file_get_contents(resource_path('css/calendar-grid.css'));

        $this->assertMatchesRegularExpression('/\.cal-row \.cc-label-players \{\s*display: none;/', $css);
        $this->assertMatchesRegularExpression('/@media \(width < 48rem\) \{.*\.cal-row \.cc-label-players \{\s*display: -webkit-box;/s', $css);
        $this->assertStringContainsString('line-clamp: 2', $css);
    }
}
