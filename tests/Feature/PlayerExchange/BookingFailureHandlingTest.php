<?php

declare(strict_types=1);

namespace Tests\Feature\PlayerExchange;

use App\Models\Booking;
use App\Models\Square;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * An unexpected failure while booking reaches the user as one generic message, so it has to leave
 * a trace in the log, and the dialog has to tell a deploy (503) or an expired session (419) apart
 * from a booking that failed.
 */
class BookingFailureHandlingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo('2026-07-01 10:00');
    }

    /** @return array<string, mixed> */
    private function payload(Square $square, array $override = []): array
    {
        return array_merge([
            'sid' => $square->sid,
            'date' => '2026-07-10',
            'time_start' => 36000,
            'time_end' => 39600,
            'quantity' => 2,
        ], $override);
    }

    #[Test]
    public function an_unexpected_failure_is_logged_without_message_or_names(): void
    {
        $user = User::factory()->create(['alias' => 'Olga Owner', 'email' => 'olga@example.test']);
        $square = Square::factory()->create();
        Booking::creating(static function (): void {
            throw new RuntimeException('secret Olga Owner olga@example.test');
        });

        Log::spy();

        $this->actingAs($user)
            ->post(route('bookings.store'), $this->payload($square, ['search_players' => 1]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJson(['error' => __('booking.messages.booking_failed')]);

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) use ($square): bool {
            return $message === 'Booking request failed'
                && $context['action'] === 'booking.store'
                && $context['exception'] === RuntimeException::class
                && $context['sid'] === $square->sid
                && $context['date'] === '2026-07-10'
                && $context['quantity'] === 2
                && $context['search_players'] === 1
                && str_starts_with($context['at'], 'BookingFailureHandlingTest.php:')
                && ! str_contains(json_encode($context), 'Olga')
                && ! str_contains(json_encode($context), 'olga@example.test')
                && ! str_contains(json_encode($context), 'secret');
        });
    }

    #[Test]
    public function a_booking_rule_message_is_not_logged_as_a_failure(): void
    {
        $user = User::factory()->create();
        $square = Square::factory()->create();

        Log::spy();

        // Far in the past: a rule message, shown to the user as it is.
        $this->actingAs($user)
            ->post(route('bookings.store'), $this->payload($square, ['date' => '2026-06-01', 'mitspieler' => 'Partner']), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonPath('error', __('booking.messages.booking_in_past'));

        Log::shouldNotHaveReceived('warning');
    }

    #[Test]
    public function the_dialog_tells_a_server_that_is_down_and_an_expired_session_from_a_failed_booking(): void
    {
        $user = User::factory()->create();
        Square::factory()->create();

        $html = $this->actingAs($user)->get(route('calendar.index', ['date' => '2026-07-10']))->assertOk()->getContent();

        $this->assertStringContainsString('this.failureMessage(res.status, json)', $html);
        $this->assertStringContainsString('[502, 503, 504].includes(status)', $html);
        $this->assertStringContainsString('res.json().catch(() => null)', $html);
    }
}
