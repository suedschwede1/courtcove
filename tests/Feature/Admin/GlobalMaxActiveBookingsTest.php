<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Option;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use App\Services\SquareValidator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GlobalMaxActiveBookingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function the_limit_is_saved_on_the_behavior_page_and_shown_there(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);

        $this->actingAs($admin)->put('/admin/config/verhalten', ['max_active_bookings' => '3'])->assertRedirect();

        $this->assertSame('3', Option::getValue('booking.max_active_bookings'));
        $this->actingAs($admin)->get('/admin/config/verhalten')->assertOk()->assertSee('name="max_active_bookings"', false);
    }

    #[Test]
    public function a_negative_limit_is_rejected(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);

        $this->actingAs($admin)->put('/admin/config/verhalten', ['max_active_bookings' => '-1'])->assertSessionHasErrors('max_active_bookings');
    }

    #[Test]
    public function the_court_form_no_longer_has_the_field(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $square = Square::factory()->create();

        $this->actingAs($admin)->get('/admin/squares/'.$square->sid.'/edit')->assertOk()->assertDontSee('name="max_active_bookings"', false);
    }

    #[Test]
    public function the_limit_counts_over_all_courts_whatever_the_court_column_says(): void
    {
        Option::updateOrCreate(['key' => 'booking.max_active_bookings', 'locale' => null], ['value' => '2']);
        $courtA = Square::factory()->create(['status' => 'enabled', 'max_active_bookings' => 99, 'time_block_bookable_max' => 0, 'range_book' => 0]);
        $courtB = Square::factory()->create(['status' => 'enabled', 'max_active_bookings' => 0, 'time_block_bookable_max' => 0, 'range_book' => 0]);
        $user = User::factory()->create();

        foreach ([$courtA, $courtB] as $i => $court) {
            $booking = Booking::factory()->create(['uid' => $user->uid, 'sid' => $court->sid, 'status' => 'single']);
            Reservation::factory()->create([
                'bid' => $booking->bid,
                'date' => Carbon::now()->addDays($i + 5)->toDateString(),
                'time_start' => '10:00:00',
                'time_end' => '11:00:00',
            ]);
        }

        $validator = app(SquareValidator::class);

        foreach ([$courtA, $courtB] as $court) {
            $result = $validator->validate($court, $user, 2, Carbon::now()->addDays(10)->setTime(10, 0), Carbon::now()->addDays(10)->setTime(11, 0));
            $this->assertFalse($result->isValid());
            $this->assertSame(__('booking.messages.max_active_bookings_reached'), $result->getError());
        }
    }

    #[Test]
    public function the_migration_seeds_the_smallest_positive_court_value_and_keeps_an_existing_option(): void
    {
        Square::factory()->create(['max_active_bookings' => 0]);
        Square::factory()->create(['max_active_bookings' => 4]);
        Square::factory()->create(['max_active_bookings' => 2]);
        Option::where('key', 'booking.max_active_bookings')->delete();

        $migration = require base_path('database/migrations/2026_11_06_000001_seed_global_max_active_bookings_option.php');
        $migration->up();
        $this->assertSame('2', Option::getValue('booking.max_active_bookings'));

        DB::table('bs_options')->where('key', 'booking.max_active_bookings')->update(['value' => '7']);
        $migration->up();
        $this->assertSame('7', Option::getValue('booking.max_active_bookings'));
    }
}
