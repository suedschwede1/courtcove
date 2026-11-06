<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Option;
use App\Models\Square;
use App\Services\BookingRules;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** booking:adopt-booking-rules with the booking rules (grid, Vorlauf, Voraus, short window, cancel). */
class AdoptBookingRulesMoreRulesTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @param array<string, mixed> $attributes */
    private function court(string $name, array $attributes = [], ?string $shortWindow = null): Square
    {
        $square = Square::factory()->create(array_merge([
            'name' => $name, 'time_block' => 3600, 'time_block_bookable' => 3600, 'time_block_bookable_max' => 3600,
            'min_range_book' => 0, 'range_book' => 17280000, 'range_cancel' => 86400,
        ], $attributes));
        $square->setMeta('short-booking-window', $shortWindow);

        return $square;
    }

    #[Test]
    public function agreeing_rules_are_adopted_and_a_disagreeing_short_window_is_reported(): void
    {
        // Like staging: only court 1 has a short-booking window.
        $first = $this->court('1', [], '900');
        $second = $this->court('2');
        $third = $this->court('3');

        $this->artisan('booking:adopt-booking-rules')
            ->expectsOutputToContain('booking.short_booking_window')
            ->expectsTable(['sid', 'Court', 'Stored', 'Minutes'], [
                [(string) $first->sid, '1', '900', '15'],
                [(string) $second->sid, '2', '0', '0'],
                [(string) $third->sid, '3', '0', '0'],
            ])
            ->assertFailed();

        $this->assertSame('3600', Option::getValue('booking.time_block'));
        $this->assertSame('0', Option::getValue('booking.min_range_book'));
        $this->assertSame('17280000', Option::getValue('booking.range_book'));
        $this->assertSame('86400', Option::getValue('booking.range_cancel'));
        $this->assertNull(Option::getValue('booking.short_booking_window'), 'no silent choice');
        $this->assertSame('900', $first->fresh()->getMeta('short-booking-window'), 'court meta untouched');
    }

    #[Test]
    public function the_dry_run_shows_days_and_hours_and_writes_nothing(): void
    {
        $this->court('1');
        $this->court('2');

        $this->artisan('booking:adopt-booking-rules', ['--dry-run' => true])
            ->expectsOutputToContain('would adopt 17280000 seconds (200 Days)')
            ->expectsOutputToContain('would adopt 86400 seconds (24 Hours)')
            ->assertSuccessful();

        $this->assertSame(0, Option::query()->whereIn('key', array_map(fn ($rule) => $rule->optionKey, BookingRules::rules()))->count());
    }

    #[Test]
    public function differing_grids_are_reported_and_not_adopted(): void
    {
        $this->court('1', ['time_block' => 3600]);
        $this->court('2', ['time_block' => 1800]);

        $this->artisan('booking:adopt-booking-rules')->expectsOutputToContain('booking.time_block (')->assertFailed();

        $this->assertNull(Option::getValue('booking.time_block'));
    }
}
