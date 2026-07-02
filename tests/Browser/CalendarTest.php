<?php

declare(strict_types=1);

namespace Tests\Browser;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;
use Tests\Browser\Support\CreatesTestData;
use Tests\DuskTestCase;

final class CalendarTest extends DuskTestCase
{
    use CreatesTestData;
    use DatabaseMigrations;

    #[Test]
    public function guests_can_view_calendar_and_are_sent_to_login_when_they_pick_a_slot(): void
    {
        $this->createSquare(alias: 'Center Court');
        $date = Carbon::today()->addDay()->format('Y-m-d');

        $this->browse(function (Browser $browser) use ($date): void {
            $browser->visit('/calendar?date='.$date)
                ->assertPathIs('/calendar')
                ->assertPresent('#calendar-grid')
                ->click('.guest-login-cell')
                ->waitForLocation('/login')
                ->assertPathIs('/login');
        });
    }

    #[Test]
    public function calendar_renders_bookable_slots_for_logged_in_users(): void
    {
        $this->createSquare(alias: 'Court One');
        $user = $this->createTestUser('dusk_cal');
        $date = Carbon::today()->addDay()->format('Y-m-d');

        try {
            $this->browse(function (Browser $browser) use ($user, $date): void {
                $browser->loginAs($user)
                    ->visit('/calendar?date='.$date)
                    ->assertPresent('#calendar-grid')
                    ->assertPresent('.cc-free');
            });
        } finally {
            $this->deleteTestUser('dusk_cal');
        }
    }

    #[Test]
    public function clicking_a_free_slot_opens_the_booking_modal(): void
    {
        $this->createSquare(alias: 'Court One');
        $user = $this->createTestUser('dusk_modal');
        $date = Carbon::today()->addDay()->format('Y-m-d');

        try {
            $this->browse(function (Browser $browser) use ($user, $date): void {
                $browser->loginAs($user)
                    ->visit('/calendar?date='.$date)
                    ->click('.cc-free')
                    ->waitFor('.booking-mobile-dialog', 5)
                    ->assertVisible('.booking-mobile-dialog');
            });
        } finally {
            $this->deleteTestUser('dusk_modal');
        }
    }

    #[Test]
    public function date_navigation_changes_the_calendar_query_string(): void
    {
        $this->createSquare(alias: 'Court One');
        $user = $this->createTestUser('dusk_nav');
        $date = Carbon::today()->addDays(2);
        $nextDate = $date->copy()->addDay()->format('Y-m-d');

        try {
            $this->browse(function (Browser $browser) use ($user, $date, $nextDate): void {
                $browser->loginAs($user)
                    ->visit('/calendar?date='.$date->format('Y-m-d'))
                    ->click('#calendar-header-nav a.ui-calendar-nav-btn--arrow:last-of-type')
                    ->waitUntil("window.location.search.includes('date={$nextDate}')")
                    ->assertQueryStringHas('date', $nextDate);
            });
        } finally {
            $this->deleteTestUser('dusk_nav');
        }
    }
}
