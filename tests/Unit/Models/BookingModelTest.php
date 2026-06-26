<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\BillingStatus;
use App\Enums\BookingStatus;
use App\Enums\Visibility;
use App\Models\Booking;
use App\Models\BookingBill;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function booking_belongs_to_user(): void
    {
        $user    = User::factory()->create();
        $square  = Square::factory()->create();
        $booking = Booking::factory()->create(['uid' => $user->uid, 'sid' => $square->sid]);

        $this->assertInstanceOf(User::class, $booking->user);
        $this->assertEquals($user->uid, $booking->user->uid);
    }

    #[Test]
    public function booking_belongs_to_square(): void
    {
        $square  = Square::factory()->create();
        $booking = Booking::factory()->create(['sid' => $square->sid]);

        $this->assertInstanceOf(Square::class, $booking->square);
    }

    #[Test]
    public function booking_has_many_reservations(): void
    {
        $booking = Booking::factory()->create();
        Reservation::factory()->count(3)->create(['bid' => $booking->bid]);

        $this->assertCount(3, $booking->reservations);
    }

    #[Test]
    public function booking_has_many_bills(): void
    {
        $booking = Booking::factory()->create();
        BookingBill::factory()->count(2)->create(['bid' => $booking->bid]);

        $this->assertCount(2, $booking->bills);
    }

    #[Test]
    public function booking_status_is_cast_to_enum(): void
    {
        $booking = Booking::factory()->create(['status' => 'enabled']);

        $this->assertInstanceOf(BookingStatus::class, $booking->status);
        $this->assertSame(BookingStatus::Enabled, $booking->status);
    }

    #[Test]
    public function booking_billing_status_is_cast_to_enum(): void
    {
        $booking = Booking::factory()->create(['status_billing' => 'pending']);

        $this->assertInstanceOf(BillingStatus::class, $booking->status_billing);
        $this->assertSame(BillingStatus::Pending, $booking->status_billing);
    }

    #[Test]
    public function booking_visibility_is_cast_to_enum(): void
    {
        $booking = Booking::factory()->create(['visibility' => 'public']);

        $this->assertInstanceOf(Visibility::class, $booking->visibility);
        $this->assertSame(Visibility::Public, $booking->visibility);
    }
}
