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

class CancelEditDialogTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $html = '';

    private function page(): DOMXPath
    {
        $court = Square::factory()->create(['name' => '1', 'priority' => 1]);
        SquareMeta::create(['sid' => $court->sid, 'key' => 'alias', 'value' => 'Court A']);

        $user = User::factory()->create(['status' => 'enabled']);
        $this->html = $this->actingAs($user)->get('/calendar')->assertOk()->getContent();

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
    public function cancel_view_shows_details_then_edit_cancel_and_back(): void
    {
        $x = $this->page();

        $dialog = $this->node($x, '//div[@id="cancel-dialog"]');
        $this->assertSame('dialog', $dialog->getAttribute('role'));
        $this->assertSame(__('booking.modal.cancel_heading'), trim($this->node($x, '//h2[@id="cancel-dialog-title"]')->textContent));

        $summary = $this->node($x, '//div[@id="cancel-dialog"]//dl[contains(@class,"ds-summary")]');
        $binds = [];
        foreach ($x->query('.//dd[@x-text]', $summary) as $dd) {
            $binds[] = $dd->getAttribute('x-text');
        }
        $this->assertSame(['dateLabel', 'timeLabel', 'squareName'], $binds);

        $this->assertStringContainsString(__('booking.modal.cancel_message'), $dialog->textContent);

        $buttons = $x->query('//div[@id="cancel-dialog"]//div[contains(@class,"ds-modal__actions")]//button');
        $this->assertSame(4, $buttons->length);

        $edit = $buttons->item(0);
        $this->assertStringContainsString('ds-btn--secondary', $edit->getAttribute('class'));
        $this->assertSame(__('booking.modal.edit_booking'), trim($edit->textContent));
        $this->assertStringContainsString("\$dispatch('open-edit-booking'", $edit->getAttribute('x-on:click'));

        $form = $this->node($x, '//div[@id="cancel-dialog"]//form[@method="POST"]');
        $this->assertStringContainsString(url('/bookings'), str_replace('\/', '/', $form->getAttribute('x-bind:action')));
        $this->assertSame('DELETE', $this->node($x, '//div[@id="cancel-dialog"]//form//input[@name="_method"]')->getAttribute('value'));
        $danger = $this->node($x, '//div[@id="cancel-dialog"]//form//button[@type="submit"][contains(@class,"ds-btn--danger")]');
        $this->assertSame(__('booking.modal.cancel_booking'), trim($danger->textContent));
        $this->assertTrue($danger->isSameNode($buttons->item(2)));

        $dismiss = $buttons->item(3);
        $this->assertStringContainsString('ds-modal__dismiss', $dismiss->getAttribute('class'));
        $this->assertSame(__('booking.modal.cancel'), trim($dismiss->textContent));
    }

    #[Test]
    public function edit_view_uses_tiles_and_the_teammate_field(): void
    {
        $x = $this->page();

        $this->node($x, '//div[@id="edit-dialog"]');
        $this->assertSame(__('booking.modal.edit_booking_heading'), trim($this->node($x, '//div[@id="edit-dialog"]//p[contains(@class,"ds-modal__eyebrow")]')->textContent));
        $this->assertStringContainsString('squareName', $this->node($x, '//h2[@id="edit-dialog-title"]//*[@x-text]')->getAttribute('x-text'));

        $radios = $x->query('//div[@id="edit-dialog"]//input[@type="radio"][@name="quantity"]');
        $this->assertSame(2, $radios->length);
        foreach ($radios as $radio) {
            $this->assertSame('quantity', $radio->getAttribute('x-model'));
        }
        $this->assertSame(0, $x->query('//div[@id="edit-dialog"]//select[@name="quantity"]')->length);

        $input = $this->node($x, '//div[@id="edit-dialog"]//input[@id="edit-mitspieler"]');
        $this->assertSame('mitspieler', $input->getAttribute('name'));
        $this->assertTrue($input->hasAttribute('required'));

        $this->assertSame('PUT', $this->node($x, '//div[@id="edit-dialog"]//input[@name="_method"]')->getAttribute('value'));
        $form = $this->node($x, '//div[@id="edit-dialog"]//form');
        $this->assertStringContainsString('@submit.prevent="submitEdit($el)"', $this->html);
        $this->assertSame('edit-form', $form->getAttribute('id'));

        $submit = $this->node($x, '//div[@id="edit-dialog"]//button[@type="submit"][contains(@class,"ds-btn--primary")]');
        $this->assertSame($form->getAttribute('id'), $submit->getAttribute('form'));
        $this->assertStringContainsString(__('booking.admin.bookings.save_action'), $submit->ownerDocument->saveHTML($submit));
    }

    #[Test]
    public function calendar_ids_stay_unique_with_all_dialogs(): void
    {
        $x = $this->page();

        $seen = [];
        foreach ($x->query('//*[@id]') as $el) {
            $seen[$el->getAttribute('id')][] = $el->nodeName;
        }
        foreach ($seen as $id => $nodes) {
            $this->assertCount(1, $nodes, "Duplicate id [{$id}]");
        }
    }
}
