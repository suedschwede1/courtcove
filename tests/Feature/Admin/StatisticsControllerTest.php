<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StatisticsControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

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
        $this->assertNotNull($response->viewData('summary'));
    }

    #[Test]
    public function stats_are_shown_after_search_is_submitted(): void
    {
        $user = User::factory()->create(['alias' => 'Gesucht Gefunden', 'status' => 'enabled']);

        $response = $this->actingAs($this->admin())->get('/admin/statistics?searched=1');

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

        $response = $this->actingAs($this->admin())->get('/admin/statistics?searched=1');

        $response->assertOk()
            ->assertSeeInOrder(['Heinz Mayer'])
            ->assertSee('3') // total active bookings (2 singles + 1 double, cancelled excluded)
            ->assertSee('2') // single count
            ->assertSee('1'); // double count
    }

    #[Test]
    public function shows_bookings_from_last_calendar_month_via_reservation_dates(): void
    {
        $user = User::factory()->create(['alias' => 'Helga Muster', 'status' => 'enabled']);
        $lastMonthDate = now()->subMonthNoOverflow()->startOfMonth()->addDays(3)->toDateString();
        $thisMonthDate = now()->startOfMonth()->addDays(3)->toDateString();

        $lastMonthBooking = Booking::factory()->create(['uid' => $user->uid, 'status' => 'single']);
        Reservation::factory()->create(['bid' => $lastMonthBooking->bid, 'date' => $lastMonthDate]);

        $thisMonthBooking = Booking::factory()->create(['uid' => $user->uid, 'status' => 'single']);
        Reservation::factory()->create(['bid' => $thisMonthBooking->bid, 'date' => $thisMonthDate]);

        $response = $this->actingAs($this->admin())->get('/admin/statistics?searched=1');

        $response->assertOk();
        $rows = $response->viewData('stats');
        $row = $rows->firstWhere('uid', $user->uid);
        $this->assertSame(1, $row['lastMonth']);
    }

    #[Test]
    public function shows_the_most_booked_court_per_user(): void
    {
        $user = User::factory()->create(['alias' => 'Sandra Weiss', 'status' => 'enabled']);
        $courtA = Square::factory()->create(['name' => '1']);
        $courtB = Square::factory()->create(['name' => '2']);

        Booking::factory()->count(2)->create(['uid' => $user->uid, 'sid' => $courtA->sid, 'status' => 'single']);
        Booking::factory()->create(['uid' => $user->uid, 'sid' => $courtB->sid, 'status' => 'single']);

        $response = $this->actingAs($this->admin())->get('/admin/statistics?searched=1');

        $row = $response->viewData('stats')->firstWhere('uid', $user->uid);
        $this->assertSame($courtA->display_name, $row['topCourt']);
    }

    #[Test]
    public function shows_cancellation_rate_per_user(): void
    {
        $user = User::factory()->create(['alias' => 'Gerhard Beispiel', 'status' => 'enabled']);
        Booking::factory()->create(['uid' => $user->uid, 'status' => 'single']);
        Booking::factory()->create(['uid' => $user->uid, 'status' => 'cancelled']);
        Booking::factory()->create(['uid' => $user->uid, 'status' => 'cancelled']);

        $response = $this->actingAs($this->admin())->get('/admin/statistics?searched=1');

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

        $response = $this->actingAs($this->admin())->get('/admin/statistics');

        $summary = $response->viewData('summary');
        $this->assertSame(2, $summary['total']);
        $this->assertSame(1, $summary['single']);
        $this->assertSame(1, $summary['double']);
        $response->assertSee(__('booking.admin.statistics.summary_total'));
    }

    #[Test]
    public function the_name_search_filter_narrows_the_results(): void
    {
        User::factory()->create(['alias' => 'Anna Muster', 'status' => 'enabled']);
        User::factory()->create(['alias' => 'Bernd Alt', 'status' => 'enabled']);

        $response = $this->actingAs($this->admin())->get('/admin/statistics?searched=1&search=Anna');

        $response->assertOk()->assertSee('Anna Muster')->assertDontSee('Bernd Alt');
    }

    #[Test]
    public function shows_bookings_in_the_current_year_per_user_and_in_the_summary(): void
    {
        $user = User::factory()->create(['alias' => 'Julia Sommer', 'status' => 'enabled']);
        $booking = Booking::factory()->create(['uid' => $user->uid, 'status' => 'single']);
        Reservation::factory()->create(['bid' => $booking->bid, 'date' => now()->startOfYear()->addDay()->toDateString()]);

        $response = $this->actingAs($this->admin())->get('/admin/statistics?searched=1');

        $row = $response->viewData('stats')->firstWhere('uid', $user->uid);
        $this->assertSame(1, $row['thisYear']);
        $this->assertSame(1, $response->viewData('summary')['thisYear']);
    }

    #[Test]
    public function counts_distinct_members_with_a_booking_this_year_and_this_month(): void
    {
        $bookedThisMonth = User::factory()->create(['status' => 'enabled']);
        $bookingA = Booking::factory()->create(['uid' => $bookedThisMonth->uid, 'status' => 'single']);
        Reservation::factory()->create(['bid' => $bookingA->bid, 'date' => now()->toDateString()]);

        $bookedEarlierThisYear = User::factory()->create(['status' => 'enabled']);
        $bookingB = Booking::factory()->create(['uid' => $bookedEarlierThisYear->uid, 'status' => 'single']);
        Reservation::factory()->create(['bid' => $bookingB->bid, 'date' => now()->startOfYear()->toDateString()]);

        User::factory()->create(['status' => 'enabled']); // no bookings at all

        $response = $this->actingAs($this->admin())->get('/admin/statistics');

        $summary = $response->viewData('summary');
        $this->assertSame(2, $summary['membersThisYear']);
        $this->assertSame(1, $summary['membersThisMonth']);
    }

    /**
     * Counts the queries of the page with 5 and then with 50 members, each with
     * bookings on three courts (one with a meta alias) and reservations.
     *
     * @return array{int, int}
     */
    private function queriesFor5And50Members(string $query): array
    {
        $admin = $this->admin();
        $courts = Square::factory()->count(3)->create();
        $courts[2]->setMeta('alias', 'Halle');

        $addMembers = function (int $count) use ($courts): void {
            User::factory()->count($count)->create(['status' => 'enabled'])->each(function (User $user) use ($courts): void {
                // Court 0: cancelled, court 1: once, court 2 (the hall): twice, so it is the top court.
                foreach ([0, 1, 2, 2] as $index) {
                    $booking = Booking::factory()->create(['uid' => $user->uid, 'sid' => $courts[$index]->sid, 'status' => $index === 0 ? 'cancelled' : 'single']);
                    Reservation::factory()->create(['bid' => $booking->bid]);
                }
            });
        };

        // Session user meta and layout options are read once per request, whatever the page.
        $queries = 0;
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if (! str_contains($query->sql, 'bs_users_meta') && ! str_contains($query->sql, 'bs_options')) {
                $queries++;
            }
        });
        $this->actingAs($admin);

        $addMembers(5);
        $queries = 0;
        $this->get('/admin/statistics'.$query)->assertOk();
        $few = $queries;

        $addMembers(45);
        $queries = 0;
        $this->get('/admin/statistics'.$query)->assertOk();

        return [$few, $queries];
    }

    #[Test]
    public function the_table_costs_the_same_small_number_of_queries_for_5_or_50_members(): void
    {
        [$few, $many] = $this->queriesFor5And50Members('?searched=1');

        $this->assertSame($few, $many);
        $this->assertLessThanOrEqual(5, $many);
    }

    #[Test]
    public function the_summary_alone_costs_one_query_for_5_or_50_members(): void
    {
        [$few, $many] = $this->queriesFor5And50Members('');

        $this->assertSame($few, $many);
        $this->assertSame(1, $many);
    }

    #[Test]
    public function labels_speak_of_booking_users_not_club_members(): void
    {
        User::factory()->create(['alias' => 'Demo Konto', 'status' => 'enabled']);

        $de = $this->actingAs($this->admin())->get('/admin/statistics?searched=1')->assertOk();
        $de->assertSee('Benutzer mit Buchung (Jahr)')
            ->assertSee('Benutzer mit Buchung (Monat)')
            ->assertSee('<th scope="col" data-sort="text" class="ds-admin-sortable">Benutzer</th>', false)
            ->assertSee('data-label="Benutzer"', false)
            ->assertDontSee('Mitglieder mit Buchung')
            ->assertDontSee('>Mitglied<', false);

        $en = $this->withUnencryptedCookie('locale', 'en')->get('/admin/statistics?searched=1')->assertOk();
        $en->assertSee('Users with bookings (year)')
            ->assertSee('Users with bookings (month)')
            ->assertSee('data-label="User"', false)
            ->assertDontSee('Members with a booking')
            ->assertDontSee('>Member<', false);
    }
}
