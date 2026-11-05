<?php

declare(strict_types=1);

namespace Tests\Browser;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;
use Tests\Browser\Support\CreatesTestData;
use Tests\DuskTestCase;

final class BookingSheetTest extends DuskTestCase
{
    use CreatesTestData;
    use DatabaseMigrations;

    #[Test]
    public function booking_from_the_sheet_shows_the_success_state(): void
    {
        $this->createSquare(alias: 'Court A');
        $user = $this->createTestUser('dusk_sheet');
        $date = Carbon::today()->addDay()->format('Y-m-d');

        try {
            $this->browse(function (Browser $browser) use ($user, $date): void {
                $browser->resize(390, 844)
                    ->loginAs($user)
                    ->visit('/calendar?date='.$date)
                    ->click('.cc-free')
                    ->waitFor('#booking-dialog .ds-modal__panel', 5);

                $this->assertTrue($browser->script('const r = document.querySelector("#booking-dialog .ds-modal__panel").getBoundingClientRect(); return Math.round(r.bottom) === window.innerHeight;')[0]);
                $this->assertTrue($browser->script('return document.documentElement.classList.contains("ds-scroll-lock");')[0]);

                $browser->type('#booking-mitspieler', 'Partner Example')
                    ->press(__('booking.modal.book_binding'))
                    ->waitForText(__('booking.modal.success_title'), 5)
                    ->assertPresent('#booking-dialog a[href$="/my-bookings"]')
                    ->clickLink(__('booking.modal.to_calendar'))
                    ->waitForLocation('/calendar', 5)
                    ->assertPathIs('/calendar')
                    ->assertMissing('#feedback-modal[data-open]');
            });
        } finally {
            $this->deleteTestUser('dusk_sheet');
        }
    }

    #[Test]
    public function reopening_after_success_shows_a_clean_form(): void
    {
        $this->createSquare(alias: 'Court A');
        $user = $this->createTestUser('dusk_reopen');
        $date = Carbon::today()->addDay()->format('Y-m-d');

        try {
            $this->browse(function (Browser $browser) use ($user, $date): void {
                $browser->resize(390, 844)
                    ->loginAs($user)
                    ->visit('/calendar?date='.$date)
                    ->click('.cc-free')
                    ->waitFor('#booking-dialog .ds-modal__panel', 5)
                    ->type('#booking-mitspieler', 'Partner Example')
                    ->press(__('booking.modal.book_binding'))
                    ->waitForText(__('booking.modal.success_title'), 5)
                    ->keys('#booking-dialog', ['{escape}'])
                    ->waitForLocation('/calendar', 5)
                    ->click('.cc-free')
                    ->waitFor('#booking-dialog .ds-modal__panel', 5)
                    ->assertValue('#booking-mitspieler', '')
                    ->assertRadioSelected('quantity', '2')
                    ->assertMissing('#booking-dialog .ds-success');
            });
        } finally {
            $this->deleteTestUser('dusk_reopen');
        }
    }
}
