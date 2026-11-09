<?php

use App\Models\Booking;
use App\Models\Square;
use App\Models\User;
use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;
use Tests\DuskTestCase;

class CalendarBookingRegressionTest extends DuskTestCase
{
    use DatabaseMigrations;

    #[Test]
    public function mobile_booking_reopening_editing_and_cancellation_work_with_normal_keyboard_input(): void
    {
        $court = Square::factory()->create();
        $user = User::factory()->create(['alias' => 'ReviewBookingMember']);
        $date = now()->addDay()->toDateString();
        $this->browse(function (Browser $browser) use ($user, $date): void {
            (new ChromeDevToolsDriver($browser->driver))->execute('Emulation.setDeviceMetricsOverride', ['width' => 390, 'height' => 900, 'deviceScaleFactor' => 1, 'mobile' => true]);
            $browser->loginAs($user)->visit('/calendar?date='.$date)->waitFor('.cc-free')->click('.cc-free')->waitFor('#booking-dialog .ds-modal__panel');
            $browser->scrollIntoView('#booking-mitspieler')->keys('#booking-mitspieler', 'Review Partner A');
            $browser->click('button[form="booking-form"][type="submit"]')->waitForText(__('booking.modal.success_title'));
            $this->assertSame(1, Booking::where('uid', $user->uid)->count());
            $browser->clickLink(__('booking.modal.to_calendar'))->waitForLocation('/calendar')->waitFor('.cc-free')->click('.cc-free')->waitFor('#booking-mitspieler');
            $browser->assertInputValue('#booking-mitspieler', '');
            $browser->click('#booking-dialog .ds-modal__close')->waitUntilMissing('#booking-dialog .ds-modal__panel');
            $browser->click('.cc-own')->waitFor('#cancel-dialog .ds-modal__panel')->click('#cancel-dialog .ds-modal__actions .ds-btn--secondary')->waitFor('#edit-mitspieler');
            $browser->scrollIntoView('#edit-mitspieler')->keys('#edit-mitspieler', ['{control}', 'a'], 'Review Partner B');
            $browser->click('button[form="edit-form"][type="submit"]')->waitForLocation('/calendar')->waitFor('.cc-own');
            $booking = Booking::where('uid', $user->uid)->firstOrFail();
            $this->assertContains('Review Partner B', $booking->fresh()->player_names);
            $browser->click('.cc-own')->waitFor('#cancel-dialog .ds-modal__panel')->click('#cancel-dialog form button[type="submit"]')->waitForLocation('/calendar');
            $browser->assertMissing('.cc-own');
            $this->assertSame('cancelled', $booking->fresh()->status);
        });
    }

    #[Test]
    public function fractional_bookings_and_their_free_gaps_can_be_used_on_a_phone(): void
    {
        $court = Square::factory()->create(['time_block_bookable' => 1800]);
        $otherCourt = Square::factory()->create(['time_block_bookable' => 1800]);
        $user = User::factory()->create(['alias' => 'Half hour member']);
        $other = User::factory()->create(['alias' => 'Other half member']);
        $date = now()->addDay()->toDateString();
        foreach ([[$court, $user, '10:00:00', '10:30:00'], [$court, $other, '10:30:00', '11:00:00'], [$otherCourt, $other, '10:00:00', '10:30:00']] as [$square,$owner,$start,$end]) {
            $booking = Booking::factory()->create(['sid' => $square->sid, 'uid' => $owner->uid, 'status' => 'single', 'visibility' => 'public']);
            $booking->reservations()->create(['date' => $date, 'time_start' => $start, 'time_end' => $end]);
        }
        $this->browse(function (Browser $browser) use ($user, $date, $otherCourt): void {
            $devtools = new ChromeDevToolsDriver($browser->driver);
            $devtools->execute('Emulation.setDeviceMetricsOverride', ['width' => 390, 'height' => 900, 'deviceScaleFactor' => 1, 'mobile' => true]);
            $browser->loginAs($user)->visit('/calendar?date='.$date)->waitFor('.cal-parts--split');
            $selector = '.cal-day[data-day="1"] .cal-row[data-hour="10"] .cal-parts[data-sid="'.$otherCourt->sid.'"] .cc-free';
            $browser->scrollIntoView($selector);
            $metrics = $browser->script("return {overflow: document.documentElement.scrollWidth > innerWidth, heights: [...document.querySelectorAll('.cal-parts--split .calendar-cell')].map(e=>e.getBoundingClientRect().height)}")[0];
            $this->assertFalse($metrics['overflow']);
            foreach ($metrics['heights'] as $height) {
                $this->assertGreaterThanOrEqual(52, $height);
            }
            $browser->driver->takeScreenshot(base_path('tests/Browser/screenshots/calendar-fixed-390.png'));
            $browser->click($selector)->waitFor('#booking-mitspieler');
            $browser->assertInputValue('time_start', '37800')->assertInputValue('time_end', '39600');
            $browser->scrollIntoView('#booking-mitspieler')->keys('#booking-mitspieler', 'Half hour partner');
            $browser->click('button[form="booking-form"][type="submit"]')->waitForText(__('booking.modal.success_title'));
            $this->assertDatabaseHas('bs_reservations', ['date' => $date, 'time_start' => '10:30:00', 'time_end' => '11:00:00']);
            $browser->clickLink(__('booking.modal.to_calendar'))->waitForLocation('/calendar');
            $devtools->execute('Emulation.setDeviceMetricsOverride', ['width' => 1440, 'height' => 1000, 'deviceScaleFactor' => 1, 'mobile' => false]);
            $browser->visit('/calendar?date='.$date)->waitFor('.cal-parts--split');
            $browser->driver->takeScreenshot(base_path('tests/Browser/screenshots/calendar-fixed-1440.png'));
            $this->assertFalse($browser->script('return document.documentElement.scrollWidth > innerWidth')[0]);
            $this->assertLessThan(100, $browser->script('return document.querySelector(\'.cal-day[data-day="1"] .cal-row[data-hour="8"]\').getBoundingClientRect().height')[0]);
            $tops = $browser->script('return [...document.querySelectorAll(\'.cal-row[data-hour="10"]\')].map(e=>e.getBoundingClientRect().top)')[0];
            $this->assertLessThanOrEqual(2, max($tops) - min($tops));
        });
    }
}
