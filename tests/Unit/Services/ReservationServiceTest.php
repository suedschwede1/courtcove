<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Booking;
use App\Models\Reservation;
use App\Models\Square;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReservationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReservationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReservationService();
    }

    #[Test]
    public function get_in_range_returns_reservations_within_window(): void
    {
        $booking    = Booking::factory()->create(['status' => 'enabled']);
        $inRange    = Reservation::factory()->create(['bid' => $booking->bid, 'date' => Carbon::now()->timestamp]);
        $outOfRange = Reservation::factory()->create(['bid' => $booking->bid, 'date' => Carbon::now()->addDays(10)->startOfDay()->timestamp]);

        $results = $this->service->getInRange(Carbon::now()->subDay(), Carbon::now()->addDay());
        $rids    = $results->pluck('rid')->toArray();

        $this->assertContains($inRange->rid, $rids);
        $this->assertNotContains($outOfRange->rid, $rids);
    }

    #[Test]
    public function get_in_range_excludes_disabled_bookings(): void
    {
        $booking     = Booking::factory()->create(['status' => 'disabled']);
        $reservation = Reservation::factory()->create(['bid' => $booking->bid, 'date' => Carbon::now()->timestamp]);

        $results = $this->service->getInRange(Carbon::now()->subDay(), Carbon::now()->addDay());

        $this->assertNotContains($reservation->rid, $results->pluck('rid')->toArray());
    }

    #[Test]
    public function get_in_range_by_square_filters_by_court(): void
    {
        $square1 = Square::factory()->create();
        $square2 = Square::factory()->create();
        $b1      = Booking::factory()->create(['sid' => $square1->sid, 'status' => 'enabled']);
        $b2      = Booking::factory()->create(['sid' => $square2->sid, 'status' => 'enabled']);
        $r1      = Reservation::factory()->create(['bid' => $b1->bid, 'date' => now()->startOfDay()->timestamp]);
        $r2      = Reservation::factory()->create(['bid' => $b2->bid, 'date' => now()->startOfDay()->timestamp]);

        $results = $this->service->getInRangeBySquare($square1, Carbon::now()->subDay(), Carbon::now()->addDay());
        $rids    = $results->pluck('rid')->toArray();

        $this->assertContains($r1->rid, $rids);
        $this->assertNotContains($r2->rid, $rids);
    }

    #[Test]
    public function has_overlap_detects_conflict(): void
    {
        $booking = Booking::factory()->create(['status' => 'enabled']);
        Reservation::factory()->create([
            'bid' => $booking->bid, 'date' => Carbon::today()->startOfDay()->timestamp,
            'time_start' => 36000, 'time_end' => 39600,
        ]);

        $this->assertTrue(
            $this->service->hasOverlap($booking->square, Carbon::today(), 36000, 39600, null)
        );
    }

    #[Test]
    public function has_overlap_detects_partial_overlap_start(): void
    {
        $booking = Booking::factory()->create(['status' => 'enabled']);
        Reservation::factory()->create([
            'bid' => $booking->bid, 'date' => Carbon::today()->startOfDay()->timestamp,
            'time_start' => 36000, 'time_end' => 39600,
        ]);

        // New slot 09:30-10:30 overlaps with existing 10:00-11:00
        $this->assertTrue(
            $this->service->hasOverlap($booking->square, Carbon::today(), 34200, 37800, null)
        );
    }

    #[Test]
    public function has_overlap_ignores_disabled_bookings(): void
    {
        $booking = Booking::factory()->create(['status' => 'disabled']);
        Reservation::factory()->create([
            'bid' => $booking->bid, 'date' => Carbon::today()->startOfDay()->timestamp,
            'time_start' => 36000, 'time_end' => 39600,
        ]);

        $this->assertFalse(
            $this->service->hasOverlap($booking->square, Carbon::today(), 36000, 39600, null)
        );
    }

    #[Test]
    public function has_overlap_can_exclude_booking_id(): void
    {
        $booking = Booking::factory()->create(['status' => 'enabled']);
        Reservation::factory()->create([
            'bid' => $booking->bid, 'date' => Carbon::today()->startOfDay()->timestamp,
            'time_start' => 36000, 'time_end' => 39600,
        ]);

        // Same slot but exclude this booking — no conflict
        $this->assertFalse(
            $this->service->hasOverlap($booking->square, Carbon::today(), 36000, 39600, $booking->bid)
        );
    }

    #[Test]
    public function adjacent_slots_do_not_overlap(): void
    {
        $booking = Booking::factory()->create(['status' => 'enabled']);
        Reservation::factory()->create([
            'bid' => $booking->bid, 'date' => Carbon::today()->startOfDay()->timestamp,
            'time_start' => 36000, 'time_end' => 39600, // 10:00-11:00
        ]);

        // 11:00-12:00 is adjacent, not overlapping
        $this->assertFalse(
            $this->service->hasOverlap($booking->square, Carbon::today(), 39600, 43200, null)
        );
    }
}
