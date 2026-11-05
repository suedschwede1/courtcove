<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Booking;
use App\Models\Reservation;
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

    #[Test]
    public function cancel_to_edit_keeps_lock_and_focus(): void
    {
        $square = $this->createSquare(alias: 'Court A');
        $user = $this->createTestUser('dusk_cancel_edit');
        $date = Carbon::today()->addDay();

        $booking = Booking::factory()->create(['uid' => $user->uid, 'sid' => $square->sid, 'status' => 'single', 'quantity' => 2]);
        Reservation::factory()->create(['bid' => $booking->bid, 'date' => $date->toDateString(), 'time_start' => '10:00:00', 'time_end' => '11:00:00']);

        try {
            $this->browse(function (Browser $browser) use ($user, $date): void {
                $browser->resize(390, 844)
                    ->loginAs($user)
                    ->visit('/calendar?date='.$date->format('Y-m-d'))
                    ->click('.cc-own')
                    ->waitFor('#cancel-dialog .ds-modal__panel', 5)
                    ->assertVisible('#cancel-dialog')
                    ->click('#cancel-dialog .ds-modal__actions .ds-btn--secondary')
                    ->waitFor('#edit-dialog .ds-modal__panel', 5)
                    ->assertVisible('#edit-dialog')
                    ->assertMissing('#cancel-dialog[data-open]');

                $this->assertTrue($browser->script('return document.documentElement.classList.contains("ds-scroll-lock");')[0]);
                $this->assertTrue($browser->script('return document.activeElement.closest("#edit-dialog") !== null;')[0]);

                $browser->keys('#edit-dialog', ['{escape}'])
                    ->waitUntilMissing('#edit-dialog .ds-modal__panel', 5);

                $this->assertFalse($browser->script('return document.documentElement.classList.contains("ds-scroll-lock");')[0]);
                $this->assertTrue($browser->script('return document.activeElement.classList.contains("cc-own");')[0]);
            });
        } finally {
            $this->deleteTestUser('dusk_cancel_edit');
        }
    }
}
