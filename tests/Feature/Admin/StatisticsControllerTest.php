<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

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
    public function shows_total_single_and_double_counts_per_user_excluding_cancelled(): void
    {
        $user = User::factory()->create(['alias' => 'Heinz Mayer', 'status' => 'enabled']);
        \App\Models\Booking::factory()->count(2)->create(['uid' => $user->uid, 'status' => 'single', 'quantity' => 2]);
        \App\Models\Booking::factory()->create(['uid' => $user->uid, 'status' => 'single', 'quantity' => 4]);
        \App\Models\Booking::factory()->create(['uid' => $user->uid, 'status' => 'cancelled', 'quantity' => 2]);

        $response = $this->actingAs($this->admin())->get('/admin/statistics');

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

        $lastMonthBooking = \App\Models\Booking::factory()->create(['uid' => $user->uid, 'status' => 'single']);
        \App\Models\Reservation::factory()->create(['bid' => $lastMonthBooking->bid, 'date' => $lastMonthDate]);

        $thisMonthBooking = \App\Models\Booking::factory()->create(['uid' => $user->uid, 'status' => 'single']);
        \App\Models\Reservation::factory()->create(['bid' => $thisMonthBooking->bid, 'date' => $thisMonthDate]);

        $response = $this->actingAs($this->admin())->get('/admin/statistics');

        $response->assertOk();
        $rows = $response->viewData('stats');
        $row = $rows->firstWhere('uid', $user->uid);
        $this->assertSame(1, $row['lastMonth']);
    }
}
