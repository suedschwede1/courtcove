<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\BillingStatus;
use App\Enums\BookingStatus;
use App\Exceptions\BookingValidationException;
use App\Models\Booking;
use App\Models\Square;
use App\Models\User;
use App\Services\BookingService;
use App\Services\SquareValidator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BookingService(new SquareValidator());
    }

    #[Test]
    public function create_single_persists_booking_and_reservation(): void
    {
        $user   = User::factory()->create(['permissions' => 'calendar.create-single-bookings']);
        $square = Square::factory()->create(['status' => 'enabled', 'time_block_bookable_max' => 0, 'range_book' => 0]);

        $dateStart = Carbon::now()->addDay()->setTime(10, 0);
        $dateEnd   = Carbon::now()->addDay()->setTime(11, 0);

        $booking = $this->service->createSingle($user, $square, 2, $dateStart, $dateEnd);

        $this->assertNotNull($booking->bid);
        $this->assertSame(BookingStatus::Enabled, $booking->status);
        $this->assertSame(BillingStatus::Pending, $booking->status_billing);
        $this->assertEquals(1, $booking->reservations()->count());

        $reservation = $booking->reservations()->first();
        $this->assertEquals(36000, $reservation->time_start); // 10 * 3600
        $this->assertEquals(39600, $reservation->time_end);   // 11 * 3600
    }

    #[Test]
    public function create_single_throws_when_square_is_disabled(): void
    {
        $user   = User::factory()->create();
        $square = Square::factory()->create(['status' => 'disabled']);

        $this->expectException(BookingValidationException::class);

        $this->service->createSingle(
            $user, $square, 2,
            Carbon::now()->addDay()->setTime(10, 0),
            Carbon::now()->addDay()->setTime(11, 0),
        );
    }

    #[Test]
    public function cancel_sets_status_disabled_and_billing_cancelled(): void
    {
        $booking = Booking::factory()->create(['status' => 'enabled', 'status_billing' => 'pending']);

        $this->service->cancelSingle($booking);

        $booking->refresh();
        $this->assertSame(BookingStatus::Disabled, $booking->status);
        $this->assertSame(BillingStatus::Cancelled, $booking->status_billing);
    }

    #[Test]
    public function create_single_with_meta_persists_meta(): void
    {
        $user   = User::factory()->create(['permissions' => 'calendar.create-single-bookings']);
        $square = Square::factory()->create(['status' => 'enabled', 'time_block_bookable_max' => 0, 'range_book' => 0]);

        $booking = $this->service->createSingle(
            $user, $square, 2,
            Carbon::now()->addDay()->setTime(10, 0),
            Carbon::now()->addDay()->setTime(11, 0),
            meta: [['meta_key' => 'player_name_1', 'meta_value' => 'Max Mustermann']],
        );

        $this->assertEquals(1, $booking->meta()->count());
        $this->assertEquals('Max Mustermann', $booking->meta()->first()->meta_value);
    }

    #[Test]
    public function create_single_no_orphan_booking_on_validation_failure(): void
    {
        $user   = User::factory()->create(['permissions' => 'calendar.create-single-bookings']);
        $square = Square::factory()->create(['status' => 'disabled']);
        $count  = Booking::count();

        try {
            $this->service->createSingle(
                $user, $square, 2,
                Carbon::now()->addDay()->setTime(10, 0),
                Carbon::now()->addDay()->setTime(11, 0),
            );
        } catch (BookingValidationException) {}

        $this->assertEquals($count, Booking::count());
    }
}
