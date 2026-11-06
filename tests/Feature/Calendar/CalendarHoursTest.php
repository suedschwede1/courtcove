<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Models\Option;
use App\Models\Square;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Global calendar start/end (Verhalten) build the grid; court opening hours lock cells inside it. */
final class CalendarHoursTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-04 06:00'));
    }

    /** @return list<int> the hours of the first day card, in order */
    private function rowHours(string $html): array
    {
        preg_match_all('/class="cal-row" data-hour="(\d+)"/', $html, $m);

        return array_values(array_unique(array_map('intval', $m[1])));
    }

    private function behavior(array $overrides = []): array
    {
        return array_merge([
            'registration' => '1', 'activation' => 'manual', 'maintenance' => '0', 'quotes_enabled' => '1',
            'max_active_bookings' => '0', 'peak_limit_enabled' => '0', 'peak_limit_max' => '0',
            'peak_limit_w1_start' => '08:00', 'peak_limit_w1_end' => '12:00',
            'peak_limit_w2_start' => '17:00', 'peak_limit_w2_end' => '21:00',
        ], $overrides);
    }

    private function court(array $attributes = []): Square
    {
        return Square::factory()->create($attributes + ['time_start' => '07:00:00', 'time_end' => '22:00:00']);
    }

    #[Test]
    public function default_grid_is_eight_to_twenty(): void
    {
        $this->court();
        $html = $this->actingAs(User::factory()->create(['status' => 'enabled']))->get('/calendar?date=2026-11-04')->getContent();

        $this->assertSame(range(8, 19), $this->rowHours($html));
    }

    #[Test]
    public function configured_hours_build_the_grid(): void
    {
        $this->court();
        Option::create(['key' => 'calendar.time_start', 'value' => '09:00', 'locale' => null]);
        Option::create(['key' => 'calendar.time_end', 'value' => '22:00', 'locale' => null]);

        $html = $this->actingAs(User::factory()->create(['status' => 'enabled']))->get('/calendar?date=2026-11-04')->getContent();

        $this->assertSame(range(9, 21), $this->rowHours($html));
    }

    #[Test]
    public function hours_outside_the_court_opening_time_are_locked(): void
    {
        $this->court(['time_start' => '10:00:00', 'time_end' => '18:00:00']);
        $html = $this->actingAs(User::factory()->create(['status' => 'enabled']))->get('/calendar?date=2026-11-04')->getContent();

        // Rows 08, 09, 18 and 19 are closed on each rendered day card; 10 to 17 stay bookable.
        $closed = substr_count($html, 'slot-cell--closed');
        $this->assertGreaterThan(0, $closed);
        $this->assertSame(0, $closed % 4);
    }

    #[Test]
    public function settings_are_saved_and_validated(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);

        $this->actingAs($admin)->put('/admin/config/verhalten', $this->behavior(['calendar_time_start' => '07:00', 'calendar_time_end' => '21:00']))->assertSessionHasNoErrors();
        $this->assertSame('07:00', Option::getValue('calendar.time_start'));
        $this->assertSame('21:00', Option::getValue('calendar.time_end'));

        $this->actingAs($admin)->put('/admin/config/verhalten', $this->behavior(['calendar_time_start' => '20:00', 'calendar_time_end' => '08:00']))
            ->assertSessionHasErrors('calendar_time_end');
    }
}
