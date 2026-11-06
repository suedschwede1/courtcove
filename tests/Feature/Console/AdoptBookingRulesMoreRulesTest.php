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

    // --- --override ----------------------------------------------------------------------------------

    #[Test]
    public function an_override_sets_the_option_despite_disagreeing_courts_and_reports_every_superseded_value(): void
    {
        $first = $this->court('1', [], '900');
        $second = $this->court('2');
        $third = $this->court('3', [], '300');

        $this->artisan('booking:adopt-booking-rules', ['--override' => ['short_booking_window=15']])
            ->expectsOutputToContain('booking.short_booking_window (Kurzfristige Buchung trotz Limits): override 900 seconds (15 Minutes)')
            ->expectsTable(['sid', 'Court', 'Stored', 'Minutes'], [
                [(string) $second->sid, '2', '0', '0'],
                [(string) $third->sid, '3', '300', '5'],
            ])
            ->expectsOutputToContain('Effective behaviour changes on 2 court(s): 2 (0 -> 15 Minutes), 3 (5 -> 15 Minutes)')
            ->assertSuccessful();

        $this->assertSame('900', Option::getValue('booking.short_booking_window'));
        $this->assertSame('900', $first->fresh()->getMeta('short-booking-window'), 'court meta untouched');
        $this->assertSame('300', $third->fresh()->getMeta('short-booking-window'), 'court meta untouched');
    }

    #[Test]
    public function the_full_option_key_works_and_a_matching_court_is_not_listed_as_superseded(): void
    {
        $this->court('1', [], '900');
        $this->court('2', [], '900');

        $this->artisan('booking:adopt-booking-rules', ['--override' => ['booking.short_booking_window=15']])
            ->expectsOutputToContain('no stored court value is superseded')
            ->assertSuccessful();

        $this->assertSame('900', Option::getValue('booking.short_booking_window'));
    }

    #[Test]
    public function the_override_value_uses_the_display_unit_incl_decimals(): void
    {
        $this->court('1');
        $this->court('2');

        $this->artisan('booking:adopt-booking-rules', ['--override' => ['range_cancel=6', 'range_book=30']])->assertSuccessful();

        $this->assertSame('21600', Option::getValue('booking.range_cancel'), '6 hours');
        $this->assertSame('2592000', Option::getValue('booking.range_book'), '30 days');

        Option::query()->where('key', 'booking.range_cancel')->delete();
        $this->artisan('booking:adopt-booking-rules', ['--override' => ['range_cancel=0.1']])->assertSuccessful();
        $this->assertSame('360', Option::getValue('booking.range_cancel'));
    }

    #[Test]
    public function an_override_with_dry_run_reports_and_writes_nothing(): void
    {
        $this->court('1', [], '900');
        $this->court('2');

        $this->artisan('booking:adopt-booking-rules', ['--dry-run' => true, '--override' => ['short_booking_window=15']])
            ->expectsOutputToContain('would set 900 seconds (15 Minutes)')
            ->expectsOutputToContain('Effective behaviour changes on 1 court(s): 2 (0 -> 15 Minutes)')
            ->assertSuccessful();

        $this->assertNull(Option::getValue('booking.short_booking_window'));
    }

    #[Test]
    public function an_unknown_rule_key_or_a_bad_value_aborts_before_anything_is_written(): void
    {
        $this->court('1');
        $this->court('2');

        $this->artisan('booking:adopt-booking-rules', ['--override' => ['nonsense=1']])
            ->expectsOutputToContain('Unknown rule key "nonsense"')
            ->assertFailed();

        foreach (['short_booking_window=abc', 'short_booking_window=-5', 'short_booking_window=', 'short_booking_window', '=15'] as $bad) {
            $this->artisan('booking:adopt-booking-rules', ['--override' => [$bad]])->assertFailed();
        }

        // One good and one bad override: nothing is applied, not even the normal adoption.
        $this->artisan('booking:adopt-booking-rules', ['--override' => ['short_booking_window=15', 'range_book=x']])->assertFailed();

        $this->assertSame(0, Option::query()->whereIn('key', array_map(fn ($rule) => $rule->optionKey, BookingRules::rules()))->count());
    }

    #[Test]
    public function an_existing_option_is_only_overwritten_by_an_override_together_with_force(): void
    {
        $this->court('1', [], '900');
        $this->court('2');
        Option::updateOrCreate(['key' => 'booking.short_booking_window', 'locale' => null], ['value' => '600']);

        $this->artisan('booking:adopt-booking-rules', ['--override' => ['short_booking_window=15']])
            ->expectsOutputToContain('already set to 600 (10 Minutes); use --force to overwrite')
            ->assertFailed();
        $this->assertSame('600', Option::getValue('booking.short_booking_window'));

        $this->artisan('booking:adopt-booking-rules', ['--override' => ['short_booking_window=15'], '--force' => true, '--dry-run' => true])
            ->expectsOutputToContain('would overwrite 600 seconds (10 Minutes) with 900 seconds (15 Minutes)')
            ->assertSuccessful();
        $this->assertSame('600', Option::getValue('booking.short_booking_window'));

        $this->artisan('booking:adopt-booking-rules', ['--override' => ['short_booking_window=15'], '--force' => true])->assertSuccessful();
        $this->assertSame('900', Option::getValue('booking.short_booking_window'));
    }

    #[Test]
    public function repeating_an_override_is_idempotent_and_force_alone_overwrites_nothing(): void
    {
        $this->court('1', [], '900');
        $this->court('2');

        $this->artisan('booking:adopt-booking-rules', ['--override' => ['short_booking_window=15']])->assertSuccessful();
        $this->artisan('booking:adopt-booking-rules', ['--override' => ['short_booking_window=15']])
            ->expectsOutputToContain('already set to 900 (15 Minutes), matches the override')
            ->assertSuccessful();

        Square::query()->update(['range_cancel' => 3600]);
        $this->artisan('booking:adopt-booking-rules', ['--force' => true])->assertSuccessful();
        $this->assertSame('900', Option::getValue('booking.short_booking_window'));
        $this->assertSame('86400', Option::getValue('booking.range_cancel'), 'force without --override never overwrites');
    }
}
