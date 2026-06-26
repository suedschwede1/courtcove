<?php

declare(strict_types=1);

namespace Tests\Unit\Factories;

use App\Enums\SquareStatus;
use App\Models\Booking;
use App\Models\BookingBill;
use App\Models\BookingMeta;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\SquareMeta;
use App\Models\User;
use App\Models\UserMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FactoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_factory_creates_valid_user(): void
    {
        $user = User::factory()->create();
        $this->assertNotNull($user->uid);
        $this->assertNotEmpty($user->email);
        $this->assertNotEmpty($user->alias);
    }

    #[Test]
    public function square_factory_creates_valid_square(): void
    {
        $square = Square::factory()->create();
        $this->assertNotNull($square->sid);
        $this->assertInstanceOf(SquareStatus::class, $square->status);
    }

    #[Test]
    public function booking_factory_creates_string_statuses(): void
    {
        $booking = Booking::factory()->create();
        $this->assertContains($booking->status, ['single', 'subscription', 'cancelled']);
        $this->assertIsString($booking->status_billing);
    }

    #[Test]
    public function booking_factory_creates_with_relations(): void
    {
        $booking = Booking::factory()
            ->for(User::factory(), 'user')
            ->for(Square::factory(), 'square')
            ->has(Reservation::factory()->count(2), 'reservations')
            ->create();

        $this->assertEquals(2, $booking->reservations()->count());
        $this->assertNotNull($booking->user);
        $this->assertNotNull($booking->square);
    }

    #[Test]
    public function reservation_factory_creates_valid_reservation(): void
    {
        $reservation = Reservation::factory()->create();
        $this->assertNotNull($reservation->rid);
        $this->assertNotEmpty($reservation->time_start);
        $this->assertGreaterThan($reservation->time_start, $reservation->time_end);
    }

    #[Test]
    public function booking_bill_factory_creates_valid_bill(): void
    {
        $bill = BookingBill::factory()->create();
        $this->assertNotNull($bill->bbid);
        $this->assertGreaterThan(0, $bill->price);
    }

    #[Test]
    public function event_factory_creates_valid_event(): void
    {
        $event = Event::factory()->create();
        $this->assertNotNull($event->eid);
        $this->assertNotNull($event->datetime_start);
    }

    #[Test]
    public function square_meta_factory_creates_valid_meta(): void
    {
        $meta = SquareMeta::factory()->create();
        $this->assertNotNull($meta->smid);
        $this->assertNotEmpty($meta->key);
    }

    #[Test]
    public function user_meta_factory_creates_valid_meta(): void
    {
        $meta = UserMeta::factory()->create();
        $this->assertNotNull($meta->umid);
        $this->assertNotEmpty($meta->key);
    }

    #[Test]
    public function booking_meta_factory_creates_valid_meta(): void
    {
        $meta = BookingMeta::factory()->create();
        $this->assertNotNull($meta->bmid);
        $this->assertNotEmpty($meta->key);
    }
}
