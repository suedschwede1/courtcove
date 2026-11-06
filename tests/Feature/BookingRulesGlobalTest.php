<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Option;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use App\Services\BookingRules;
use App\Services\BookingService;
use App\Services\SquareValidator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Booking rules that became global (Verhalten, card "Buchungsregeln"): grid, Buchungsvorlauf, Buchung im
 * Voraus, Kurzfristige Buchung trotz Limits, Stornierung. Option first (an explicit 0 counts), court second.
 */
class BookingRulesGlobalTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-07-01 10:00:00');
    }

    private function option(string $key, string $value): void
    {
        Option::updateOrCreate(['key' => $key, 'locale' => null], ['value' => $value]);
    }

    /** @param array<string, mixed> $attributes */
    private function court(array $attributes = []): Square
    {
        return Square::factory()->create(array_merge(['status' => 'enabled', 'time_start' => '07:00:00', 'time_end' => '22:00:00'], $attributes));
    }

    private function validate(Square $square, string $start): ?string
    {
        $from = Carbon::parse($start);
        $result = app(SquareValidator::class)->validate($square, User::factory()->create(['status' => 'enabled']), 2, $from, $from->copy()->addHour());

        return $result->isValid() ? null : $result->getError();
    }

    #[Test]
    public function every_rule_reads_the_option_first_and_the_court_second(): void
    {
        $court = $this->court(['time_block' => 1800, 'min_range_book' => 600, 'range_book' => 86400, 'range_cancel' => 7200]);
        $court->setMeta('short-booking-window', '900');
        $rules = app(BookingRules::class);

        $expectedFallback = [
            BookingRules::TIME_BLOCK => 1800, BookingRules::MIN_RANGE_BOOK => 600, BookingRules::RANGE_BOOK => 86400,
            BookingRules::SHORT_BOOKING_WINDOW => 900, BookingRules::RANGE_CANCEL => 7200,
        ];
        foreach ($expectedFallback as $key => $value) {
            $this->assertSame($value, $rules->value($key, $court), $key.' fallback');
        }

        foreach (array_keys($expectedFallback) as $key) {
            $this->option($key, '0');
            $this->assertSame(0, $rules->value($key, $court), $key.' explicit 0 overrides');
            $this->option($key, '3600');
            $this->assertSame(3600, $rules->value($key, $court), $key.' option');
        }
    }

    #[Test]
    public function a_court_without_short_booking_meta_counts_as_zero(): void
    {
        $this->assertSame(0, app(BookingRules::class)->value(BookingRules::SHORT_BOOKING_WINDOW, $this->court()));
    }

    #[Test]
    public function buchungsvorlauf_comes_from_the_option(): void
    {
        $court = $this->court(['min_range_book' => 0]);
        $this->assertNull($this->validate($court, '2026-07-01 12:00'));

        $this->option(BookingRules::MIN_RANGE_BOOK, '14400');
        $this->assertSame(__('booking.messages.booking_too_early'), $this->validate($court, '2026-07-01 12:00'));
        $this->assertNull($this->validate($court, '2026-07-01 15:00'));
    }

    #[Test]
    public function buchung_im_voraus_comes_from_the_option(): void
    {
        $court = $this->court(['range_book' => 30 * 86400]);
        $this->assertNull($this->validate($court, '2026-07-20 10:00'));

        $this->option(BookingRules::RANGE_BOOK, (string) (7 * 86400));
        $this->assertNotNull($this->validate($court, '2026-07-20 10:00'));
        $this->assertNull($this->validate($court, '2026-07-08 10:00'), 'same day as the boundary still allowed');
    }

    #[Test]
    public function the_short_booking_window_comes_from_the_option(): void
    {
        $this->option(BookingRules::MIN_RANGE_BOOK, '14400');
        $court = $this->court();

        $this->assertSame(__('booking.messages.booking_too_early'), $this->validate($court, '2026-07-01 10:30'));

        $this->option(BookingRules::SHORT_BOOKING_WINDOW, '3600');
        $this->assertNull($this->validate($court, '2026-07-01 10:30'), 'within 60 minutes the Vorlauf is lifted');
        $this->assertSame(__('booking.messages.booking_too_early'), $this->validate($court, '2026-07-01 12:00'));
    }

    #[Test]
    public function the_cancellation_deadline_comes_from_the_option(): void
    {
        $court = $this->court(['range_cancel' => 3600]);
        $user = User::factory()->create(['status' => 'enabled']);
        $booking = Booking::factory()->create(['uid' => $user->uid, 'sid' => $court->sid, 'status' => 'single']);
        Reservation::factory()->create(['bid' => $booking->bid, 'date' => '2026-07-01', 'time_start' => '13:00:00', 'time_end' => '14:00:00']);
        $service = app(BookingService::class);

        $this->assertTrue($service->canUserCancelSingle($user, $booking->fresh()), 'court: 1 hour before');

        $this->option(BookingRules::RANGE_CANCEL, (string) (4 * 3600));
        $this->assertFalse($service->canUserCancelSingle($user, $booking->fresh()), 'global: 4 hours before');

        $this->option(BookingRules::RANGE_CANCEL, '0');
        $this->assertFalse($service->canUserCancelSingle($user, $booking->fresh()), '0 = members never cancel');
    }

    #[Test]
    public function the_rule_list_contains_the_moved_settings(): void
    {
        $keys = array_map(fn ($rule) => $rule->optionKey, BookingRules::rules());

        foreach ([BookingRules::TIME_BLOCK_MIN, BookingRules::TIME_BLOCK_MAX, BookingRules::TIME_BLOCK, BookingRules::MIN_RANGE_BOOK,
            BookingRules::RANGE_BOOK, BookingRules::SHORT_BOOKING_WINDOW, BookingRules::RANGE_CANCEL] as $key) {
            $this->assertContains($key, $keys);
        }
    }
}
