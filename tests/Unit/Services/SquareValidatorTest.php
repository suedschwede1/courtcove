<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Booking;
use App\Models\Option;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use App\Services\SquareValidator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SquareValidatorTest extends TestCase
{
    use RefreshDatabase;

    private SquareValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new SquareValidator;
    }

    #[Test]
    public function disabled_square_blocks_all_bookings(): void
    {
        $square = Square::factory()->create(['status' => 'disabled']);
        $user = User::factory()->create();

        $result = $this->validator->validate(
            $square, $user, 2,
            Carbon::now()->addDay()->setTime(10, 0),
            Carbon::now()->addDay()->setTime(11, 0),
        );

        $this->assertFalse($result->isValid());
        $this->assertStringContainsStringIgnoringCase('disabled', $result->getError());
    }

    #[Test]
    public function disabled_square_allows_privileged_user_like_legacy_system(): void
    {
        $square = Square::factory()->create([
            'status' => 'disabled', 'time_block_bookable_max' => 0, 'range_book' => 0,
        ]);
        $user = User::factory()->create(['status' => 'admin']);

        $result = $this->validator->validate(
            $square, $user, 2,
            Carbon::now()->addDay()->setTime(10, 0),
            Carbon::now()->addDay()->setTime(11, 0),
        );

        $this->assertTrue($result->isValid());
    }

    #[Test]
    public function readonly_square_blocks_booking_without_privilege(): void
    {
        $square = Square::factory()->create(['status' => 'readonly']);
        $user = User::factory()->create(['status' => 'enabled']);

        $result = $this->validator->validate(
            $square, $user, 2,
            Carbon::now()->addDay()->setTime(10, 0),
            Carbon::now()->addDay()->setTime(11, 0),
        );

        $this->assertFalse($result->isValid());
    }

    #[Test]
    public function readonly_square_allows_privileged_user(): void
    {
        $square = Square::factory()->create([
            'status' => 'readonly', 'time_block_bookable_max' => 0, 'range_book' => 0,
        ]);
        $user = User::factory()->create(['status' => 'admin']);

        $result = $this->validator->validate(
            $square, $user, 2,
            Carbon::now()->addDay()->setTime(10, 0),
            Carbon::now()->addDay()->setTime(11, 0),
        );

        $this->assertTrue($result->isValid());
    }

    #[Test]
    public function booking_beyond_range_book_is_rejected(): void
    {
        $square = Square::factory()->create(['status' => 'enabled', 'range_book' => 7 * 86400]);
        $user = User::factory()->create();

        $result = $this->validator->validate(
            $square, $user, 2,
            Carbon::now()->addDays(10)->setTime(10, 0),
            Carbon::now()->addDays(10)->setTime(11, 0),
        );

        $this->assertFalse($result->isValid());
        $this->assertStringContainsStringIgnoringCase('range', $result->getError());
    }

    #[Test]
    public function booking_within_range_book_is_accepted(): void
    {
        $square = Square::factory()->create([
            'status' => 'enabled', 'range_book' => 14 * 86400, 'time_block_bookable_max' => 0,
        ]);
        $user = User::factory()->create();

        $result = $this->validator->validate(
            $square, $user, 2,
            Carbon::now()->addDays(5)->setTime(10, 0),
            Carbon::now()->addDays(5)->setTime(11, 0),
        );

        $this->assertTrue($result->isValid());
    }

    #[Test]
    public function daily_limit_is_enforced(): void
    {
        $square = Square::factory()->create(['status' => 'enabled', 'time_block_bookable_max' => 3600]);
        $otherSquare = Square::factory()->create(['status' => 'enabled']);
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['uid' => $user->uid, 'sid' => $otherSquare->sid, 'status' => 'single']);

        Reservation::factory()->create([
            'bid' => $booking->bid,
            'date' => Carbon::now()->addDays(3)->toDateString(),
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
        ]);

        $result = $this->validator->validate(
            $square, $user, 2,
            Carbon::now()->addDays(3)->setTime(12, 0),
            Carbon::now()->addDays(3)->setTime(13, 0),
        );

        $this->assertFalse($result->isValid());
        $this->assertStringContainsStringIgnoringCase('limit', $result->getError());
    }

    #[Test]
    public function configured_short_term_window_ignores_daily_limit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 10:00:00'));

        $square = Square::factory()->create(['status' => 'enabled', 'time_block_bookable_max' => 3600]);
        $square->setMeta('short-booking-window', (string) (3600));
        $user = User::factory()->create();

        $dateStart = Carbon::now()->addMinutes(45);

        $result = $this->validator->validate(
            $square, $user, 2,
            $dateStart,
            $dateStart->copy()->addHour(),
        );

        Carbon::setTestNow();

        $this->assertTrue($result->isValid());
    }

    #[Test]
    public function configured_short_term_window_ignores_min_range_book(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 10:00:00'));

        $square = Square::factory()->create([
            'status' => 'enabled',
            'min_range_book' => 2 * 3600,
        ]);
        $square->setMeta('short-booking-window', (string) (3600));
        $user = User::factory()->create();

        $dateStart = Carbon::now()->addMinutes(45);

        $result = $this->validator->validate(
            $square, $user, 2,
            $dateStart,
            $dateStart->copy()->addHour(),
        );

        Carbon::setTestNow();

        $this->assertTrue($result->isValid());
    }

    #[Test]
    public function configured_short_term_window_ignores_max_active_bookings(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 10:00:00'));

        $square = Square::factory()->create(['status' => 'enabled', 'max_active_bookings' => 1]);
        $square->setMeta('short-booking-window', (string) (3600));
        $user = User::factory()->create();

        $booking = Booking::factory()->create(['uid' => $user->uid, 'sid' => $square->sid, 'status' => 'single']);
        Reservation::factory()->create([
            'bid' => $booking->bid,
            'date' => Carbon::now()->addDays(1)->toDateString(),
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
        ]);

        $dateStart = Carbon::now()->addMinutes(45);

        $result = $this->validator->validate(
            $square, $user, 2,
            $dateStart,
            $dateStart->copy()->addHour(),
        );

        Carbon::setTestNow();

        $this->assertTrue($result->isValid());
    }

    #[Test]
    public function unlimited_range_book_zero_always_passes(): void
    {
        $square = Square::factory()->create([
            'status' => 'enabled', 'range_book' => 0, 'time_block_bookable_max' => 0,
        ]);
        $user = User::factory()->create();

        $result = $this->validator->validate(
            $square, $user, 2,
            Carbon::now()->addYear()->setTime(10, 0),
            Carbon::now()->addYear()->setTime(11, 0),
        );

        $this->assertTrue($result->isValid());
    }

    // --- Basic validation ---

    #[Test]
    public function end_before_start_is_rejected(): void
    {
        $square = Square::factory()->create();
        $user = User::factory()->create();

        $result = $this->validator->validate(
            $square, $user, 2,
            Carbon::now()->addDay()->setTime(11, 0),
            Carbon::now()->addDay()->setTime(10, 0),
        );

        $this->assertFalse($result->isValid());
    }

    #[Test]
    public function booking_spanning_midnight_is_rejected(): void
    {
        $square = Square::factory()->create(['time_end' => '23:59:59']);
        $user = User::factory()->create();

        $result = $this->validator->validate(
            $square, $user, 2,
            Carbon::now()->addDay()->setTime(23, 0),
            Carbon::now()->addDays(2)->setTime(1, 0),
        );

        $this->assertFalse($result->isValid());
    }

    #[Test]
    public function quantity_less_than_one_is_rejected(): void
    {
        $square = Square::factory()->create();
        $user = User::factory()->create();

        $result = $this->validator->validate(
            $square, $user, 0,
            Carbon::now()->addDay()->setTime(10, 0),
            Carbon::now()->addDay()->setTime(11, 0),
        );

        $this->assertFalse($result->isValid());
    }

    // --- Opening hours ---

    #[Test]
    public function booking_before_opening_time_is_rejected(): void
    {
        $square = Square::factory()->create(['time_start' => '10:00:00', 'time_end' => '22:00:00']);
        $user = User::factory()->create();

        $result = $this->validator->validate(
            $square, $user, 2,
            Carbon::now()->addDay()->setTime(8, 0),
            Carbon::now()->addDay()->setTime(9, 0),
        );

        $this->assertFalse($result->isValid());
        $this->assertStringContainsStringIgnoringCase('opening hours', $result->getError());
    }

    #[Test]
    public function booking_after_closing_time_is_rejected(): void
    {
        $square = Square::factory()->create(['time_start' => '08:00:00', 'time_end' => '20:00:00']);
        $user = User::factory()->create();

        $result = $this->validator->validate(
            $square, $user, 2,
            Carbon::now()->addDay()->setTime(21, 0),
            Carbon::now()->addDay()->setTime(22, 0),
        );

        $this->assertFalse($result->isValid());
        $this->assertStringContainsStringIgnoringCase('opening hours', $result->getError());
    }

    #[Test]
    public function booking_within_opening_hours_is_accepted(): void
    {
        $square = Square::factory()->create([
            'time_start' => '08:00:00', 'time_end' => '22:00:00',
            'time_block_bookable_max' => 0, 'range_book' => 0,
        ]);
        $user = User::factory()->create();

        $result = $this->validator->validate(
            $square, $user, 2,
            Carbon::now()->addDay()->setTime(10, 0),
            Carbon::now()->addDay()->setTime(11, 0),
        );

        $this->assertTrue($result->isValid());
    }

    // --- min_range_book ---

    #[Test]
    public function booking_too_soon_violates_min_range_book(): void
    {
        $square = Square::factory()->create([
            'min_range_book' => 2 * 3600,
        ]);
        $user = User::factory()->create();

        $result = $this->validator->validate(
            $square, $user, 2,
            Carbon::now()->addMinutes(30)->setSeconds(0),
            Carbon::now()->addMinutes(90)->setSeconds(0),
        );

        $this->assertFalse($result->isValid());
    }

    #[Test]
    public function booking_after_min_range_book_is_accepted(): void
    {
        $square = Square::factory()->create([
            'min_range_book' => 2 * 3600,
            'time_block_bookable_max' => 0, 'range_book' => 0,
        ]);
        $user = User::factory()->create();

        $result = $this->validator->validate(
            $square, $user, 2,
            Carbon::now()->addHours(3)->setSeconds(0),
            Carbon::now()->addHours(4)->setSeconds(0),
        );

        $this->assertTrue($result->isValid());
    }

    // --- time_block_bookable (minimum duration) ---

    #[Test]
    public function booking_shorter_than_minimum_duration_is_rejected(): void
    {
        $square = Square::factory()->create(['time_block_bookable' => 3600]);
        $user = User::factory()->create();

        $result = $this->validator->validate(
            $square, $user, 2,
            Carbon::now()->addDay()->setTime(10, 0),
            Carbon::now()->addDay()->setTime(10, 30),
        );

        $this->assertFalse($result->isValid());
        $this->assertSame(__('booking.messages.booking_duration_too_short'), $result->getError());
    }

    #[Test]
    public function booking_equal_to_minimum_duration_is_accepted(): void
    {
        $square = Square::factory()->create([
            'time_block_bookable' => 3600,
            'time_block_bookable_max' => 0, 'range_book' => 0,
        ]);
        $user = User::factory()->create();

        $result = $this->validator->validate(
            $square, $user, 2,
            Carbon::now()->addDay()->setTime(10, 0),
            Carbon::now()->addDay()->setTime(11, 0),
        );

        $this->assertTrue($result->isValid());
    }

    // --- max_active_bookings ---

    #[Test]
    public function max_active_bookings_limit_blocks_new_booking(): void
    {
        $square = Square::factory()->create(['status' => 'enabled', 'max_active_bookings' => 2]);
        $user = User::factory()->create();

        for ($i = 1; $i <= 2; $i++) {
            $booking = Booking::factory()->create(['uid' => $user->uid, 'sid' => $square->sid, 'status' => 'single']);
            Reservation::factory()->create([
                'bid' => $booking->bid,
                'date' => Carbon::now()->addDays($i + 5)->toDateString(),
                'time_start' => '10:00:00',
                'time_end' => '11:00:00',
            ]);
        }

        $result = $this->validator->validate(
            $square, $user, 2,
            Carbon::now()->addDays(10)->setTime(10, 0),
            Carbon::now()->addDays(10)->setTime(11, 0),
        );

        $this->assertFalse($result->isValid());
        $this->assertSame(__('booking.messages.max_active_bookings_reached'), $result->getError());
    }

    #[Test]
    public function max_active_bookings_zero_means_unlimited(): void
    {
        $square = Square::factory()->create([
            'status' => 'enabled', 'max_active_bookings' => 0,
            'time_block_bookable_max' => 0, 'range_book' => 0,
        ]);
        $user = User::factory()->create();

        for ($i = 1; $i <= 5; $i++) {
            $booking = Booking::factory()->create(['uid' => $user->uid, 'sid' => $square->sid, 'status' => 'single']);
            Reservation::factory()->create([
                'bid' => $booking->bid,
                'date' => Carbon::now()->addDays($i + 5)->toDateString(),
                'time_start' => '10:00:00',
                'time_end' => '11:00:00',
            ]);
        }

        $result = $this->validator->validate(
            $square, $user, 2,
            Carbon::now()->addDays(15)->setTime(10, 0),
            Carbon::now()->addDays(15)->setTime(11, 0),
        );

        $this->assertTrue($result->isValid());
    }

    // --- peak_limit ---

    #[Test]
    public function peak_limit_blocks_booking_during_peak_when_limit_reached(): void
    {
        Option::create(['key' => 'peak_limit.enabled', 'value' => '1', 'locale' => null]);

        $square = Square::factory()->create(['max_active_bookings' => 1, 'time_block_bookable_max' => 0, 'range_book' => 0]);
        $square->setMeta('peak_limit_enabled', '1');

        $user = User::factory()->create(['status' => 'enabled']);

        $existingBooking = Booking::factory()->create(['uid' => $user->uid, 'sid' => $square->sid, 'status' => 'single']);
        Reservation::factory()->create([
            'bid' => $existingBooking->bid,
            'date' => Carbon::tomorrow()->toDateString(),
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
        ]);

        $result = $this->validator->validate(
            $square, $user, 2,
            Carbon::now()->addDays(2)->setTime(10, 0),
            Carbon::now()->addDays(2)->setTime(11, 0),
        );

        $this->assertFalse($result->isValid());
        $this->assertSame(__('booking.messages.peak_limit_reached'), $result->getError());
    }

    #[Test]
    public function peak_limit_allows_off_peak_booking_when_peak_limit_reached(): void
    {
        Option::create(['key' => 'peak_limit.enabled', 'value' => '1', 'locale' => null]);

        $square = Square::factory()->create(['max_active_bookings' => 1, 'time_block_bookable_max' => 0, 'range_book' => 0]);
        $square->setMeta('peak_limit_enabled', '1');

        $user = User::factory()->create(['status' => 'enabled']);

        $existingBooking = Booking::factory()->create(['uid' => $user->uid, 'sid' => $square->sid, 'status' => 'single']);
        Reservation::factory()->create([
            'bid' => $existingBooking->bid,
            'date' => Carbon::tomorrow()->toDateString(),
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
        ]);

        $result = $this->validator->validate(
            $square, $user, 2,
            Carbon::now()->addDays(2)->setTime(13, 0),
            Carbon::now()->addDays(2)->setTime(14, 0),
        );

        $this->assertTrue($result->isValid());
    }

    #[Test]
    public function peak_limit_inactive_square_uses_global_limit_as_before(): void
    {
        Option::create(['key' => 'peak_limit.enabled', 'value' => '1', 'locale' => null]);

        $square = Square::factory()->create(['max_active_bookings' => 1, 'time_block_bookable_max' => 0, 'range_book' => 0]);
        // peak_limit_enabled NICHT gesetzt

        $user = User::factory()->create(['status' => 'enabled']);

        $existingBooking = Booking::factory()->create(['uid' => $user->uid, 'sid' => $square->sid, 'status' => 'single']);
        Reservation::factory()->create([
            'bid' => $existingBooking->bid,
            'date' => Carbon::tomorrow()->toDateString(),
            'time_start' => '13:00:00',
            'time_end' => '14:00:00',
        ]);

        $result = $this->validator->validate(
            $square, $user, 2,
            Carbon::now()->addDays(2)->setTime(13, 0),
            Carbon::now()->addDays(2)->setTime(14, 0),
        );

        $this->assertFalse($result->isValid());
    }

    // --- range_book same-day exemption ---

    #[Test]
    public function booking_on_same_day_as_range_book_boundary_is_accepted(): void
    {
        $square = Square::factory()->create([
            'status' => 'enabled',
            'range_book' => 7 * 86400,
            'time_block_bookable_max' => 0,
        ]);
        $user = User::factory()->create();

        $result = $this->validator->validate(
            $square, $user, 2,
            Carbon::now()->addDays(7)->setTime(10, 0),
            Carbon::now()->addDays(7)->setTime(11, 0),
        );

        $this->assertTrue($result->isValid());
    }
}

