<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingPagesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($dom);
    }

    private function courtA(): Square
    {
        $square = Square::factory()->create(['name' => '1']);
        $square->setMeta('alias', 'Court A');

        return $square;
    }

    #[Test]
    public function create_page_summarises_and_posts_the_slot(): void
    {
        $this->travelTo('2026-07-01 10:00');
        $user = User::factory()->create(['status' => 'enabled']);
        $square = $this->courtA();

        $html = $this->actingAs($user)
            ->get(route('bookings.create', ['sid' => $square->sid, 'date' => '2026-07-10', 'time_start' => 10]))
            ->assertOk()
            ->getContent();
        $x = $this->xpath($html);

        $title = __('booking.calendar.book_court', ['court' => 'Court A']);
        $this->assertSame($title, trim($x->query('//*[contains(@class,"ds-card")]//*[contains(@class,"ds-card__title")]')->item(0)->textContent));
        $this->assertSame(3, $x->query('//dl[contains(@class,"ds-summary")]/div[contains(@class,"ds-summary__item")]')->length);

        $form = '//form[@action="'.route('bookings.store').'"]';
        $this->assertSame(1, $x->query($form)->length);
        foreach (['sid' => (string) $square->sid, 'date' => '2026-07-10', 'time_start' => '36000', 'time_end' => '39600', 'quantity' => '2'] as $name => $value) {
            $input = $x->query($form.'//input[@type="hidden"][@name="'.$name.'"]');
            $this->assertSame(1, $input->length, $name);
            $this->assertSame($value, $input->item(0)->getAttribute('value'), $name);
        }
        $this->assertSame(1, $x->query($form.'//input[@id="mitspieler"]')->length);
        $this->assertStringContainsString(__('booking.admin.bookings.teammates'), trim($x->query($form.'//label[@for="mitspieler"]')->item(0)->textContent));
        $this->assertSame(__('booking.modal.complete_booking'), trim($x->query($form.'//button[@type="submit"]')->item(0)->textContent));
        $this->assertSame(1, $x->query('//p[contains(@class,"ds-modal__quote")]')->length);

        $cancel = $x->query('//a[contains(@class,"ds-btn--secondary")][@href="'.route('calendar.index', ['date' => '2026-07-10']).'"]');
        $this->assertSame(1, $cancel->length);
        $this->assertSame(__('booking.modal.cancel'), trim($cancel->item(0)->textContent));
        $this->assertSame(0, $x->query('//*[contains(@class,"ds-card")]//table')->length);
    }

    #[Test]
    public function create_page_shows_booking_errors_as_notice(): void
    {
        $user = User::factory()->create(['status' => 'enabled']);
        $square = $this->courtA();

        $createUrl = route('bookings.create', ['sid' => $square->sid, 'date' => '2026-07-10', 'time_start' => 10]);

        // A rejected booking redirects back to the form with a `booking` error.
        $this->actingAs($user)->from($createUrl)->post(route('bookings.store'), [
            'sid' => $square->sid,
            'date' => '2026-07-10',
            'time_start' => 36000,
            'time_end' => 39600,
            'quantity' => 2,
            'mitspieler' => 'Example Partner',
        ])->assertSessionHasErrors('booking');

        $html = $this->get($createUrl)->assertOk()->getContent();
        $notice = $this->xpath($html)->query('//*[@role="alert"][contains(@class,"ds-notice--problem")]');
        $this->assertSame(1, $notice->length);
        $this->assertNotSame('', trim($notice->item(0)->textContent));
    }

    #[Test]
    public function edit_page_uses_tiles_with_the_current_quantity(): void
    {
        $user = User::factory()->create(['status' => 'enabled']);
        $square = $this->courtA();
        $booking = Booking::factory()->create(['uid' => $user->uid, 'sid' => $square->sid, 'status' => 'single', 'quantity' => 4]);
        Reservation::factory()->create([
            'bid' => $booking->bid,
            'date' => '2026-07-10',
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
        ]);

        $html = $this->actingAs($user)->get(route('bookings.edit', $booking))->assertOk()->getContent();
        $x = $this->xpath($html);

        $form = '//form[@action="'.route('bookings.update', $booking).'"]';
        $this->assertSame(1, $x->query($form)->length);
        $this->assertSame(1, $x->query($form.'//input[@name="_method"][@value="PUT"]')->length);
        $this->assertSame(1, $x->query($form.'//input[@type="radio"][@name="quantity"][@value="4"][@checked]')->length);
        $this->assertSame(1, $x->query($form.'//input[@type="radio"][@name="quantity"][@value="2"][not(@checked)]')->length);
        $this->assertSame(__('booking.admin.common.save'), trim($x->query($form.'//button[@type="submit"]')->item(0)->textContent));
        $this->assertSame(3, $x->query('//dl[contains(@class,"ds-summary")]/div')->length);
        $this->assertStringNotContainsString('data-player-field', $html);
    }
}
