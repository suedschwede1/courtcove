<?php

declare(strict_types=1);

namespace Tests\Feature\AdminMode;

use App\Models\Booking;
use App\Models\Option;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Booking limits through the member booking dialog (POST /bookings, AJAX like the booking sheet):
 * privileged accounts only bypass them while admin mode is on; without it they are normal members.
 * Covers both storages of the role (users.split_role off: status admin/assist; on: role column).
 */
class BookingLimitsAdminModeTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const DAY = '2026-07-10';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-07-01 10:00:00');
    }

    /** @return array<string, array{bool}> */
    public static function roleStorages(): array
    {
        return ['merged status' => [false], 'split role column' => [true]];
    }

    private function account(string $kind, bool $split): User
    {
        config(['users.split_role' => $split]);

        $user = match ($kind) {
            'member' => User::factory()->create(['status' => 'enabled']),
            'admin' => $split
                ? User::factory()->create(['status' => 'enabled', 'role' => 'admin'])
                : User::factory()->create(['status' => 'admin']),
            'assist' => $split
                ? User::factory()->create(['status' => 'enabled', 'role' => 'assist'])
                : User::factory()->create(['status' => 'assist']),
        };

        if ($kind === 'assist') {
            $user->syncPrivileges(['calendar.create-single-bookings', 'calendar.see-past']);
        }

        return $user->fresh();
    }

    /** @param array<string, mixed> $attributes */
    private function square(array $attributes = []): Square
    {
        return Square::factory()->create(array_merge(['status' => 'enabled'], $attributes));
    }

    private function existingBooking(User $user, Square $square, string $date, string $from, string $to): Booking
    {
        $booking = Booking::factory()->create(['uid' => $user->uid, 'sid' => $square->sid, 'status' => 'single', 'visibility' => 'public']);
        Reservation::factory()->create(['bid' => $booking->bid, 'date' => $date, 'time_start' => $from, 'time_end' => $to]);

        return $booking;
    }

    private function book(Square $square, string $date, int $startHour): TestResponse
    {
        return $this->withHeader('X-Requested-With', 'XMLHttpRequest')->post('/bookings', [
            'sid' => $square->sid,
            'date' => $date,
            'time_start' => $startHour * 3600,
            'time_end' => ($startHour + 1) * 3600,
            'quantity' => 2,
            'mitspieler' => 'Partner Example',
        ]);
    }

    private function assertRejectedWith(TestResponse $response, string $messageKey): void
    {
        $response->assertStatus(422);
        $this->assertSame(__($messageKey), $response->json('error'));
    }

    // --- daily limit (time_block_bookable_max), the reported bug ---------------------------------

    #[Test]
    #[DataProvider('roleStorages')]
    public function the_daily_limit_stops_an_admin_without_admin_mode(bool $split): void
    {
        $admin = $this->account('admin', $split);
        $first = $this->square(['time_block_bookable_max' => 3600]);
        $second = $this->square(['time_block_bookable_max' => 3600]);
        $this->existingBooking($admin, $first, self::DAY, '09:00:00', '10:00:00');

        $this->actingAsWithoutAdminMode($admin);

        $this->assertRejectedWith($this->book($second, self::DAY, 12), 'booking.messages.daily_booking_limit_exceeded');
        $this->assertSame(1, Booking::query()->where('uid', $admin->uid)->count());
    }

    #[Test]
    #[DataProvider('roleStorages')]
    public function the_daily_limit_stops_an_assist_with_booking_privileges_without_admin_mode(bool $split): void
    {
        $assist = $this->account('assist', $split);
        $square = $this->square(['time_block_bookable_max' => 3600]);
        $this->existingBooking($assist, $square, self::DAY, '09:00:00', '10:00:00');

        $this->actingAsWithoutAdminMode($assist);

        $this->assertRejectedWith($this->book($square, self::DAY, 12), 'booking.messages.daily_booking_limit_exceeded');
    }

    #[Test]
    #[DataProvider('roleStorages')]
    public function admin_mode_lifts_the_daily_limit_for_privileged_accounts(bool $split): void
    {
        foreach (['admin', 'assist'] as $kind) {
            $user = $this->account($kind, $split);
            $square = $this->square(['time_block_bookable_max' => 3600]);
            $this->existingBooking($user, $square, self::DAY, '09:00:00', '10:00:00');

            $this->actingAsAdminMode($user)->book($square, self::DAY, 12)->assertOk();
            $this->assertSame(2, Booking::query()->where('uid', $user->uid)->count(), $kind);
        }
    }

    #[Test]
    public function a_member_with_the_admin_mode_cookie_gets_no_exception(): void
    {
        $member = $this->account('member', false);
        $square = $this->square(['time_block_bookable_max' => 3600]);
        $this->existingBooking($member, $square, self::DAY, '09:00:00', '10:00:00');

        $this->actingAsAdminMode($member);

        $this->assertRejectedWith($this->book($square, self::DAY, 12), 'booking.messages.daily_booking_limit_exceeded');
    }

    #[Test]
    public function the_daily_limit_counts_across_squares_and_ignores_cancelled_and_other_days(): void
    {
        $member = $this->account('member', false);
        $limited = $this->square(['time_block_bookable_max' => 7200]);
        $other = $this->square();
        $this->existingBooking($member, $other, self::DAY, '09:00:00', '10:00:00');
        $this->existingBooking($member, $other, '2026-07-11', '09:00:00', '11:00:00');
        $cancelled = $this->existingBooking($member, $other, self::DAY, '15:00:00', '17:00:00');
        $cancelled->update(['status' => 'cancelled']);

        $this->actingAs($member);

        $this->book($limited, self::DAY, 12)->assertOk();
        $this->assertRejectedWith($this->book($limited, self::DAY, 18), 'booking.messages.daily_booking_limit_exceeded');
    }

    #[Test]
    public function a_square_without_daily_limit_does_not_check_it(): void
    {
        $member = $this->account('member', false);
        $square = $this->square(['time_block_bookable_max' => 0]);
        $this->existingBooking($member, $square, self::DAY, '09:00:00', '12:00:00');

        $this->actingAs($member)->book($square, self::DAY, 14)->assertOk();
    }

    #[Test]
    public function editing_the_partner_of_an_own_booking_does_not_count_that_booking_twice(): void
    {
        $member = $this->account('member', false);
        $square = $this->square(['time_block_bookable_max' => 3600]);
        Option::updateOrCreate(['key' => 'booking.max_active_bookings', 'locale' => null], ['value' => '1']);
        $booking = $this->existingBooking($member, $square, self::DAY, '09:00:00', '10:00:00');

        $this->actingAs($member)->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->put('/bookings/'.$booking->bid, ['quantity' => 2, 'mitspieler' => 'New Partner'])
            ->assertOk();
    }

    #[Test]
    public function the_short_booking_window_still_lifts_the_daily_limit_for_everyone_by_design(): void
    {
        $this->travelTo(self::DAY.' 11:30:00');
        $member = $this->account('member', false);
        $square = $this->square(['time_block_bookable_max' => 3600]);
        $square->setMeta('short-booking-window', '3600');
        $this->existingBooking($member, $square, self::DAY, '09:00:00', '10:00:00');

        $this->actingAs($member)->book($square, self::DAY, 12)->assertOk();
    }

    // --- other limits ----------------------------------------------------------------------------

    #[Test]
    #[DataProvider('roleStorages')]
    public function range_book_binds_privileged_accounts_without_admin_mode(bool $split): void
    {
        $admin = $this->account('admin', $split);
        $square = $this->square(['range_book' => 3 * 86400]);

        $this->actingAsWithoutAdminMode($admin);
        $this->book($square, self::DAY, 10)->assertStatus(422);

        $this->actingAsAdminMode($admin)->book($square, self::DAY, 10)->assertOk();
    }

    #[Test]
    #[DataProvider('roleStorages')]
    public function min_range_book_binds_privileged_accounts_without_admin_mode(bool $split): void
    {
        $admin = $this->account('admin', $split);
        $square = $this->square(['min_range_book' => 86400]);

        $this->actingAsWithoutAdminMode($admin);
        $this->assertRejectedWith($this->book($square, '2026-07-01', 14), 'booking.messages.booking_too_early');

        $this->actingAsAdminMode($admin)->book($square, '2026-07-01', 14)->assertOk();
    }

    #[Test]
    #[DataProvider('roleStorages')]
    public function booking_the_past_needs_admin_mode(bool $split): void
    {
        $assist = $this->account('assist', $split);
        $square = $this->square();

        $this->actingAsWithoutAdminMode($assist);
        $this->assertRejectedWith($this->book($square, '2026-07-01', 8), 'booking.messages.booking_in_past');

        $this->actingAsAdminMode($assist)->book($square, '2026-07-01', 8)->assertOk();
    }

    #[Test]
    public function a_readonly_square_needs_admin_mode(): void
    {
        $admin = $this->account('admin', false);
        $square = $this->square(['status' => 'readonly']);

        $this->actingAsWithoutAdminMode($admin);
        $this->book($square, self::DAY, 10)->assertStatus(422);

        $this->actingAsAdminMode($admin)->book($square, self::DAY, 10)->assertOk();
    }

    #[Test]
    public function max_active_bookings_binds_everyone_in_the_member_dialog_also_in_admin_mode(): void
    {
        // Unchanged rule (no privilege bypass in the validator); in admin mode the calendar offers the
        // admin booking form instead, which does not apply member limits.
        $admin = $this->account('admin', false);
        $square = $this->square();
        // The limit is one global option now (Konfiguration > Verhalten), not a per-court field.
        Option::updateOrCreate(['key' => 'booking.max_active_bookings', 'locale' => null], ['value' => '1']);
        $this->existingBooking($admin, $square, '2026-07-05', '09:00:00', '10:00:00');

        $this->actingAsWithoutAdminMode($admin);
        $this->assertRejectedWith($this->book($square, self::DAY, 10), 'booking.messages.max_active_bookings_reached');

        $this->actingAsAdminMode($admin);
        $this->assertRejectedWith($this->book($square, self::DAY, 10), 'booking.messages.max_active_bookings_reached');
    }

    // --- admin booking form ------------------------------------------------------------------------

    #[Test]
    public function the_admin_booking_form_needs_admin_mode(): void
    {
        $admin = $this->account('admin', false);
        $square = $this->square();
        $query = '?sid='.$square->sid.'&date='.self::DAY.'&time_start=10';

        $this->actingAsWithoutAdminMode($admin)->get('/admin/bookings/create'.$query)
            ->assertForbidden()->assertSee('data-admin-mode-required', false);
        $this->actingAsAdminMode($admin)->get('/admin/bookings/create'.$query)->assertOk();
    }

    #[Test]
    public function the_calendar_offers_the_member_dialog_to_an_admin_without_admin_mode(): void
    {
        $admin = $this->account('admin', false);
        $this->square();

        $this->actingAsWithoutAdminMode($admin)->get('/calendar?date='.self::DAY)
            ->assertOk()->assertDontSee('data-action="admin-book"', false);
        $this->actingAsAdminMode($admin)->get('/calendar?date='.self::DAY)
            ->assertOk()->assertSee('data-action="admin-book"', false);
    }
}
