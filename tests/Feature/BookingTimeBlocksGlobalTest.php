<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Option;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use App\Services\BookingRules;
use App\Services\BookingService;
use App\Services\SquareValidator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * "Zeitblock min. buchbar" / "Zeitblock max. buchbar" are global options (seconds, shown in minutes under
 * Verhalten). Read order: the option if set, else the court column (data from before the switch).
 */
class BookingTimeBlocksGlobalTest extends TestCase
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

    /** @param array<string, mixed> $attributes */
    private function court(array $attributes = []): Square
    {
        return Square::factory()->create(array_merge(['status' => 'enabled', 'time_start' => '07:00:00', 'time_end' => '22:00:00'], $attributes));
    }

    private function validate(Square $square, User $user, string $from, string $to): ?string
    {
        $result = app(SquareValidator::class)->validate(
            $square, $user, 2, Carbon::parse(self::DAY.' '.$from), Carbon::parse(self::DAY.' '.$to),
        );

        return $result->isValid() ? null : $result->getError();
    }

    private function booking(User $user, Square $square, string $from, string $to): void
    {
        $booking = Booking::factory()->create(['uid' => $user->uid, 'sid' => $square->sid, 'status' => 'single', 'visibility' => 'public']);
        Reservation::factory()->create(['bid' => $booking->bid, 'date' => self::DAY, 'time_start' => $from, 'time_end' => $to]);
    }

    // --- read order ---------------------------------------------------------------------------------

    #[Test]
    public function without_the_option_the_court_value_applies(): void
    {
        $court = $this->court(['time_block_bookable' => 2700, 'time_block_bookable_max' => 5400]);
        $rules = app(BookingRules::class);

        $this->assertSame(2700, $rules->minimumSeconds($court));
        $this->assertSame(5400, $rules->dailyMaxSeconds($court));
    }

    #[Test]
    public function the_global_option_overrides_every_court_value(): void
    {
        $this->option(BookingRules::TIME_BLOCK_MIN, '1800');
        $this->option(BookingRules::TIME_BLOCK_MAX, '0');
        $court = $this->court(['time_block_bookable' => 3600, 'time_block_bookable_max' => 7200]);
        $rules = app(BookingRules::class);

        $this->assertSame(1800, $rules->minimumSeconds($court));
        $this->assertSame(0, $rules->dailyMaxSeconds($court), 'an explicit 0 (= unlimited) also overrides');
    }

    // --- minimum duration ---------------------------------------------------------------------------

    #[Test]
    public function the_minimum_duration_comes_from_the_option(): void
    {
        $court = $this->court(['time_block_bookable' => 3600]);
        $user = User::factory()->create(['status' => 'enabled']);

        $this->assertSame(__('booking.messages.booking_duration_too_short'), $this->validate($court, $user, '10:00', '10:30'), 'court fallback 60 min');

        $this->option(BookingRules::TIME_BLOCK_MIN, '1800');
        $this->assertNull($this->validate($court, $user, '10:00', '10:30'));

        $this->option(BookingRules::TIME_BLOCK_MIN, '5400');
        $this->assertSame(__('booking.messages.booking_duration_too_short'), $this->validate($court, $user, '10:00', '11:00'));
    }

    #[Test]
    public function a_short_member_booking_is_extended_to_the_global_minimum(): void
    {
        $this->option(BookingRules::TIME_BLOCK_MIN, '5400');
        $court = $this->court(['time_block_bookable' => 1800]);
        $user = User::factory()->create(['status' => 'enabled']);

        $booking = app(BookingService::class)->createSingle($user, $court, 2, Carbon::parse(self::DAY.' 10:00'), Carbon::parse(self::DAY.' 11:00'));

        $this->assertSame('11:30:00', (string) $booking->reservations->first()->time_end);
    }

    // --- daily limit ----------------------------------------------------------------------------------

    #[Test]
    public function one_global_daily_limit_counts_all_courts_together(): void
    {
        $this->option(BookingRules::TIME_BLOCK_MAX, '3600');
        $unlimitedCourt = $this->court(['time_block_bookable_max' => 0]);
        $generousCourt = $this->court(['time_block_bookable_max' => 14400]);
        $user = User::factory()->create(['status' => 'enabled']);
        $this->booking($user, $unlimitedCourt, '09:00:00', '10:00:00');

        $message = __('booking.messages.daily_booking_limit_exceeded');
        $this->assertSame($message, $this->validate($generousCourt, $user, '12:00', '13:00'));
        $this->assertSame($message, $this->validate($unlimitedCourt, $user, '12:00', '13:00'));
    }

    #[Test]
    public function the_daily_limit_falls_back_to_the_court_value(): void
    {
        $court = $this->court(['time_block_bookable_max' => 7200]);
        $user = User::factory()->create(['status' => 'enabled']);
        $this->booking($user, $court, '09:00:00', '10:00:00');

        $this->assertNull($this->validate($court, $user, '12:00', '13:00'));

        $this->option(BookingRules::TIME_BLOCK_MAX, '3600');
        $this->assertSame(__('booking.messages.daily_booking_limit_exceeded'), $this->validate($court, $user, '12:00', '13:00'));
    }

    #[Test]
    public function a_global_zero_lifts_a_court_daily_limit(): void
    {
        $this->option(BookingRules::TIME_BLOCK_MAX, '0');
        $court = $this->court(['time_block_bookable_max' => 3600]);
        $user = User::factory()->create(['status' => 'enabled']);
        $this->booking($user, $court, '09:00:00', '10:00:00');

        $this->assertNull($this->validate($court, $user, '12:00', '13:00'));
    }
}
