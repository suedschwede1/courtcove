<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StatisticsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function regular_member_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'enabled']))
            ->get('/admin/statistics')
            ->assertForbidden();
    }

    #[Test]
    public function admin_can_view_the_page(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/statistics')
            ->assertOk();
    }

    #[Test]
    public function stats_are_not_shown_until_search_is_submitted(): void
    {
        $user = User::factory()->create(['alias' => 'Nicht Gesucht', 'status' => 'enabled']);

        $response = $this->actingAs($this->admin())->get('/admin/statistics');

        $response->assertOk()->assertDontSee('Nicht Gesucht');
        $this->assertNull($response->viewData('stats'));
    }

    #[Test]
    public function stats_are_shown_after_search_is_submitted(): void
    {
        $user = User::factory()->create(['alias' => 'Gesucht Gefunden', 'status' => 'enabled']);

        $response = $this->actingAs($this->admin())->get('/admin/statistics?search=1');

        $response->assertOk()->assertSee('Gesucht Gefunden');
        $this->assertNotNull($response->viewData('stats'));
    }

    #[Test]
    public function shows_total_single_and_double_counts_per_user_excluding_cancelled(): void
    {
        $user = User::factory()->create(['alias' => 'Heinz Mayer', 'status' => 'enabled']);
        Booking::factory()->count(2)->create(['uid' => $user->uid, 'status' => 'single', 'quantity' => 2]);
        Booking::factory()->create(['uid' => $user->uid, 'status' => 'single', 'quantity' => 4]);
        Booking::factory()->create(['uid' => $user->uid, 'status' => 'cancelled', 'quantity' => 2]);

        $response = $this->actingAs($this->admin())->get('/admin/statistics?search=1');

        $response->assertOk()
            ->assertSeeInOrder(['Heinz Mayer'])
            ->assertSee('3') // total active bookings (2 singles + 1 double, cancelled excluded)
            ->assertSee('2') // single count
            ->assertSee('1'); // double count
    }

    #[Test]
    public function shows_bookings_from_last_calendar_month_via_reservation_dates(): void
    {
        $user = User::factory()->create(['alias' => 'Helga Miglbauer', 'status' => 'enabled']);
        $lastMonthDate = now()->subMonthNoOverflow()->startOfMonth()->addDays(3)->toDateString();
        $thisMonthDate = now()->startOfMonth()->addDays(3)->toDateString();

        $lastMonthBooking = Booking::factory()->create(['uid' => $user->uid, 'status' => 'single']);
        Reservation::factory()->create(['bid' => $lastMonthBooking->bid, 'date' => $lastMonthDate]);

        $thisMonthBooking = Booking::factory()->create(['uid' => $user->uid, 'status' => 'single']);
        Reservation::factory()->create(['bid' => $thisMonthBooking->bid, 'date' => $thisMonthDate]);

        $response = $this->actingAs($this->admin())->get('/admin/statistics?search=1');

        $response->assertOk();
        $rows = $response->viewData('stats');
        $row = $rows->firstWhere('uid', $user->uid);
        $this->assertSame(1, $row['lastMonth']);
    }

    #[Test]
    public function shows_the_most_booked_court_per_user(): void
    {
        $user = User::factory()->create(['alias' => 'Sandra Wenigwieser', 'status' => 'enabled']);
        $courtA = Square::factory()->create(['name' => '1']);
        $courtB = Square::factory()->create(['name' => '2']);

        Booking::factory()->count(2)->create(['uid' => $user->uid, 'sid' => $courtA->sid, 'status' => 'single']);
        Booking::factory()->create(['uid' => $user->uid, 'sid' => $courtB->sid, 'status' => 'single']);

        $response = $this->actingAs($this->admin())->get('/admin/statistics?search=1');

        $row = $response->viewData('stats')->firstWhere('uid', $user->uid);
        $this->assertSame($courtA->display_name, $row['topCourt']);
    }

    #[Test]
    public function shows_cancellation_rate_per_user(): void
    {
        $user = User::factory()->create(['alias' => 'Gerhard Bichlwagner', 'status' => 'enabled']);
        Booking::factory()->create(['uid' => $user->uid, 'status' => 'single']);
        Booking::factory()->create(['uid' => $user->uid, 'status' => 'cancelled']);
        Booking::factory()->create(['uid' => $user->uid, 'status' => 'cancelled']);

        $response = $this->actingAs($this->admin())->get('/admin/statistics?search=1');

        $row = $response->viewData('stats')->firstWhere('uid', $user->uid);
        // 2 cancelled out of 3 total bookings = 66.7%
        $this->assertSame(66.7, $row['cancellationRate']);
        $response->assertSee('66.7');
    }

    #[Test]
    public function shows_club_wide_summary_totals(): void
    {
        $userA = User::factory()->create(['status' => 'enabled']);
        $userB = User::factory()->create(['status' => 'enabled']);
        Booking::factory()->create(['uid' => $userA->uid, 'status' => 'single', 'quantity' => 2]);
        Booking::factory()->create(['uid' => $userB->uid, 'status' => 'single', 'quantity' => 4]);
        Booking::factory()->create(['uid' => $userB->uid, 'status' => 'cancelled', 'quantity' => 2]);

        $response = $this->actingAs($this->admin())->get('/admin/statistics?search=1');

        $summary = $response->viewData('summary');
        $this->assertSame(2, $summary['total']);
        $this->assertSame(1, $summary['single']);
        $this->assertSame(1, $summary['double']);
        $response->assertSee(__('booking.admin.statistics.summary_total'));
    }
}
