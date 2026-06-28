<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\Square;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function regular_member_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'enabled']))->get('/admin/events')->assertForbidden();
    }

    #[Test]
    public function admin_can_create_event_for_a_square(): void
    {
        $square = Square::factory()->create();
        $this->actingAs($this->admin())->post('/admin/events', [
            'sid' => $square->sid, 'name' => 'Stadtmeisterschaft', 'status' => 'enabled',
            'datetime_start' => '2026-07-01 10:00', 'datetime_end' => '2026-07-01 18:00',
        ])->assertRedirect(route('admin.events.index'));

        $event = Event::firstOrFail();
        $this->assertSame((int) $square->sid, (int) $event->sid);
        $this->assertEquals('Stadtmeisterschaft', $event->meta()->where('key', 'name')->value('value'));
    }

    #[Test]
    public function event_create_allows_all_squares_when_sid_blank(): void
    {
        $this->actingAs($this->admin())->post('/admin/events', [
            'sid' => '', 'name' => 'Wartung', 'status' => 'enabled',
            'datetime_start' => '2026-07-01 10:00', 'datetime_end' => '2026-07-01 12:00',
        ])->assertRedirect();
        $this->assertNull(Event::firstOrFail()->sid);
    }

    #[Test]
    public function create_validates_end_after_start(): void
    {
        $this->actingAs($this->admin())->post('/admin/events', [
            'sid' => '', 'name' => 'X', 'status' => 'enabled',
            'datetime_start' => '2026-07-01 12:00', 'datetime_end' => '2026-07-01 10:00',
        ])->assertSessionHasErrors('datetime_end');
    }

    #[Test]
    public function admin_can_update_event_name(): void
    {
        $event = Event::factory()->create(['status' => 'enabled']);
        $event->meta()->create(['key' => 'name', 'value' => 'Alt']);
        $this->actingAs($this->admin())->put("/admin/events/{$event->eid}", [
            'sid' => $event->sid, 'name' => 'Neu', 'status' => 'enabled',
            'datetime_start' => '2026-07-01 10:00', 'datetime_end' => '2026-07-01 12:00',
        ])->assertRedirect(route('admin.events.index'));
        $this->assertEquals('Neu', $event->fresh()->meta()->where('key', 'name')->value('value'));
    }

    #[Test]
    public function admin_can_delete_event(): void
    {
        $event = Event::factory()->create();
        $this->actingAs($this->admin())->delete("/admin/events/{$event->eid}")->assertRedirect(route('admin.events.index'));
        $this->assertDatabaseMissing('bs_events', ['eid' => $event->eid]);
    }

    #[Test]
    public function create_form_pre_fills_date_and_time_from_query_params(): void
    {
        Square::factory()->create();
        $response = $this->actingAs($this->admin())->get('/admin/events/create?sid=1&date_start=2026-07-05&time_start=10:00&date_end=2026-07-05&time_end=12:00');
        $response->assertOk();
        $response->assertSee('value="2026-07-05"', false);
        $response->assertSee('value="10:00"', false);
        $response->assertSee('value="12:00"', false);
    }
}
