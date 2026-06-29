<?php

declare(strict_types=1);

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\Browser\Support\CreatesTestData;
use Tests\DuskTestCase;

final class CalendarTest extends DuskTestCase
{
    use CreatesTestData;

    public function test_calendar_redirects_guests_to_login(): void
    {
        $this->browse(function (Browser $b): void {
            $b->visit('/calendar')
              ->assertPathIs('/login');
        });
    }

    public function test_calendar_renders_for_logged_in_user(): void
    {
        $user = $this->createTestUser('dusk_cal');

        try {
            $this->browse(function (Browser $b) use ($user): void {
                $b->loginAs($user)
                  ->visit('/calendar')
                  ->assertPresent('table')
                  ->assertSee('Heute');
            });
        } finally {
            $this->deleteTestUser('dusk_cal');
        }
    }

    public function test_clicking_free_slot_opens_booking_modal(): void
    {
        $user = $this->createTestUser('dusk_modal');

        try {
            $this->browse(function (Browser $b) use ($user): void {
                $b->loginAs($user)
                  ->visit('/calendar')
                  ->click('.booking-cell:not(.booking-cell--past):not(.booking-cell--booked)')
                  ->waitFor('.booking-modal', 5)
                  ->assertVisible('.booking-modal');
            });
        } finally {
            $this->deleteTestUser('dusk_modal');
        }
    }

    public function test_date_navigation_changes_url(): void
    {
        $user = $this->createTestUser('dusk_nav');

        try {
            $this->browse(function (Browser $b) use ($user): void {
                $b->loginAs($user)
                  ->visit('/calendar')
                  ->click('[data-nav="next"]')
                  ->waitUntilMissing('[data-nav="next"][disabled]')
                  ->assertQueryStringHas('date');
            });
        } finally {
            $this->deleteTestUser('dusk_nav');
        }
    }
}
