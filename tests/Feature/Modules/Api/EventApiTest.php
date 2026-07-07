<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Api;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Sanctum\Sanctum;
use Modules\Api\Mcp\Tools\CreateEventTool;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function rest_event_creation_rejects_unknown_square_id(): void
    {
        Sanctum::actingAs(User::factory()->create(['status' => 'admin']));

        $this->postJson('/api/v1/events', [
            'sid' => 999,
            'name' => 'Invalid square event',
            'date_start' => '2026-07-10',
            'time_start' => '10:00',
            'date_end' => '2026-07-10',
            'time_end' => '12:00',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('sid');

        $this->assertSame(0, Event::count());
    }

    #[Test]
    public function mcp_event_creation_rejects_unknown_square_id(): void
    {
        Sanctum::actingAs(User::factory()->create(['status' => 'admin']));

        $this->expectException(ValidationException::class);

        app(CreateEventTool::class)->handle(new McpRequest([
            'sid' => 999,
            'name' => 'Invalid square event',
            'date_start' => '2026-07-10',
            'time_start' => '10:00',
            'date_end' => '2026-07-10',
            'time_end' => '12:00',
        ]));
    }
}
