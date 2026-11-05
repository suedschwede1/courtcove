<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pins the exact results of the admin statistics page (per-user rows and the
 * club summary) on a fixed date and a data set full of edge cases: month and
 * year boundaries, bookings with two reservations, cancelled bookings, court
 * ties, users without bookings and bookings of inactive users.
 */
class StatisticsCharacterizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $admin;

    private Square $court1;

    private Square $court2;

    private Square $hall;

    private User $anna;

    private User $bernd;

    private User $carla;

    private User $emil;

    protected function setUp(): void
    {
        parent::setUp();

        // January: "last month" is December of the previous year.
        Carbon::setTestNow('2026-01-15 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @param list<string> $dates */
    private function booking(User $user, Square $square, string $status, int $quantity, array $dates = []): Booking
    {
        $booking = Booking::factory()->create([
            'uid' => $user->uid,
            'sid' => $square->sid,
            'status' => $status,
            'quantity' => $quantity,
        ]);

        foreach ($dates as $date) {
            Reservation::factory()->create(['bid' => $booking->bid, 'date' => $date]);
        }

        return $booking;
    }

    private function seedClub(): void
    {
        // Creation order gives court1 the smallest sid.
        $this->court1 = Square::factory()->create(['name' => '1']);
        $this->court2 = Square::factory()->create(['name' => '2']);
        $this->hall = Square::factory()->create(['name' => '3']);
        $this->hall->setMeta('alias', 'Halle');

        $this->admin = User::factory()->create(['alias' => 'Zora Admin', 'status' => 'admin']);
        $this->anna = User::factory()->create(['alias' => 'Anna Alpha', 'status' => 'enabled']);
        $this->emil = User::factory()->create(['alias' => 'Emil Epsilon', 'status' => 'enabled']);
        $this->carla = User::factory()->create(['alias' => 'Carla Gamma', 'status' => 'assist']);
        $this->bernd = User::factory()->create(['alias' => 'Bernd Beta', 'status' => 'admin']);

        // Anna: first booking is on court2 so the court tie (2:2) must be decided by sid.
        $this->booking($this->anna, $this->court2, 'single', 2, ['2025-12-01']);
        $this->booking($this->anna, $this->court1, 'single', 2, ['2025-12-31', '2026-01-01']);
        $this->booking($this->anna, $this->court2, 'subscription', 4, ['2026-01-31', '2026-01-08']);
        $this->booking($this->anna, $this->court1, 'cancelled', 2, ['2026-01-10']);
        $this->booking($this->anna, $this->court1, 'single', 3, ['2025-11-30']);

        // Bernd: one booking next month (this year, not this month), two cancelled.
        $this->booking($this->bernd, $this->hall, 'single', 4, ['2026-02-01']);
        $this->booking($this->bernd, $this->hall, 'cancelled', 2, ['2025-12-10']);
        $this->booking($this->bernd, $this->court1, 'cancelled', 2);

        // Carla: no bookings at all.

        // Emil: last month twice, other years, one booking without reservation.
        $this->booking($this->emil, $this->court2, 'single', 2, ['2025-12-16']);
        $this->booking($this->emil, $this->court1, 'single', 2, ['2025-12-15']);
        $this->booking($this->emil, $this->court1, 'single', 2, ['2024-12-31']);
        $this->booking($this->emil, $this->court2, 'subscription', 4, ['2027-01-01']);
        $this->booking($this->emil, $this->hall, 'single', 1);

        // Inactive accounts: never listed, never counted.
        foreach (['placeholder', 'disabled', 'blocked', 'deleted'] as $status) {
            $inactive = User::factory()->create(['alias' => 'Inaktiv '.$status, 'status' => $status]);
            $this->booking($inactive, $this->court1, 'single', 2, ['2026-01-05', '2025-12-05']);
            $this->booking($inactive, $this->court1, 'cancelled', 4, ['2026-01-06']);
        }
    }

    private function page(string $query = ''): TestResponse
    {
        return $this->actingAs($this->admin)->get('/admin/statistics'.$query)->assertOk();
    }

    /** @return array<string, mixed> */
    private function row(User $user, int $total, int $single, int $double, int $lastMonth, int $thisYear, ?string $topCourt, int $cancelled, float $rate): array
    {
        return [
            'uid' => $user->uid,
            'alias' => $user->alias,
            'total' => $total,
            'single' => $single,
            'double' => $double,
            'lastMonth' => $lastMonth,
            'thisYear' => $thisYear,
            'topCourt' => $topCourt,
            'cancelled' => $cancelled,
            'cancellationRate' => $rate,
        ];
    }

    /** @return array<string, int|float> */
    private function fullSummary(): array
    {
        return [
            'total' => 10,
            'single' => 5,
            'double' => 3,
            'lastMonth' => 4,
            'thisYear' => 3,
            'membersThisYear' => 2,
            'membersThisMonth' => 1,
            'cancellationRate' => 23.1,
        ];
    }

    #[Test]
    public function rows_are_exact_and_ordered_by_alias(): void
    {
        $this->seedClub();

        $stats = $this->page('?searched=1')->viewData('stats');

        $this->assertSame([
            $this->row($this->anna, 4, 2, 1, 2, 2, $this->court1->display_name, 1, 20.0),
            $this->row($this->bernd, 1, 0, 1, 0, 1, 'Halle', 2, 66.7),
            $this->row($this->carla, 0, 0, 0, 0, 0, null, 0, 0.0),
            $this->row($this->emil, 5, 3, 1, 2, 0, $this->court1->display_name, 0, 0.0),
            $this->row($this->admin, 0, 0, 0, 0, 0, null, 0, 0.0),
        ], $stats->all());
    }

    #[Test]
    public function the_summary_is_exact_with_and_without_the_table(): void
    {
        $this->seedClub();

        $this->assertSame($this->fullSummary(), $this->page('?searched=1')->viewData('summary'));
        $this->assertSame($this->fullSummary(), $this->page()->viewData('summary'));
    }

    #[Test]
    public function without_search_submit_no_rows_are_passed_and_the_hint_is_shown(): void
    {
        $this->seedClub();

        $response = $this->page();

        $this->assertFalse($response->viewData('searched'));
        $this->assertNull($response->viewData('stats'));
        $response->assertSee(__('booking.admin.search_hint'))
            ->assertDontSee('Anna Alpha')
            ->assertDontSee('statistics-table"', false);
    }

    #[Test]
    public function the_search_filter_narrows_rows_and_summary(): void
    {
        $this->seedClub();

        $response = $this->page('?searched=1&search=Beta');

        $this->assertSame('Beta', $response->viewData('search'));
        $this->assertSame([
            $this->row($this->bernd, 1, 0, 1, 0, 1, 'Halle', 2, 66.7),
        ], $response->viewData('stats')->all());

        $bernd = [
            'total' => 1,
            'single' => 0,
            'double' => 1,
            'lastMonth' => 0,
            'thisYear' => 1,
            'membersThisYear' => 1,
            'membersThisMonth' => 0,
            'cancellationRate' => 66.7,
        ];
        $this->assertSame($bernd, $response->viewData('summary'));

        // The summary follows the search even when the table is not shown.
        $this->assertSame($bernd, $this->page('?search=Beta')->viewData('summary'));
    }

    #[Test]
    public function the_search_term_is_trimmed_and_matches_inside_the_alias(): void
    {
        $this->seedClub();

        $stats = $this->page('?searched=1&search=%20psil%20')->viewData('stats');

        $this->assertSame([$this->emil->uid], $stats->pluck('uid')->all());
    }

    #[Test]
    public function inactive_accounts_cannot_be_found(): void
    {
        $this->seedClub();

        $response = $this->page('?searched=1&search=Inaktiv');

        $this->assertTrue($response->viewData('stats')->isEmpty());
        $response->assertSee(__('booking.admin.no_results'));
        $this->assertSame([
            'total' => 0,
            'single' => 0,
            'double' => 0,
            'lastMonth' => 0,
            'thisYear' => 0,
            'membersThisYear' => 0,
            'membersThisMonth' => 0,
            'cancellationRate' => 0.0,
        ], $response->viewData('summary'));
    }

    #[Test]
    public function the_rendered_table_formats_rates_and_missing_courts(): void
    {
        $this->seedClub();

        $this->page('?searched=1')
            ->assertSeeInOrder(['Anna Alpha', 'Bernd Beta', 'Carla Gamma', 'Emil Epsilon', 'Zora Admin'])
            ->assertSeeInOrder(['Anna Alpha', $this->court1->display_name, '20.0%', 'Bernd Beta', 'Halle', '66.7%', 'Carla Gamma', '—', '0.0%'])
            ->assertSee('23.1%');
    }

    #[Test]
    public function last_month_uses_the_previous_calendar_month_without_overflow(): void
    {
        Carbon::setTestNow('2026-03-31 08:00:00');
        $court = Square::factory()->create(['name' => '1']);
        $this->admin = User::factory()->create(['alias' => 'Zora Admin', 'status' => 'admin']);
        $user = User::factory()->create(['alias' => 'Marta Monat', 'status' => 'enabled']);

        $this->booking($user, $court, 'single', 2, ['2026-02-28']);
        $this->booking($user, $court, 'single', 2, ['2026-02-01']);
        $this->booking($user, $court, 'single', 2, ['2026-01-31']);
        $this->booking($user, $court, 'single', 2, ['2026-03-01']);
        $this->booking($user, $court, 'single', 2, ['2026-03-31']);

        $response = $this->page('?searched=1&search=Marta');

        $row = $response->viewData('stats')->first();
        $this->assertSame(2, $row['lastMonth']);
        $this->assertSame(5, $row['thisYear']);
        $this->assertSame(1, $response->viewData('summary')['membersThisMonth']);
    }

    #[Test]
    public function a_court_tie_goes_to_the_smaller_sid_whatever_was_booked_first(): void
    {
        $courtA = Square::factory()->create(['name' => '1']);
        $courtB = Square::factory()->create(['name' => '2']);
        $courtC = Square::factory()->create(['name' => '3']);
        $this->admin = User::factory()->create(['alias' => 'Zora Admin', 'status' => 'admin']);
        $user = User::factory()->create(['alias' => 'Tina Tie', 'status' => 'enabled']);

        $this->booking($user, $courtC, 'single', 2);
        $this->booking($user, $courtB, 'single', 2);
        $this->booking($user, $courtC, 'single', 2);
        $this->booking($user, $courtB, 'single', 2);
        $this->booking($user, $courtA, 'single', 2);
        $this->booking($user, $courtA, 'cancelled', 2);
        $this->booking($user, $courtA, 'cancelled', 2);

        $row = $this->page('?searched=1&search=Tina')->viewData('stats')->first();

        $this->assertSame($courtB->display_name, $row['topCourt']);
        $this->assertSame(28.6, $row['cancellationRate']);
    }
}
