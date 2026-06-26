<?php
declare(strict_types=1);
namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminBookingTest extends TestCase
{
    use RefreshDatabase;
    private function admin(): User { return User::factory()->create(['status' => 'admin']); }

    #[Test]
    public function regular_member_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'enabled']))->get('/admin/bookings')->assertForbidden();
    }

    #[Test]
    public function index_lists_active_bookings(): void
    {
        $owner = User::factory()->create(['alias' => 'Bucher Mitglied']);
        $b = Booking::factory()->create(['uid' => $owner->uid, 'status' => 'single']);
        Reservation::factory()->create(['bid' => $b->bid, 'date' => Carbon::today()->toDateString()]);

        $this->actingAs($this->admin())->get('/admin/bookings')
            ->assertOk()->assertSee('Bucher Mitglied');
    }

    #[Test]
    public function index_excludes_cancelled_bookings(): void
    {
        $owner = User::factory()->create(['alias' => 'Storno Mitglied']);
        Booking::factory()->create(['uid' => $owner->uid, 'status' => 'cancelled']);

        $this->actingAs($this->admin())->get('/admin/bookings')->assertOk()->assertDontSee('Storno Mitglied');
    }

    #[Test]
    public function admin_can_cancel_any_booking(): void
    {
        $b = Booking::factory()->create(['status' => 'single']);
        $this->actingAs($this->admin())->delete("/admin/bookings/{$b->bid}")->assertRedirect(route('admin.bookings.index'));
        $this->assertSame('cancelled', $b->fresh()->status);
    }
}
