<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\BookingValidationException;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use App\Services\BookingService;
use App\Services\SquareValidator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingServiceCapacityTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BookingService(new SquareValidator);
        Mail::fake();
    }

    // --- Capacity checks ---

    #[Test]
    public function booking_is_rejected_when_court_is_at_full_capacity(): void
    {
        $square = Square::factory()->create(['capacity' => 2, 'capacity_heterogenic' => 1]);
        $user = User::factory()->create();

        $this->seedReservation($square, quantity: 2);

        $this->expectException(BookingValidationException::class);

        $this->service->createSingle(
            $user, $square, 1,
            Carbon::now()->addDay()->setTime(10, 0),
            Carbon::now()->addDay()->setTime(11, 0),
        );
    }

    #[Test]
    public function booking_succeeds_when_capacity_has_space(): void
    {
        $square = Square::factory()->create(['capacity' => 4, 'capacity_heterogenic' => 1]);
        $user = User::factory()->create();

        $this->seedReservation($square, quantity: 2);

        $booking = $this->service->createSingle(
            $user, $square, 2,
            Carbon::now()->addDay()->setTime(10, 0),
            Carbon::now()->addDay()->setTime(11, 0),
        );

        $this->assertNotNull($booking->bid);
    }

    #[Test]
    public function non_heterogenic_court_blocks_any_overlapping_booking(): void
    {
        // capacity_heterogenic = 0 means no mixing: any existing booking blocks the slot entirely
        $square = Square::factory()->create(['capacity' => 4, 'capacity_heterogenic' => 0]);
        $user = User::factory()->create();

        $this->seedReservation($square, quantity: 1);

        $this->expectException(BookingValidationException::class);

        $this->service->createSingle(
            $user, $square, 1,
            Carbon::now()->addDay()->setTime(10, 0),
            Carbon::now()->addDay()->setTime(11, 0),
        );
    }

    #[Test]
    public function non_overlapping_booking_is_not_blocked_by_existing_reservation(): void
    {
        $square = Square::factory()->create(['capacity' => 2, 'capacity_heterogenic' => 1]);
        $user = User::factory()->create();

        // Existing reservation: 10:00–11:00
        $this->seedReservation($square, quantity: 2, timeStart: '10:00:00', timeEnd: '11:00:00');

        // New booking: 11:00–12:00 — no overlap
        $booking = $this->service->createSingle(
            $user, $square, 2,
            Carbon::now()->addDay()->setTime(11, 0),
            Carbon::now()->addDay()->setTime(12, 0),
        );

        $this->assertNotNull($booking->bid);
    }

    // --- Event conflict checks ---

    #[Test]
    public function enabled_event_on_specific_court_blocks_booking(): void
    {
        $square = Square::factory()->create();
        $user = User::factory()->create();

        Event::factory()->create([
            'sid' => $square->sid,
            'status' => 'enabled',
            'datetime_start' => Carbon::now()->addDay()->setTime(9, 0),
            'datetime_end' => Carbon::now()->addDay()->setTime(12, 0),
        ]);

        $this->expectException(BookingValidationException::class);

        $this->service->createSingle(
            $user, $square, 2,
            Carbon::now()->addDay()->setTime(10, 0),
            Carbon::now()->addDay()->setTime(11, 0),
        );
    }

    #[Test]
    public function enabled_event_for_all_courts_null_sid_blocks_booking(): void
    {
        $square = Square::factory()->create();
        $user = User::factory()->create();

        Event::factory()->create([
            'sid' => null,
            'status' => 'enabled',
            'datetime_start' => Carbon::now()->addDay()->setTime(9, 0),
            'datetime_end' => Carbon::now()->addDay()->setTime(12, 0),
        ]);

        $this->expectException(BookingValidationException::class);

        $this->service->createSingle(
            $user, $square, 2,
            Carbon::now()->addDay()->setTime(10, 0),
            Carbon::now()->addDay()->setTime(11, 0),
        );
    }

    #[Test]
    public function disabled_event_does_not_block_booking(): void
    {
        $square = Square::factory()->create();
        $user = User::factory()->create();

        Event::factory()->create([
            'sid' => $square->sid,
            'status' => 'disabled',
            'datetime_start' => Carbon::now()->addDay()->setTime(9, 0),
            'datetime_end' => Carbon::now()->addDay()->setTime(12, 0),
        ]);

        $booking = $this->service->createSingle(
            $user, $square, 2,
            Carbon::now()->addDay()->setTime(10, 0),
            Carbon::now()->addDay()->setTime(11, 0),
        );

        $this->assertNotNull($booking->bid);
    }

    #[Test]
    public function event_on_different_court_does_not_block_booking(): void
    {
        $square = Square::factory()->create();
        $otherSquare = Square::factory()->create();
        $user = User::factory()->create();

        Event::factory()->create([
            'sid' => $otherSquare->sid,
            'status' => 'enabled',
            'datetime_start' => Carbon::now()->addDay()->setTime(9, 0),
            'datetime_end' => Carbon::now()->addDay()->setTime(12, 0),
        ]);

        $booking = $this->service->createSingle(
            $user, $square, 2,
            Carbon::now()->addDay()->setTime(10, 0),
            Carbon::now()->addDay()->setTime(11, 0),
        );

        $this->assertNotNull($booking->bid);
    }

    #[Test]
    public function event_ending_before_booking_does_not_block(): void
    {
        $square = Square::factory()->create();
        $user = User::factory()->create();

        Event::factory()->create([
            'sid' => $square->sid,
            'status' => 'enabled',
            'datetime_start' => Carbon::now()->addDay()->setTime(8, 0),
            'datetime_end' => Carbon::now()->addDay()->setTime(10, 0),
        ]);

        $booking = $this->service->createSingle(
            $user, $square, 2,
            Carbon::now()->addDay()->setTime(10, 0),
            Carbon::now()->addDay()->setTime(11, 0),
        );

        $this->assertNotNull($booking->bid);
    }

    // --- Helper ---

    private function seedReservation(
        Square $square,
        int $quantity = 2,
        string $timeStart = '10:00:00',
        string $timeEnd = '11:00:00',
    ): void {
        $booking = Booking::factory()->create([
            'sid' => $square->sid,
            'status' => 'single',
            'visibility' => 'public',
            'quantity' => $quantity,
        ]);

        Reservation::factory()->create([
            'bid' => $booking->bid,
            'date' => Carbon::now()->addDay()->toDateString(),
            'time_start' => $timeStart,
            'time_end' => $timeEnd,
        ]);
    }
}
