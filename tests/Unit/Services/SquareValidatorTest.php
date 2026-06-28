<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Booking;
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
    public function short_booking_within_30min_ignores_daily_limit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 10:00:00'));

        $square = Square::factory()->create(['status' => 'enabled', 'time_block_bookable_max' => 3600]);
        $user = User::factory()->create();

        $dateStart = Carbon::now()->addMinutes(20);

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
}
