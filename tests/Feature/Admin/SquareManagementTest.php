<?php
declare(strict_types=1);
namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Square;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SquareManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User { return User::factory()->create(['status' => 'admin']); }

    /** Vollständige, gültige Formular-Eingabe; Overrides per $overrides. */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Center', 'alias' => 'Court B', 'status' => 'enabled',
            'readonly_message' => '', 'priority' => 1, 'capacity' => 2,
            'capacity_ask_names' => '', 'name_visibility' => 'private',
            'time_start' => '08:00', 'time_end' => '22:00',
            'time_block' => 60, 'time_block_bookable' => 30, 'time_block_bookable_max' => 180,
            'min_range_book' => 0, 'range_book' => 56, 'max_active_bookings' => 0,
            'range_cancel' => 24, 'label_free' => 'frei',
        ], $overrides);
    }

    #[Test]
    public function regular_member_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'enabled']))
            ->get('/admin/squares')->assertForbidden();
    }

    #[Test]
    public function admin_sees_square_list(): void
    {
        $square = Square::factory()->create(['name' => 'Center']);
        $square->setMeta('alias', 'Court B');

        $this->actingAs($this->admin())->get('/admin/squares')
            ->assertOk()->assertSee('Plätze')->assertSee('Court B');
    }
}
