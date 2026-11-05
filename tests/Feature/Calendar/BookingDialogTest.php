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

class BookingDialogTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $html = '';

    private Square $courtA;

    private Square $courtB;

    private function page(): DOMXPath
    {
        $this->courtA = Square::factory()->create(['name' => '1', 'priority' => 1]);
        SquareMeta::create(['sid' => $this->courtA->sid, 'key' => 'alias', 'value' => 'Court A']);
        $this->courtB = Square::factory()->create(['name' => '2', 'priority' => 2]);
        SquareMeta::create(['sid' => $this->courtB->sid, 'key' => 'alias', 'value' => 'Court B']);

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
    public function dialog_has_eyebrow_title_tiles_and_footer(): void
    {
        $x = $this->page();

        $dialog = $this->node($x, '//div[@id="booking-dialog"]');
        $this->assertSame('dialog', $dialog->getAttribute('role'));
        $this->assertSame('booking-dialog-title', $dialog->getAttribute('aria-labelledby'));
        $this->assertSame(__('booking.modal.new_booking'), trim($this->node($x, '//div[@id="booking-dialog"]//p[contains(@class,"ds-modal__eyebrow")]')->textContent));
        $this->node($x, '//h2[@id="booking-dialog-title"]');
        $this->assertStringContainsString('courtAlias', $this->node($x, '//h2[@id="booking-dialog-title"]//*[@x-text]')->getAttribute('x-text'));

        $labels = [];
        foreach ($x->query('//div[@id="booking-dialog"]//dl[contains(@class,"ds-summary")]//dt') as $dt) {
            $labels[] = trim($dt->textContent);
        }
        $this->assertSame([__('booking.calendar.date'), __('booking.calendar.time'), __('booking.calendar.court')], $labels);

        $dismiss = $this->node($x, '//div[@id="booking-dialog"]//button[contains(@class,"ds-btn--secondary")][contains(@class,"ds-modal__dismiss")]');
        $this->assertSame(__('booking.modal.cancel'), trim($dismiss->textContent));
        $this->assertSame('close()', $dismiss->getAttribute('x-on:click'));

        $submit = $this->node($x, '//div[@id="booking-dialog"]//button[@type="submit"][contains(@class,"ds-btn--primary")]');
        $this->assertStringContainsString(__('booking.modal.book_binding'), html_entity_decode($submit->ownerDocument->saveHTML($submit)));
        $phone = $this->node($x, '//div[@id="booking-dialog"]//button[@type="submit"]//span[contains(@class,"ds-only-phone")]');
        $this->assertStringContainsString('timeRange', $phone->getAttribute('x-text'));
    }

    #[Test]
    public function play_type_is_two_option_tiles_bound_to_quantity(): void
    {
        $x = $this->page();

        $legend = $this->node($x, '//div[@id="booking-dialog"]//fieldset[contains(@class,"ds-options")]/legend');
        $this->assertSame(__('booking.modal.play_type'), trim($legend->textContent));

        $radios = $x->query('//div[@id="booking-dialog"]//input[@type="radio"][@name="quantity"]');
        $this->assertSame(2, $radios->length);
        $this->assertSame('2', $radios->item(0)->getAttribute('value'));
        $this->assertSame('4', $radios->item(1)->getAttribute('value'));
        foreach ($radios as $radio) {
            $this->assertSame('quantity', $radio->getAttribute('x-model'));
        }

        $labels = $x->query('//div[@id="booking-dialog"]//label[contains(@class,"ds-option")]');
        $this->assertSame(__('booking.modal.single_tile'), trim($labels->item(0)->textContent));
        $this->assertSame(__('booking.modal.double_tile'), trim($labels->item(1)->textContent));
        $this->assertSame(0, $x->query('//div[@id="booking-dialog"]//select[@name="quantity"]')->length);
    }

    #[Test]
    public function teammate_field_is_labelled_and_suggests_members(): void
    {
        $x = $this->page();

        $label = $this->node($x, '//label[@for="booking-mitspieler"]');
        $this->assertSame(__('booking.admin.bookings.teammates'), trim($label->textContent));

        $input = $this->node($x, '//input[@id="booking-mitspieler"]');
        $this->assertSame('mitspieler', $input->getAttribute('name'));
        $this->assertTrue($input->hasAttribute('required'));
        $this->assertSame('255', $input->getAttribute('maxlength'));
        $this->assertStringContainsString('booking-mitspieler-help', $input->getAttribute('aria-describedby'));

        $this->assertSame(__('booking.modal.player_help'), trim($this->node($x, '//p[@id="booking-mitspieler-help"]')->textContent));

        $this->assertGreaterThan(0, $x->query('//ul[contains(@class,"ds-suggest")]//template[@x-for]')->length);
        $this->assertGreaterThan(0, $x->query('//ul[contains(@class,"ds-suggest")]//template//button[@type="button"][contains(@class,"ds-suggest__item")]')->length);
    }

    #[Test]
    public function form_keeps_the_posted_fields(): void
    {
        $x = $this->page();

        $form = $this->node($x, '//div[@id="booking-dialog"]//form');
        $this->assertSame(route('bookings.store'), $form->getAttribute('action'));
        $this->assertSame('POST', strtoupper($form->getAttribute('method')));
        $this->assertStringContainsString('@submit.prevent="submitBooking($el)"', $this->html);

        foreach (['sid', 'date', 'time_start', 'time_end'] as $name) {
            $hidden = $this->node($x, '//div[@id="booking-dialog"]//form//input[@type="hidden"][@name="'.$name.'"]');
            $this->assertTrue($hidden->hasAttribute('x-bind:value'), "{$name} needs x-bind:value");
        }
        $this->assertGreaterThan(0, $x->query('//div[@id="booking-dialog"]//form//input[@name="_token"]')->length);
    }

    #[Test]
    public function courts_map_uses_alias_and_position(): void
    {
        $this->page();

        $html = str_replace('\\u0022', '"', html_entity_decode($this->html));
        $this->assertStringContainsString('"alias":"Court A"', $html);
        $this->assertStringContainsString('"number":"'.$this->courtA->name.'"', $html);
        $this->assertStringContainsString('"alias":"Court B"', $html);
        $this->assertStringContainsString('"number":"'.$this->courtB->name.'"', $html);
    }

    #[Test]
    public function open_booking_resets_every_field(): void
    {
        $x = $this->page();

        $data = $this->node($x, '//div[@id="booking-dialog"]/..')->getAttribute('x-data');
        foreach (["this.state = 'form'", "this.quantity = '2'", "this.mitspieler = ''", 'this.error = null', 'this.loading = false', 'this.acResults = []', 'this.redirect = null', 'this.quote = null'] as $needle) {
            $this->assertStringContainsString($needle, $data);
        }
    }
}
