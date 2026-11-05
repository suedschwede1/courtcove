<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Models\Square;
use App\Models\SquareMeta;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminModalsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $html = '';

    private function page(): DOMXPath
    {
        $court = Square::factory()->create(['name' => '1', 'priority' => 1]);
        SquareMeta::create(['sid' => $court->sid, 'key' => 'alias', 'value' => 'Court A']);

        $admin = User::factory()->create(['status' => 'admin']);
        $this->html = $this->actingAs($admin)->withUnencryptedCookie('admin_mode', '1')->get('/calendar')->assertOk()->getContent();

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$this->html);

        return new DOMXPath($dom);
    }

    private function node(DOMXPath $x, string $query): DOMElement
    {
        $nodes = $x->query($query);
        $this->assertGreaterThan(0, $nodes->length, "Missing node: {$query}");

        return $nodes->item(0);
    }

    #[Test]
    public function event_dialog_fields_follow_the_date_time_order(): void
    {
        $x = $this->page();

        $dialog = $this->node($x, '//div[@id="event-dialog"]');
        $this->assertSame('dialog', $dialog->getAttribute('role'));
        $this->assertStringContainsString('ds-modal__panel--lg', $this->node($x, '//div[@id="event-dialog"]/div[contains(@class,"ds-modal__panel")]')->getAttribute('class'));
        $this->assertSame(__('booking.modal.create_event'), trim($this->node($x, '//h2[@id="event-dialog-title"]')->textContent));

        $form = $this->node($x, '//div[@id="event-dialog"]//form');
        $this->assertSame(route('admin.events.store'), $form->getAttribute('action'));

        $ids = [];
        foreach ($x->query('//div[@id="event-dialog"]//form//*[self::input or self::select or self::textarea][@id]') as $field) {
            $ids[] = $field->getAttribute('id');
        }
        $this->assertSame(['event-name', 'event-date-start', 'event-time-start', 'event-date-end', 'event-time-end', 'event-sid', 'event-description'], $ids);
        foreach ($ids as $id) {
            $this->node($x, '//div[@id="event-dialog"]//label[@for="'.$id.'"]');
        }

        foreach (['event-date-start' => 'dateStartVal', 'event-time-start' => 'timeStartVal', 'event-date-end' => 'dateEndVal', 'event-time-end' => 'timeEndVal'] as $id => $model) {
            $this->assertSame($model, $this->node($x, '//input[@id="'.$id.'"]')->getAttribute('x-model'));
        }
        $this->assertSame('sid', $this->node($x, '//select[@id="event-sid"]')->getAttribute('x-model'));

        $this->assertSame('enabled', $this->node($x, '//div[@id="event-dialog"]//input[@name="status"][@type="hidden"]')->getAttribute('value'));
        $this->assertSame(url('/'), $this->node($x, '//div[@id="event-dialog"]//input[@name="redirect_to"][@type="hidden"]')->getAttribute('value'));

        $submit = $this->node($x, '//div[@id="event-dialog"]//button[@type="submit"]');
        $this->assertSame(__('booking.modal.save_event'), trim($submit->textContent));

        $block = $dialog->ownerDocument->saveHTML($dialog);
        $this->assertStringNotContainsString('focus:ring-', $block);
        $this->assertStringNotContainsString('#bf4316', $block);
    }

    #[Test]
    public function admin_frame_is_a_labelled_modal_driven_by_an_event(): void
    {
        $x = $this->page();

        $modal = $this->node($x, '//div[@id="admin-booking-modal"]');
        $this->assertSame('dialog', $modal->getAttribute('role'));
        $this->assertSame(__('booking.modal.admin_form'), $modal->getAttribute('aria-label'));
        $this->assertStringContainsString('ds-modal__panel--frame', $this->node($x, '//div[@id="admin-booking-modal"]/div[contains(@class,"ds-modal__panel")]')->getAttribute('class'));

        $this->node($x, '//div[@id="admin-booking-modal"]/parent::div[@x-data]');
        $this->assertStringContainsString('@open-admin-frame.window=', $this->html);

        $iframe = $this->node($x, '//div[@id="admin-booking-modal"]//iframe[@id="abm-iframe"]');
        $this->assertSame('src', $iframe->getAttribute('x-bind:src'));
        $this->assertStringContainsString('ds-modal__frame', $iframe->getAttribute('class'));

        $this->assertStringContainsString("src = 'about:blank'", html_entity_decode($this->html));
    }

    #[Test]
    public function booking_js_only_dispatches_frame_events(): void
    {
        $js = (string) file_get_contents(public_path('js/booking.js'));

        $this->assertStringContainsString("new CustomEvent('open-admin-frame'", $js);
        $this->assertStringContainsString('abm-iframe', $js);
        $this->assertStringContainsString('.booking-trigger', $js);
        $this->assertStringNotContainsString('style.display', $js);
        $this->assertStringNotContainsString('booking-modal__viewport', $js);
    }
}
