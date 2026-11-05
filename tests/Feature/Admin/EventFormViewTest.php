<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
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
 * Pins the restyled event form: date/time order, the booking/event switch and the popup
 * rules (hidden popup inputs, no breadcrumb, no new links inside the calendar iframe).
 */
final class EventFormViewTest extends TestCase
{
    use LazilyRefreshDatabase;

    private DOMXPath $xpath;

    private function load(string $url): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $response = $this->actingAs($admin)->get($url);
        $response->assertOk();

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$response->getContent());
        libxml_clear_errors();
        $this->xpath = new DOMXPath($dom);
    }

    private function nodes(string $query): DOMNodeList
    {
        $nodes = $this->xpath->query($query);
        $this->assertNotFalse($nodes);

        return $nodes;
    }

    /**
     * @return list<string>
     */
    private function mainLinks(): array
    {
        $hrefs = [];
        foreach ($this->nodes('//main//a[@href]') as $a) {
            $this->assertInstanceOf(DOMElement::class, $a);
            $hrefs[] = $a->getAttribute('href');
        }
        sort($hrefs);

        return $hrefs;
    }

    #[Test]
    public function date_and_time_fields_follow_the_design_order_in_a_two_column_grid(): void
    {
        $this->load(route('admin.events.create', ['date_start' => '2026-07-05', 'time_start' => '10:00', 'date_end' => '2026-07-05', 'time_end' => '12:00']));

        $names = [];
        foreach ($this->nodes('//div[contains(@class,"ds-admin-form-grid--2")][.//input[@name="date_start"]]//input') as $input) {
            $this->assertInstanceOf(DOMElement::class, $input);
            $names[] = $input->getAttribute('name');
        }
        $this->assertSame(['date_start', 'time_start', 'date_end', 'time_end'], $names);

        foreach (['date_start', 'time_start', 'date_end', 'time_end', 'capacity'] as $name) {
            $input = $this->nodes('//input[@name="'.$name.'"]')->item(0);
            $this->assertInstanceOf(DOMElement::class, $input);
            $this->assertSame(1, $this->nodes('//label[@for="'.$input->getAttribute('id').'"]')->length, $name.' has a label');
        }
        $this->assertSame(1, $this->nodes('//select[@name="sid"][@id]')->length);
    }

    #[Test]
    public function create_page_switches_to_the_booking_form_through_link_tabs(): void
    {
        $this->load(route('admin.events.create', ['popup' => 1, 'sid' => 3, 'date_start' => '2026-07-05']));

        $tabs = $this->nodes('//nav[contains(@class,"ds-tabs")]/a[contains(@class,"ds-tabs__tab")]');
        $this->assertSame(2, $tabs->length);
        $booking = $tabs->item(0);
        $event = $tabs->item(1);
        $this->assertInstanceOf(DOMElement::class, $booking);
        $this->assertInstanceOf(DOMElement::class, $event);
        $this->assertSame('', $booking->getAttribute('aria-current'));
        $this->assertSame('page', $event->getAttribute('aria-current'));
        $this->assertStringStartsWith(route('admin.bookings.create'), $booking->getAttribute('href'));
        $this->assertStringContainsString('popup=1', $booking->getAttribute('href'));
        $this->assertStringContainsString('popup=1', $event->getAttribute('href'));

        $this->assertSame(1, $this->nodes('//form//input[@type="hidden"][@name="popup"][@value="1"]')->length);
        $this->assertSame(1, $this->nodes('//form//input[@type="hidden"][@name="redirect_to"]')->length);
        $this->assertSame(0, $this->nodes('//nav[contains(@class,"ds-admin-crumbs")]')->length);
    }

    #[Test]
    public function popup_edit_keeps_hidden_inputs_and_adds_no_links(): void
    {
        Square::factory()->create(['name' => 'Court A']);
        $event = Event::factory()->create();
        $this->load(route('admin.events.edit', [$event, 'popup' => 1]));

        $this->assertSame(1, $this->nodes('//form//input[@type="hidden"][@name="popup"][@value="1"]')->length);
        $this->assertSame(1, $this->nodes('//form//input[@type="hidden"][@name="redirect_to"]')->length);
        $this->assertSame(1, $this->nodes('//form//input[@type="hidden"][@name="_method"][@value="PUT"]')->length);
        $this->assertSame(0, $this->nodes('//nav[contains(@class,"ds-admin-crumbs")]')->length);
        $this->assertSame([route('admin.events.index')], $this->mainLinks());
    }

    #[Test]
    public function full_page_edit_has_a_header_with_breadcrumb_and_actions(): void
    {
        $event = Event::factory()->create();
        $this->load(route('admin.events.edit', $event));

        $this->assertSame(1, $this->nodes('//h1[contains(@class,"ds-admin-head__title")]')->length);
        $this->assertSame(1, $this->nodes('//nav[contains(@class,"ds-admin-crumbs")]/a[@href="'.route('admin.events.index').'"]')->length);
        $this->assertSame(1, $this->nodes('//div[contains(@class,"ds-admin-actions")]//button[@type="submit"]')->length);
        $this->assertSame(0, $this->nodes('//input[@type="hidden"][@name="popup"]')->length);
    }
}
