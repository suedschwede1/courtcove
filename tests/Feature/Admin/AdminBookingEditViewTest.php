<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pins the admin booking form (full page and calendar popup): card grouping, option
 * tiles, the cross-form cancel/delete buttons and the popup's link set.
 */
final class AdminBookingEditViewTest extends TestCase
{
    use LazilyRefreshDatabase;

    private DOMXPath $xpath;

    private User $admin;

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

    private function text(DOMElement|\DOMNode $node): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $node->textContent));
    }

    private static function cls(string $class): string
    {
        return 'contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")';
    }

    /** A double subscription owned by the admin with weekly reservations 2026-07-06/13/20. */
    private function subscription(): Booking
    {
        $this->admin = User::factory()->create(['status' => 'admin']);
        $square = Square::factory()->create(['name' => 'Court A']);
        $booking = Booking::factory()->create([
            'uid' => $this->admin->uid, 'sid' => $square->sid, 'status' => 'subscription', 'quantity' => 4,
        ]);
        foreach (['2026-07-06', '2026-07-13', '2026-07-20'] as $date) {
            Reservation::factory()->create([
                'bid' => $booking->bid, 'date' => $date, 'time_start' => '10:00:00', 'time_end' => '11:00:00',
            ]);
        }

        return $booking;
    }

    private function getEdit(array $query = []): void
    {
        $booking = $this->subscription();
        $url = route('admin.bookings.edit', array_merge([$booking, 'occurrence' => '2026-07-13'], $query));
        $this->load($this->actingAs($this->admin)->get($url)->assertOk()->getContent());
    }

    #[Test]
    public function edit_page_groups_fields_in_who_when_and_note_cards(): void
    {
        $this->getEdit();

        $titles = array_map(fn ($n) => $this->text($n), iterator_to_array($this->nodes('//h2['.self::cls('ds-card__title').']')));
        $this->assertSame(['Wer & Wo', 'Wann', 'Notizen', 'Nur diesen Termin ändern?'], $titles);

        $order = [];
        foreach ($this->nodes('//*[@id="date" or @id="time_start" or @id="time_end" or @id="admin-booking-repeat" or @id="admin-booking-date-end"]') as $node) {
            $this->assertInstanceOf(DOMElement::class, $node);
            $order[] = $node->getAttribute('id');
        }
        $this->assertSame(['date', 'time_start', 'time_end', 'admin-booking-repeat', 'admin-booking-date-end'], $order);
    }

    #[Test]
    public function quantity_is_an_option_tile_and_status_is_not_editable(): void
    {
        $this->getEdit();

        $this->assertSame(1, $this->nodes('//fieldset['.self::cls('ds-options').']//input[@type="radio"][@name="quantity"][@value="4"][@checked]')->length);
        $this->assertSame(1, $this->nodes('//input[@type="radio"][@name="quantity"][@value="2"][@id="admin-booking-quantity-2"][not(@checked)]')->length);
        $this->assertSame(1, $this->nodes('//input[@id="admin-booking-quantity-4"]')->length);
        $this->assertSame(0, $this->nodes('//*[@name="status"]')->length);
        $this->assertSame(0, $this->nodes('//*[@name="status_billing"]')->length);
        $this->assertSame(1, $this->nodes('//fieldset['.self::cls('ds-options').']//input[@type="radio"][@name="edit_scope"][@value="single_occurrence"][@checked]')->length);
        $this->assertSame(3, $this->nodes('//select[@id="admin-booking-repeat"]/option')->length);
    }

    #[Test]
    public function cancel_and_delete_buttons_target_their_forms(): void
    {
        $this->getEdit();

        $this->assertSame(1, $this->nodes('//button[@type="submit"][@form="admin-booking-cancel"]['.self::cls('ds-btn--danger').']')->length);
        $this->assertSame(1, $this->nodes('//button[@type="submit"][@form="admin-booking-delete"]')->length);

        foreach (['admin-booking-cancel', 'admin-booking-delete'] as $id) {
            $form = $this->nodes('//form[@id="'.$id.'"][@onsubmit]')->item(0);
            $this->assertInstanceOf(DOMElement::class, $form, $id.' form with onsubmit');
            $this->assertStringContainsString('confirm(', $form->getAttribute('onsubmit'));
        }

        $scope = $this->nodes('//input[@type="hidden"][@id="admin-booking-cancel-scope"]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $scope);
        $this->assertSame('single_occurrence', $scope->getAttribute('value'));
    }

    #[Test]
    public function full_page_has_breadcrumb_and_header_badges(): void
    {
        $this->getEdit();

        $this->assertSame(1, $this->nodes('//nav['.self::cls('ds-admin-crumbs').']//a[@href="'.route('admin.bookings.index').'"]')->length);

        $meta = $this->nodes('//*['.self::cls('ds-admin-head__meta').']')->item(0);
        $this->assertNotNull($meta);
        $badges = array_map(fn ($n) => $this->text($n), iterator_to_array($this->xpath->query('.//*['.self::cls('ds-badge').']', $meta)));
        $this->assertContains(__('booking.admin.bookings.status_series'), $badges);
        $this->assertStringContainsString('Termin vom 13.07.2026', $this->text($meta));
    }

    #[Test]
    public function popup_edit_keeps_hidden_inputs_and_adds_no_links(): void
    {
        $this->getEdit(['popup' => 1]);

        $this->assertSame(1, $this->nodes('//form[@id="admin-booking-update"]//input[@type="hidden"][@name="popup"][@value="1"]')->length);
        $this->assertSame(1, $this->nodes('//form[@id="admin-booking-update"]//input[@type="hidden"][@name="redirect_to"]')->length);
        foreach (['admin-booking-cancel', 'admin-booking-delete'] as $id) {
            $this->assertSame(1, $this->nodes('//form[@id="'.$id.'"]//input[@type="hidden"][@name="redirect_to"]')->length, $id.' redirect_to');
            $this->assertSame(1, $this->nodes('//form[@id="'.$id.'"]//input[@type="hidden"][@name="occurrence"][@value="2026-07-13"]')->length, $id.' occurrence');
        }
        $this->assertSame(1, $this->nodes('//form[@id="admin-booking-delete"]//input[@type="hidden"][@id="admin-booking-delete-scope"][@value="single_occurrence"]')->length);
        $this->assertSame(1, $this->nodes('//form[@id="admin-booking-cancel"]//input[@type="hidden"][@id="admin-booking-cancel-scope"][@value="single_occurrence"]')->length);
        $this->assertSame(0, $this->nodes('//*['.self::cls('ds-admin-crumbs').']')->length);
        $this->assertSame(0, $this->nodes('//*[@id="admin-nav"]')->length);

        $hrefs = [];
        foreach ($this->nodes('//a[@href]') as $a) {
            $this->assertInstanceOf(DOMElement::class, $a);
            $hrefs[] = $a->getAttribute('href');
        }
        sort($hrefs);
        $expected = [route('admin.bookings.index'), route('admin.users.edit', $this->admin)];
        sort($expected);
        $this->assertSame($expected, $hrefs);
    }

    #[Test]
    public function create_mode_tabs_are_buttons_with_selected_state(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $square = Square::factory()->create(['name' => 'Court A']);
        $url = route('admin.bookings.create', ['popup' => 1, 'sid' => $square->sid, 'date' => '2026-07-13', 'time_start' => '10:00', 'time_end' => '11:00']);

        $this->load($this->actingAs($admin)->get($url)->assertOk()->getContent());

        $tabs = $this->nodes('//div[@role="tablist"]//button[@role="tab"]');
        $this->assertSame(2, $tabs->length);
        foreach ($tabs as $i => $tab) {
            $this->assertInstanceOf(DOMElement::class, $tab);
            $this->assertMatchesRegularExpression('/(^|\s)ds-tabs__tab(\s|$)/', $tab->getAttribute('class'));
            $this->assertSame('button', $tab->getAttribute('type'));
            if ($i === 0) {
                $this->assertSame('true', $tab->getAttribute('aria-selected'));
            }
        }

        $form = '//form[@id="admin-booking-create"]';
        $this->assertSame(1, $this->nodes($form.'//select[@id="sid"]')->length);
        $this->assertSame(1, $this->nodes($form.'//input[@id="booked_for"]')->length);
        $this->assertSame(2, $this->nodes($form.'//fieldset['.self::cls('ds-options').']//input[@type="radio"][@name="quantity"]')->length);
        $this->assertSame(1, $this->nodes($form.'//input[@name="quantity"][@value="2"][@checked]')->length);
        $this->assertSame(0, $this->nodes($form.'//input[@name="status"]')->length);
        $this->assertSame(1, $this->nodes($form.'//input[@type="hidden"][@name="popup"][@value="1"]')->length);
        $this->assertSame(1, $this->nodes('//form[@id="admin-event-create"]')->length);
    }
}
