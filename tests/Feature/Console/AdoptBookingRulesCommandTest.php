<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Option;
use App\Models\Square;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** booking:adopt-booking-rules: court values become global options only when all courts agree. */
class AdoptBookingRulesCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function courts(array ...$values): void
    {
        foreach ($values as $i => [$min, $max]) {
            Square::factory()->create(['name' => (string) ($i + 1), 'time_block_bookable' => $min, 'time_block_bookable_max' => $max]);
        }
    }

    #[Test]
    public function equal_court_values_are_adopted_as_options(): void
    {
        $this->courts([3600, 3600], [3600, 3600], [3600, 3600]);

        $this->artisan('booking:adopt-booking-rules')
            ->expectsOutputToContain('booking.time_block_bookable_min')
            ->assertSuccessful();

        $this->assertSame('3600', Option::getValue('booking.time_block_bookable_min'));
        $this->assertSame('3600', Option::getValue('booking.time_block_bookable_max'));
        $this->assertSame(3, Square::query()->where('time_block_bookable', 3600)->count(), 'courts unchanged');
    }

    #[Test]
    public function a_second_run_changes_nothing(): void
    {
        $this->courts([3600, 0], [3600, 0]);
        $this->artisan('booking:adopt-booking-rules')->assertSuccessful();

        Square::query()->update(['time_block_bookable' => 1800]);
        $this->artisan('booking:adopt-booking-rules')->expectsOutputToContain('already set')->assertSuccessful();

        $this->assertSame('3600', Option::getValue('booking.time_block_bookable_min'), 'an existing option is never overwritten');
        $this->assertSame('0', Option::getValue('booking.time_block_bookable_max'));
        $this->assertSame(2, Option::query()->where('key', 'like', 'booking.time_block_bookable_%')->count());
    }

    #[Test]
    public function different_court_values_abort_that_setting_with_a_table(): void
    {
        $this->courts([3600, 3600], [1800, 3600]);

        $this->artisan('booking:adopt-booking-rules')
            ->expectsOutputToContain('courts disagree')
            ->expectsTable(['sid', 'Court', 'Stored', 'Minutes'], [
                [(string) Square::query()->where('name', '1')->value('sid'), '1', '3600', '60'],
                [(string) Square::query()->where('name', '2')->value('sid'), '2', '1800', '30'],
            ])
            ->assertFailed();

        $this->assertNull(Option::getValue('booking.time_block_bookable_min'), 'no silent choice');
        $this->assertSame('3600', Option::getValue('booking.time_block_bookable_max'), 'the agreeing setting is adopted');
    }

    #[Test]
    public function dry_run_reports_and_writes_nothing(): void
    {
        $this->courts([3600, 3600], [3600, 3600]);

        $this->artisan('booking:adopt-booking-rules', ['--dry-run' => true])
            ->expectsOutputToContain('would adopt')
            ->assertSuccessful();

        $this->assertSame(0, Option::query()->where('key', 'like', 'booking.time_block_bookable_%')->count());
    }

    #[Test]
    public function without_courts_nothing_is_adopted(): void
    {
        $this->artisan('booking:adopt-booking-rules')->expectsOutputToContain('no courts')->assertSuccessful();

        $this->assertSame(0, Option::query()->where('key', 'like', 'booking.time_block_bookable_%')->count());
    }
}
