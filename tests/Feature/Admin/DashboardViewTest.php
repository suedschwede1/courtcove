<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Event;
use App\Models\EventMeta;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use App\Models\UserMeta;
use DOMDocument;
use DOMNodeList;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DashboardViewTest extends TestCase
{
    use LazilyRefreshDatabase;

    private DOMXPath $xpath;

    private function load(string $html): void
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        $this->xpath = new DOMXPath($dom);
    }

    private function nodes(string $query): DOMNodeList
    {
        $nodes = $this->xpath->query($query);
        $this->assertNotFalse($nodes);

        return $nodes;
    }

    private function text(string $query): string
    {
        $node = $this->nodes($query)->item(0);
        $this->assertNotNull($node, $query.' not found');

        return trim((string) preg_replace('/\s+/u', ' ', $node->textContent));
    }

    private static function cls(string $class): string
    {
        return 'contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")';
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    /** One court, one booking today 10:00-11:00, one cancelled booking, one future event. */
    private function seedDashboard(): Square
    {
        $square = Square::factory()->create(['name' => 'Court A']);
        $member = User::factory()->create(['alias' => 'Pat Example']);

        $booking = Booking::factory()->create(['uid' => $member->uid, 'sid' => $square->sid, 'status' => 'single']);
        Reservation::factory()->create(['bid' => $booking->bid, 'date' => now()->toDateString(), 'time_start' => '10:00:00', 'time_end' => '11:00:00']);

        $cancelled = Booking::factory()->create(['uid' => $member->uid, 'sid' => $square->sid, 'status' => 'cancelled']);
        Reservation::factory()->create(['bid' => $cancelled->bid, 'date' => now()->toDateString(), 'time_start' => '12:00:00', 'time_end' => '13:00:00']);

        $event = Event::factory()->create([
            'sid' => $square->sid,
            'datetime_start' => now()->addDays(2)->setTime(17, 0),
            'datetime_end' => now()->addDays(2)->setTime(19, 0),
        ]);
        EventMeta::create(['eid' => $event->eid, 'key' => 'name', 'value' => 'Example Training', 'locale' => null]);

        return $square;
    }

    #[Test]
    public function kpis_are_four_cards_without_coloured_border(): void
    {
        $this->seedDashboard();
        $html = $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()->getContent();
        $this->load($html);

        $tiles = $this->nodes('//div[contains(@class,"ds-admin-kpis")]/*');
        $this->assertSame(4, $tiles->length);
        foreach ($tiles as $tile) {
            $this->assertSame(1, $this->xpath->query('.//*['.self::cls('ds-kpi').']', $tile)->length);
        }

        $labels = array_map(
            fn ($n) => trim($n->textContent),
            iterator_to_array($this->nodes('//div[contains(@class,"ds-admin-kpis")]//*['.self::cls('ds-kpi__label').']'))
        );
        $this->assertSame(['Buchungen heute', 'Aktive Mitglieder', 'Diese Woche', 'Veranstaltungen'], $labels);
        $this->assertStringNotContainsString('border-top', $html);
    }

    #[Test]
    public function today_table_lists_time_court_member_status(): void
    {
        $this->seedDashboard();
        $this->load($this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()->getContent());

        $heads = array_map(
            fn ($n) => trim($n->textContent),
            iterator_to_array($this->nodes('//main//table//thead//th'))
        );
        $this->assertSame(['Zeit', 'Platz', 'Mitglied', 'Status'], $heads);

        $this->assertSame('10:00 – 11:00', $this->text('//main//table//tbody/tr[1]/td[@data-label="Zeit"]'));
        $this->assertSame('Court A', $this->text('//main//table//tbody/tr[1]/td[@data-label="Platz"]'));
        $this->assertSame('Pat Example', $this->text('//main//table//tbody/tr[1]/td[@data-label="Mitglied"]'));
        $this->assertSame('Aktiv', $this->text('//main//table//tbody/tr[1]//span['.self::cls('ds-badge--good').']'));
        $this->assertSame('Alle Buchungen', $this->text('//main//a[@href="'.route('admin.bookings.index').'"]'));
    }

    #[Test]
    public function cancelled_booking_maps_to_the_problem_badge(): void
    {
        // The controller lists only active bookings; the view still maps a cancelled one.
        $square = $this->seedDashboard();
        $cancelled = Booking::with(['square', 'user', 'reservations'])->where('status', 'cancelled')->firstOrFail();
        $this->actingAs($this->admin());

        $html = (string) $this->view('admin.dashboard', [
            'bookingsToday' => collect([$cancelled]),
            'bookingsTodayCount' => 1,
            'bookingsTodayBySquare' => [$square->display_name => 1],
            'activeMembersCount' => 2,
            'adminCount' => 1,
            'bookingsThisWeek' => 1,
            'bookingsLastWeek' => 1,
            'upcomingEventsCount' => 0,
            'nextEvent' => null,
            'errors' => new ViewErrorBag,
        ]);
        $this->load($html);

        $this->assertSame('Storniert', $this->text('//main//table//tbody/tr[1]//span['.self::cls('ds-badge--problem').']'));
    }

    #[Test]
    public function new_booking_action_for_booking_admins_only(): void
    {
        $this->seedDashboard();
        $link = '//main//a['.self::cls('ds-btn--primary').' and @href="'.route('admin.bookings.create').'"]';

        $this->load($this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()->getContent());
        $this->assertSame(1, $this->nodes($link)->length);

        $assist = User::factory()->create(['status' => 'assist']);
        UserMeta::create(['uid' => $assist->uid, 'key' => 'allow.admin.see-menu', 'value' => 'true']);
        $this->load($this->actingAs($assist)->get(route('admin.dashboard'))->assertOk()->getContent());
        $this->assertSame(0, $this->nodes($link)->length);
    }

    #[Test]
    public function next_event_is_listed_with_a_manage_link(): void
    {
        $this->seedDashboard();
        $this->load($this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()->getContent());

        $this->assertStringContainsString('Example Training', $this->text('//main//*['.self::cls('ds-admin-list__item').']'));
        $this->assertSame('Veranstaltungen verwalten', $this->text('//main//a[@href="'.route('admin.events.index').'"]'));
    }

    #[Test]
    public function week_meta_has_no_arrow_characters(): void
    {
        $this->seedDashboard();
        $html = $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('↑', $html);
        $this->assertStringNotContainsString('↓', $html);
    }
}
