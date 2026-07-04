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

    /** Mails are only dispatched when a real SMTP mailer is configured. */
    private function configureMail(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.com',
        ]);
    }

    /** Mail dispatch is deferred via afterResponse(); run the terminating callbacks. */
    private function runDeferredJobs(): void
    {
        $this->app->terminate();
    }

    #[Test]
    public function confirmation_mail_is_queued_when_booking_is_created(): void
    {
        Mail::fake();
        $this->configureMail();

        $user = User::factory()->create(['email' => 'member@example.com']);
        $square = $this->makeSquare();

        app(BookingService::class)->createSingle(
            $user, $square, 2,
            Carbon::parse('2026-07-10 10:00:00'),
            Carbon::parse('2026-07-10 11:00:00'),
        );
        $this->runDeferredJobs();

        Mail::assertQueued(BookingConfirmed::class, fn ($m) => $m->hasTo('member@example.com'));
    }

    #[Test]
    public function no_confirmation_mail_when_mail_is_not_configured(): void
    {
        // The test environment uses the array mailer - mail is "not available",
        // matching the current production setup without SMTP.
        Mail::fake();

        $user = User::factory()->create(['email' => 'member@example.com']);
        $square = $this->makeSquare();

        app(BookingService::class)->createSingle(
            $user, $square, 2,
            Carbon::parse('2026-07-10 10:00:00'),
            Carbon::parse('2026-07-10 11:00:00'),
        );
        $this->runDeferredJobs();

        Mail::assertNothingOutgoing();
    }

    #[Test]
    public function no_confirmation_mail_when_user_has_no_email(): void
    {
        Mail::fake();
        $this->configureMail();

        $user = User::factory()->create(['email' => null]);
        $square = $this->makeSquare();

        app(BookingService::class)->createSingle(
            $user, $square, 2,
            Carbon::parse('2026-07-10 10:00:00'),
            Carbon::parse('2026-07-10 11:00:00'),
        );
        $this->runDeferredJobs();

        Mail::assertNothingOutgoing();
    }

    #[Test]
    public function cancellation_mail_is_queued_when_booking_is_cancelled(): void
    {
        Mail::fake();
        $this->configureMail();

        $user = User::factory()->create(['email' => 'member@example.com']);
        $booking = Booking::factory()->create(['uid' => $user->uid, 'status' => 'single']);
        Reservation::factory()->create([
            'bid' => $booking->bid,
            'date' => '2026-07-10',
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
        ]);

        app(BookingService::class)->cancelSingle($booking);
        $this->runDeferredJobs();

        Mail::assertQueued(BookingCancelled::class, fn ($m) => $m->hasTo('member@example.com'));
    }

    #[Test]
    public function no_cancellation_mail_when_mail_is_not_configured(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'member@example.com']);
        $booking = Booking::factory()->create(['uid' => $user->uid, 'status' => 'single']);

        app(BookingService::class)->cancelSingle($booking);
        $this->runDeferredJobs();

        Mail::assertNothingOutgoing();
    }

    #[Test]
    public function no_cancellation_mail_when_user_has_no_email(): void
    {
        Mail::fake();
        $this->configureMail();

        $user = User::factory()->create(['email' => null]);
        $booking = Booking::factory()->create(['uid' => $user->uid, 'status' => 'single']);

        app(BookingService::class)->cancelSingle($booking);
        $this->runDeferredJobs();

        Mail::assertNothingOutgoing();
    }
}
