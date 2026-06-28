<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\BookingCancelled;
use App\Mail\BookingConfirmed;
use App\Models\Booking;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingMailTest extends TestCase
{
    use RefreshDatabase;

    private function makeSquare(): Square
    {
        return Square::factory()->create([
            'status' => 'enabled',
            'time_block_bookable_max' => 0,
            'range_book' => 0,
        ]);
    }

    #[Test]
    public function confirmation_mail_is_queued_when_booking_is_created(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'member@example.com']);
        $square = $this->makeSquare();

        app(BookingService::class)->createSingle(
            $user, $square, 2,
            Carbon::parse('2026-07-10 10:00:00'),
            Carbon::parse('2026-07-10 11:00:00'),
        );

        Mail::assertQueued(BookingConfirmed::class, fn ($m) => $m->hasTo('member@example.com'));
    }

    #[Test]
    public function no_confirmation_mail_when_user_has_no_email(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => null]);
        $square = $this->makeSquare();

        app(BookingService::class)->createSingle(
            $user, $square, 2,
            Carbon::parse('2026-07-10 10:00:00'),
            Carbon::parse('2026-07-10 11:00:00'),
        );

        Mail::assertNothingQueued();
    }

    #[Test]
    public function cancellation_mail_is_queued_when_booking_is_cancelled(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'member@example.com']);
        $booking = Booking::factory()->create(['uid' => $user->uid, 'status' => 'single']);
        Reservation::factory()->create([
            'bid' => $booking->bid,
            'date' => '2026-07-10',
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
        ]);

        app(BookingService::class)->cancelSingle($booking);

        Mail::assertQueued(BookingCancelled::class, fn ($m) => $m->hasTo('member@example.com'));
    }

    #[Test]
    public function no_cancellation_mail_when_user_has_no_email(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => null]);
        $booking = Booking::factory()->create(['uid' => $user->uid, 'status' => 'single']);

        app(BookingService::class)->cancelSingle($booking);

        Mail::assertNothingQueued();
    }
}
