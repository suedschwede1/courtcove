<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Square;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SquareManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    /** Complete, valid form input; override via $overrides. */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Center', 'alias' => 'Alpha Court', 'status' => 'enabled',
            'readonly_message' => '', 'priority' => 1, 'capacity' => 2,
            'capacity_ask_names' => '', 'name_visibility' => 'private',
            'time_start' => '08:00', 'time_end' => '22:00',
            'time_block' => 60, 'time_block_bookable' => 30, 'time_block_bookable_max' => 180,
            'min_range_book' => 0, 'range_book' => 56, 'short_booking_window' => 0,
            'max_active_bookings' => 0, 'range_cancel' => 24, 'label_free' => 'frei',
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
        $square->setMeta('alias', 'Alpha Court');

        $this->actingAs($this->admin())->get('/admin/squares')
            ->assertOk()->assertSee('Plätze')->assertSee('Alpha Court');
    }

    #[Test]
    public function create_page_renders(): void
    {
        $this->actingAs($this->admin())->get('/admin/squares/create')
            ->assertOk()->assertSee('Anzeigename');
    }

    #[Test]
    public function store_creates_square_with_unit_conversions(): void
    {
        $this->actingAs($this->admin())->post('/admin/squares', $this->payload())
            ->assertRedirect(route('admin.squares.index'));

        $square = Square::where('name', 'Center')->firstOrFail();
        $this->assertSame(3600, (int) $square->time_block);               // 60 min × 60
        $this->assertSame(1800, (int) $square->time_block_bookable);      // 30 min × 60
        $this->assertSame(10800, (int) $square->time_block_bookable_max); // 180 min × 60
        $this->assertSame(56 * 86400, (int) $square->range_book);         // 56 days
        $this->assertSame(86400, (int) $square->range_cancel);            // 24 h × 3600
        $this->assertSame('Alpha Court', $square->getMeta('alias'));
        $this->assertSame('frei', $square->getMeta('label.free'));
    }

    #[Test]
    public function store_maps_name_visibility(): void
    {
        $this->actingAs($this->admin())->post('/admin/squares', $this->payload(['name' => 'Pub', 'name_visibility' => 'public']));
        $pub = Square::where('name', 'Pub')->firstOrFail();
        $this->assertSame('true', $pub->getMeta('private_names'));
        $this->assertSame('true', $pub->getMeta('public_names'));

        $this->actingAs($this->admin())->post('/admin/squares', $this->payload(['name' => 'None', 'name_visibility' => 'none']));
        $none = Square::where('name', 'None')->firstOrFail();
        $this->assertSame('false', $none->getMeta('private_names'));
        $this->assertSame('false', $none->getMeta('public_names'));
    }

    #[Test]
    public function store_requires_name(): void
    {
        $this->actingAs($this->admin())->post('/admin/squares', $this->payload(['name' => '']))
            ->assertSessionHasErrors('name');
    }

    #[Test]
    public function edit_page_reverse_converts_values(): void
    {
        $square = Square::factory()->create([
            'time_block' => 3600, 'range_book' => 56 * 86400, 'range_cancel' => 86400,
        ]);

        $this->actingAs($this->admin())->get(route('admin.squares.edit', $square))
            ->assertOk()
            ->assertSee('value="56"', false);   // range_book 56*86400 sec ÷ 86400 = 56 days
    }

    #[Test]
    public function update_changes_columns_and_meta(): void
    {
        $square = Square::factory()->create(['name' => 'Alt']);

        $this->actingAs($this->admin())
            ->put(route('admin.squares.update', $square), $this->payload(['name' => 'Neu', 'alias' => 'Beta Court']))
            ->assertRedirect(route('admin.squares.index'));

        $fresh = $square->fresh();
        $this->assertSame('Neu', $fresh->name);
        $this->assertSame('Beta Court', $fresh->getMeta('alias'));
    }

    #[Test]
    public function update_with_blank_alias_deletes_meta(): void
    {
        $square = Square::factory()->create();
        $square->setMeta('alias', 'Alpha Court');

        $this->actingAs($this->admin())
            ->put(route('admin.squares.update', $square), $this->payload(['alias' => '']))
            ->assertRedirect();

        $this->assertNull($square->fresh()->getMeta('alias'));
    }

    #[Test]
    public function destroy_deletes_square_without_bookings(): void
    {
        $square = Square::factory()->create();
        $square->setMeta('alias', 'Alpha Court');

        $this->actingAs($this->admin())->delete(route('admin.squares.destroy', $square))
            ->assertRedirect(route('admin.squares.index'));

        $this->assertDatabaseMissing('bs_squares', ['sid' => $square->sid]);
        $this->assertDatabaseMissing('bs_squares_meta', ['sid' => $square->sid]);
    }

    #[Test]
    public function destroy_disables_square_with_bookings(): void
    {
        $square = Square::factory()->create(['status' => 'enabled']);
        Booking::factory()->create(['sid' => $square->sid]);

        $this->actingAs($this->admin())->delete(route('admin.squares.destroy', $square))
            ->assertRedirect(route('admin.squares.index'));

        $this->assertDatabaseHas('bs_squares', ['sid' => $square->sid, 'status' => 'disabled']);
    }
}
