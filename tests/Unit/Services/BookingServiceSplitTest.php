<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\BookingValidationException;
use App\Models\Booking;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use App\Services\BookingService;
use App\Services\SquareValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingServiceSplitTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BookingService(new SquareValidator);
    }

    /** @return array{0: Booking, 1: list<Reservation>} */
    private function makeSubscription(array $dates): array
    {
        $user = User::factory()->create();
        $square = Square::factory()->create();
        $booking = Booking::factory()->create([
            'uid' => $user->uid,
            'sid' => $square->sid,
            'status' => 'subscription',
            'quantity' => 4,
        ]);
        $booking->meta()->create(['key' => 'owner-name', 'value' => 'Gast Serie']);
        $booking->meta()->create(['key' => 'admin-note', 'value' => 'Interne Notiz']);

        $reservations = [];
        foreach ($dates as $date) {
            $reservations[] = Reservation::factory()->create([
                'bid' => $booking->bid, 'date' => $date, 'time_start' => '10:00:00', 'time_end' => '11:00:00',
            ]);
        }

        return [$booking->fresh(['reservations', 'meta']), $reservations];
    }

    #[Test]
    public function returns_same_booking_when_occurrence_is_null(): void
    {
        [$booking] = $this->makeSubscription(['2026-07-06', '2026-07-13', '2026-07-20']);

        $result = $this->service->splitForScope($booking, null, 'single_occurrence');

        $this->assertSame($booking->bid, $result->bid);
    }

    #[Test]
    public function returns_same_booking_when_not_a_subscription(): void
    {
        $booking = Booking::factory()->create(['status' => 'single']);
        Reservation::factory()->create(['bid' => $booking->bid, 'date' => '2026-07-06']);

        $result = $this->service->splitForScope($booking, '2026-07-06', 'single_occurrence');

        $this->assertSame($booking->bid, $result->bid);
    }

    #[Test]
    public function rejects_an_occurrence_date_missing_from_the_current_reservations(): void
    {
        [$booking] = $this->makeSubscription(['2026-07-06', '2026-07-13']);

        $this->expectException(BookingValidationException::class);
        $this->service->splitForScope($booking, '2026-08-01', 'single_occurrence');
    }

    #[Test]
    public function single_occurrence_moves_only_that_reservation_into_a_new_single_booking(): void
    {
        [$booking, $reservations] = $this->makeSubscription(['2026-07-06', '2026-07-13', '2026-07-20']);

        $newBooking = $this->service->splitForScope($booking, '2026-07-13', 'single_occurrence');

        $this->assertNotSame($booking->bid, $newBooking->bid);
        $this->assertSame('single', $newBooking->fresh()->status);
        $this->assertSame($booking->uid, $newBooking->uid);
        $this->assertSame($booking->sid, $newBooking->sid);
        $this->assertSame(4, $newBooking->quantity);
        $this->assertEquals(1, $newBooking->reservations()->count());
        $this->assertSame('2026-07-13', $newBooking->reservations()->first()->date);

        $booking->refresh();
        $this->assertSame('subscription', $booking->status);
        $this->assertEquals(2, $booking->reservations()->count());
        $this->assertDatabaseHas('bs_bookings_meta', ['bid' => $newBooking->bid, 'key' => 'owner-name', 'value' => 'Gast Serie']);
        $this->assertDatabaseHas('bs_bookings_meta', ['bid' => $newBooking->bid, 'key' => 'admin-note', 'value' => 'Interne Notiz']);
    }

    #[Test]
    public function single_occurrence_leaves_original_as_single_when_only_two_reservations_existed(): void
    {
        [$booking] = $this->makeSubscription(['2026-07-06', '2026-07-13']);

        $this->service->splitForScope($booking, '2026-07-13', 'single_occurrence');

        $this->assertSame('single', $booking->fresh()->status);
        $this->assertEquals(1, $booking->reservations()->count());
    }

    #[Test]
    public function single_occurrence_on_the_only_remaining_reservation_does_not_split(): void
    {
        [$booking] = $this->makeSubscription(['2026-07-06']);
        $booking->update(['status' => 'subscription']); // force subscription flag even with 1 row

        $result = $this->service->splitForScope($booking, '2026-07-06', 'single_occurrence');

        $this->assertSame($booking->bid, $result->bid);
    }

    #[Test]
    public function this_and_following_moves_occurrence_and_later_dates_into_new_booking(): void
    {
        [$booking] = $this->makeSubscription(['2026-07-06', '2026-07-13', '2026-07-20', '2026-07-27']);

        $newBooking = $this->service->splitForScope($booking, '2026-07-20', 'this_and_following');

        $this->assertNotSame($booking->bid, $newBooking->bid);
        $this->assertSame('subscription', $newBooking->fresh()->status);
        $this->assertEquals(2, $newBooking->reservations()->count());
        $this->assertEqualsCanonicalizing(
            ['2026-07-20', '2026-07-27'],
            $newBooking->reservations()->pluck('date')->all(),
        );

        $booking->refresh();
        $this->assertSame('subscription', $booking->status);
        $this->assertEqualsCanonicalizing(
            ['2026-07-06', '2026-07-13'],
            $booking->reservations()->pluck('date')->all(),
        );
    }

    #[Test]
    public function this_and_following_on_the_last_occurrence_creates_a_single_booking(): void
    {
        [$booking] = $this->makeSubscription(['2026-07-06', '2026-07-13', '2026-07-20']);

        $newBooking = $this->service->splitForScope($booking, '2026-07-20', 'this_and_following');

        $this->assertSame('single', $newBooking->fresh()->status);
        $this->assertEquals(1, $newBooking->reservations()->count());
    }

    #[Test]
    public function this_and_following_on_the_first_occurrence_does_not_split(): void
    {
        [$booking] = $this->makeSubscription(['2026-07-06', '2026-07-13', '2026-07-20']);

        $result = $this->service->splitForScope($booking, '2026-07-06', 'this_and_following');

        $this->assertSame($booking->bid, $result->bid);
        $this->assertEquals(3, $booking->reservations()->count());
    }

    #[Test]
    public function cancel_single_with_scope_cancels_only_the_split_off_booking(): void
    {
        [$booking] = $this->makeSubscription(['2026-07-06', '2026-07-13', '2026-07-20']);

        $this->service->cancelSingle($booking, '2026-07-13', 'single_occurrence');

        $this->assertSame('subscription', $booking->fresh()->status);
        $this->assertEquals(2, $booking->reservations()->count());

        $cancelled = Booking::where('status', 'cancelled')->firstOrFail();
        $this->assertEquals(1, $cancelled->reservations()->count());
        $this->assertSame('2026-07-13', $cancelled->reservations()->first()->date);
    }

    #[Test]
    public function cancel_single_without_occurrence_cancels_whole_booking_as_before(): void
    {
        [$booking] = $this->makeSubscription(['2026-07-06', '2026-07-13']);

        $this->service->cancelSingle($booking);

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertEquals(2, $booking->reservations()->count());
    }

    #[Test]
    public function delete_single_with_this_and_following_scope_only_deletes_tail_reservations(): void
    {
        [$booking] = $this->makeSubscription(['2026-07-06', '2026-07-13', '2026-07-20']);

        $this->service->deleteSingle($booking, '2026-07-13', 'this_and_following');

        $this->assertDatabaseHas('bs_bookings', ['bid' => $booking->bid]);
        $this->assertEquals(1, $booking->fresh()->reservations()->count());
        $this->assertSame('single', $booking->fresh()->status);
        $this->assertDatabaseMissing('bs_reservations', ['date' => '2026-07-13']);
        $this->assertDatabaseMissing('bs_reservations', ['date' => '2026-07-20']);
        $this->assertDatabaseHas('bs_reservations', ['date' => '2026-07-06']);
    }
}
