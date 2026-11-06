<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Option;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use App\Services\PeakLimitService;
use App\Services\SquareValidator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Peak-time limit (club decision 2026-11-06): switch, windows and limit are global options under
 * Administration > Konfiguration > Verhalten; a court only switches the limit on for itself. The limit is
 * `peak_limit.max`, falling back to the global `booking.max_active_bookings` when empty/0; both 0 = no limit.
 * Counted across all courts: future active bookings of the person that START inside a peak window.
 */
class PeakLimitBehaviorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const DAY = '2026-07-10';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-07-01 10:00:00');
    }

    private function option(string $key, string $value): void
    {
        Option::updateOrCreate(['key' => $key, 'locale' => null], ['value' => $value]);
    }

    /** Global peak limit on with the default windows 08-12 and 17-21. */
    private function peakOn(?string $max = null, string $maxActive = '0'): void
    {
        $this->option('peak_limit.enabled', '1');
        $this->option('booking.max_active_bookings', $maxActive);
        if ($max !== null) {
            $this->option('peak_limit.max', $max);
        }
    }

    private function court(bool $peakSwitch = true): Square
    {
        $square = Square::factory()->create(['status' => 'enabled', 'time_start' => '07:00:00', 'time_end' => '22:00:00']);
        $square->setMeta('peak_limit_enabled', $peakSwitch ? '1' : '0');

        return $square;
    }

    private function booking(User $user, Square $square, string $date, string $from, string $to, string $status = 'single'): void
    {
        $booking = Booking::factory()->create(['uid' => $user->uid, 'sid' => $square->sid, 'status' => $status, 'visibility' => 'public']);
        Reservation::factory()->create(['bid' => $booking->bid, 'date' => $date, 'time_start' => $from, 'time_end' => $to]);
    }

    private function validate(Square $square, User $user, int $hour, string $date = self::DAY): ?string
    {
        $result = app(SquareValidator::class)->validate(
            $square, $user, 2,
            Carbon::parse($date)->setTime($hour, 0),
            Carbon::parse($date)->setTime($hour + 1, 0),
        );

        return $result->isValid() ? null : $result->getError();
    }

    private function member(): User
    {
        return User::factory()->create(['status' => 'enabled']);
    }

    // --- PeakLimitService ---------------------------------------------------------------------------

    #[Test]
    public function the_limit_is_peak_limit_max_with_the_global_max_active_value_as_fallback(): void
    {
        $service = new PeakLimitService;
        $this->assertSame(0, $service->limit(), 'nothing configured = no limit');

        $this->option('booking.max_active_bookings', '5');
        $this->assertSame(5, $service->limit(), 'empty peak_limit.max falls back');

        $this->option('peak_limit.max', '0');
        $this->assertSame(5, $service->limit(), '0 falls back');

        $this->option('peak_limit.max', '2');
        $this->assertSame(2, $service->limit());

        $this->option('booking.max_active_bookings', '0');
        $this->assertSame(2, $service->limit(), 'own value without a global max');
    }

    #[Test]
    public function a_court_is_affected_only_with_its_switch_and_the_global_switch(): void
    {
        $service = new PeakLimitService;
        $on = $this->court(true);
        $off = $this->court(false);

        $this->assertFalse($service->appliesTo($on), 'global switch off');

        $this->option('peak_limit.enabled', '1');
        $this->assertTrue($service->appliesTo($on));
        $this->assertFalse($service->appliesTo($off));
    }

    // --- SquareValidator ----------------------------------------------------------------------------

    #[Test]
    public function the_own_peak_limit_applies_without_a_global_max_active_value(): void
    {
        $this->peakOn('2', '0');
        $court = $this->court();
        $other = $this->court(false);
        $user = $this->member();
        $this->booking($user, $court, '2026-07-05', '09:00:00', '10:00:00');

        $this->assertNull($this->validate($court, $user, 18), 'one peak booking, limit 2');

        // The second peak booking may be on any court (counted across all courts).
        $this->booking($user, $other, '2026-07-06', '18:00:00', '19:00:00');
        $this->assertSame(__('booking.messages.peak_limit_reached'), $this->validate($court, $user, 18));
    }

    #[Test]
    public function the_fallback_to_the_global_max_active_value_keeps_existing_data_unchanged(): void
    {
        $this->peakOn(null, '1');
        $court = $this->court();
        $user = $this->member();
        $this->booking($user, $court, '2026-07-05', '10:00:00', '11:00:00');

        $this->assertSame(__('booking.messages.peak_limit_reached'), $this->validate($court, $user, 9));
    }

    #[Test]
    public function both_values_zero_means_no_peak_limit(): void
    {
        $this->peakOn('0', '0');
        $court = $this->court();
        $user = $this->member();
        foreach (['2026-07-03', '2026-07-04', '2026-07-05'] as $date) {
            $this->booking($user, $court, $date, '09:00:00', '10:00:00');
        }

        $this->assertNull($this->validate($court, $user, 9));
    }

    #[Test]
    public function only_bookings_that_start_inside_a_window_count(): void
    {
        $this->peakOn('1', '0');
        $court = $this->court();
        $user = $this->member();
        // 12:00 is the end of window 1 (exclusive), 16:00 is before window 2, 21:00 is its end.
        $this->booking($user, $court, '2026-07-05', '12:00:00', '13:00:00');
        $this->booking($user, $court, '2026-07-05', '16:00:00', '17:00:00');
        $this->booking($user, $court, '2026-07-05', '21:00:00', '22:00:00');
        // Past and cancelled peak bookings do not count either.
        $this->booking($user, $court, '2026-06-30', '09:00:00', '10:00:00');
        $this->booking($user, $court, '2026-07-06', '09:00:00', '10:00:00', 'cancelled');

        $this->assertNull($this->validate($court, $user, 8), '08:00 starts window 1');

        $this->booking($user, $court, '2026-07-06', '20:00:00', '21:00:00');
        $this->assertSame(__('booking.messages.peak_limit_reached'), $this->validate($court, $user, 8));
    }

    #[Test]
    public function a_new_booking_outside_the_windows_is_not_checked(): void
    {
        $this->peakOn('1', '0');
        $court = $this->court();
        $user = $this->member();
        $this->booking($user, $court, '2026-07-05', '09:00:00', '10:00:00');

        $this->assertNull($this->validate($court, $user, 12), '12:00 is outside (window 1 ends at 12:00)');
        $this->assertNull($this->validate($court, $user, 16));
        $this->assertSame(__('booking.messages.peak_limit_reached'), $this->validate($court, $user, 11), '11:00 is inside');
    }

    #[Test]
    public function a_court_without_its_switch_uses_the_global_max_active_rule(): void
    {
        $this->peakOn('1', '3');
        $court = $this->court(false);
        $user = $this->member();
        $this->booking($user, $court, '2026-07-05', '09:00:00', '10:00:00');

        $this->assertNull($this->validate($court, $user, 9), 'peak limit 1 is not applied on this court');

        $this->booking($user, $court, '2026-07-06', '09:00:00', '10:00:00');
        $this->booking($user, $court, '2026-07-07', '09:00:00', '10:00:00');
        $this->assertSame(__('booking.messages.max_active_bookings_reached'), $this->validate($court, $user, 9));
    }

    #[Test]
    public function with_the_global_switch_off_the_peak_limit_never_applies(): void
    {
        $this->option('peak_limit.enabled', '0');
        $this->option('peak_limit.max', '1');
        $court = $this->court(true);
        $user = $this->member();
        $this->booking($user, $court, '2026-07-05', '09:00:00', '10:00:00');

        $this->assertNull($this->validate($court, $user, 9));
    }

    #[Test]
    public function changed_windows_are_used_for_the_check_and_the_count(): void
    {
        $this->peakOn('1', '0');
        $this->option('peak_limit.window_1_start', '06:30');
        $this->option('peak_limit.window_1_end', '07:30');
        $this->option('peak_limit.window_2_start', '19:00');
        $this->option('peak_limit.window_2_end', '20:00');
        $court = $this->court();
        $user = $this->member();
        $this->booking($user, $court, '2026-07-05', '09:00:00', '10:00:00');

        $this->assertNull($this->validate($court, $user, 19), '09:00 is no longer a peak start');

        $this->booking($user, $court, '2026-07-06', '07:00:00', '08:00:00');
        $this->assertSame(__('booking.messages.peak_limit_reached'), $this->validate($court, $user, 19));
    }

    #[Test]
    public function the_short_booking_window_still_lifts_the_peak_limit(): void
    {
        $this->travelTo(self::DAY.' 17:50:00');
        $this->peakOn('1', '0');
        $court = $this->court();
        $court->setMeta('short-booking-window', '900');
        $user = $this->member();
        $this->booking($user, $court, '2026-07-12', '09:00:00', '10:00:00');

        $this->assertNull($this->validate($court, $user, 18));
    }

    // --- admin mode (HTTP, booking dialog) ----------------------------------------------------------

    private function book(Square $square, int $hour): TestResponse
    {
        return $this->withHeader('X-Requested-With', 'XMLHttpRequest')->post('/bookings', [
            'sid' => $square->sid, 'date' => self::DAY,
            'time_start' => $hour * 3600, 'time_end' => ($hour + 1) * 3600,
            'quantity' => 2, 'mitspieler' => 'Partner Example',
        ]);
    }

    #[Test]
    public function privileged_accounts_only_bypass_the_peak_limit_in_admin_mode(): void
    {
        $this->peakOn('1', '0');
        $court = $this->court();
        $admin = User::factory()->create(['status' => 'admin']);
        $this->booking($admin, $court, '2026-07-05', '09:00:00', '10:00:00');

        $this->actingAsWithoutAdminMode($admin);
        $response = $this->book($court, 18)->assertStatus(422);
        $this->assertSame(__('booking.messages.peak_limit_reached'), $response->json('error'));

        $this->actingAsAdminMode($admin)->book($court, 18)->assertOk();
    }

    #[Test]
    public function a_member_with_the_admin_mode_cookie_is_still_limited(): void
    {
        $this->peakOn('1', '0');
        $court = $this->court();
        $member = $this->member();
        $this->booking($member, $court, '2026-07-05', '09:00:00', '10:00:00');

        $this->actingAsAdminMode($member)->book($court, 18)->assertStatus(422);
    }

    // --- outside the windows: the global max-active limit applies on peak courts too -----------------

    #[Test]
    public function outside_the_windows_the_global_limit_applies_on_a_court_with_the_peak_switch(): void
    {
        $this->peakOn('5', '2');
        $court = $this->court();
        $user = $this->member();
        $this->booking($user, $court, '2026-07-05', '13:00:00', '14:00:00');
        $this->booking($user, $court, '2026-07-06', '13:00:00', '14:00:00');

        $this->assertSame(__('booking.messages.max_active_bookings_reached'), $this->validate($court, $user, 13), '12:00-17:00 is outside the windows');
        $this->assertSame(__('booking.messages.max_active_bookings_reached'), $this->validate($court, $user, 16));
    }

    #[Test]
    public function outside_the_windows_below_the_global_limit_is_allowed(): void
    {
        $this->peakOn('5', '3');
        $court = $this->court();
        $user = $this->member();
        $this->booking($user, $court, '2026-07-05', '13:00:00', '14:00:00');
        $this->booking($user, $court, '2026-07-06', '13:00:00', '14:00:00');

        $this->assertNull($this->validate($court, $user, 13));
    }

    #[Test]
    public function inside_the_windows_both_limits_apply_and_the_stricter_one_wins(): void
    {
        // Peak limit stricter (1) than the global limit (3): peak message.
        $this->peakOn('1', '3');
        $court = $this->court();
        $user = $this->member();
        $this->booking($user, $court, '2026-07-05', '09:00:00', '10:00:00');
        $this->assertSame(__('booking.messages.peak_limit_reached'), $this->validate($court, $user, 18));

        // Global limit stricter (2, reached by two non-peak bookings) than the peak limit (5): global message.
        $this->option('peak_limit.max', '5');
        $this->option('booking.max_active_bookings', '2');
        $other = $this->member();
        $this->booking($other, $court, '2026-07-05', '13:00:00', '14:00:00');
        $this->booking($other, $court, '2026-07-06', '13:00:00', '14:00:00');
        $this->assertSame(__('booking.messages.max_active_bookings_reached'), $this->validate($court, $other, 18));
    }

    #[Test]
    public function a_global_limit_of_zero_leaves_only_the_peak_rule_inside_the_windows(): void
    {
        $this->peakOn('1', '0');
        $court = $this->court();
        $user = $this->member();
        foreach (['2026-07-03', '2026-07-04', '2026-07-05'] as $date) {
            $this->booking($user, $court, $date, '13:00:00', '14:00:00');
        }

        $this->assertNull($this->validate($court, $user, 13), 'no global limit, outside the windows');
        $this->assertNull($this->validate($court, $user, 18), 'no peak booking yet');
    }

    #[Test]
    public function the_short_booking_window_lifts_both_limits(): void
    {
        $this->travelTo(self::DAY.' 17:50:00');
        $this->peakOn('1', '1');
        $this->option('booking.short_booking_window', '900');
        $court = $this->court();
        $user = $this->member();
        $this->booking($user, $court, '2026-07-12', '09:00:00', '10:00:00');

        $this->assertNull($this->validate($court, $user, 18), 'inside a window and above both limits');

        $this->travelTo(self::DAY.' 14:50:00');
        $this->assertNull($this->validate($court, $user, 15), 'outside the windows');
    }

    #[Test]
    public function admin_mode_bypasses_the_peak_rule_but_not_the_global_max_active_limit(): void
    {
        $this->peakOn('1', '2');
        $court = $this->court();
        $admin = User::factory()->create(['status' => 'admin']);
        $this->booking($admin, $court, '2026-07-05', '09:00:00', '10:00:00');

        // Peak limit (1) reached, global (2) not: admin mode passes inside a window.
        $this->actingAsAdminMode($admin)->book($court, 18)->assertOk();

        // Now two active bookings: the global limit stops the admin in admin mode, too.
        $response = $this->actingAsAdminMode($admin)->book($court, 14)->assertStatus(422);
        $this->assertSame(__('booking.messages.max_active_bookings_reached'), $response->json('error'));
    }
}
