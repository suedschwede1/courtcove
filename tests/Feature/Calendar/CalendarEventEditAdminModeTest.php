<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Models\Event;
use App\Models\EventMeta;
use App\Models\Square;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CalendarEventEditAdminModeTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function calendar(bool $adminMode): string
    {
        Carbon::setTestNow('2026-11-04 10:00');
        $court = Square::factory()->create(['name' => '1', 'priority' => 1]);
        $event = Event::factory()->create([
            'sid' => $court->sid,
            'status' => 'enabled',
            'datetime_start' => Carbon::parse('2026-11-04 14:00'),
            'datetime_end' => Carbon::parse('2026-11-04 16:00'),
        ]);
        EventMeta::create(['eid' => $event->eid, 'key' => 'name', 'value' => 'Example Training']);

        $admin = User::factory()->create(['status' => 'admin']);
        $request = $this->actingAs($admin);
        if ($adminMode) {
            $request = $request->withUnencryptedCookie('admin_mode', '1');
        }

        return $request->get('/calendar?d=2026-11-04')->assertOk()->getContent();
    }

    #[Test]
    public function an_admin_without_admin_mode_gets_no_event_edit_link(): void
    {
        $html = $this->calendar(false);

        $this->assertStringNotContainsString('event-cell--editable', $html);
        $this->assertStringNotContainsString('/admin/events/', $html);
    }

    #[Test]
    public function an_admin_with_admin_mode_gets_the_event_edit_link(): void
    {
        $html = $this->calendar(true);

        $this->assertStringContainsString('event-cell--editable', $html);
    }
}
