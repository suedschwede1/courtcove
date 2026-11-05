<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\MixedTournament;

use App\Models\User;
use App\Modules\MixedTournament\Domain\EventService;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedEventGrant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): EventService
    {
        return $this->app->make(EventService::class);
    }

    #[Test]
    public function create_event_stores_mode_and_defaults(): void
    {
        $event = $this->service()->create('Beispielturnier', '2027-04-10', 'mexicano');

        $row = MixedEvent::query()->findOrFail($event->id);
        $this->assertSame('Beispielturnier', $row->title);
        $this->assertSame('2027-04-10', $row->event_date);
        $this->assertSame('mexicano', $row->mode);
        $this->assertSame('draft', $row->status);
        $this->assertFalse($row->is_default);
        $this->assertFalse($row->is_test);
        $this->assertSame(4, $row->court_count);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row->created);
    }

    #[Test]
    public function invalid_mode_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid tournament mode');

        $this->service()->create('Beispielturnier', '2027-04-10', 'swiss');
    }

    #[Test]
    public function exactly_one_default_event(): void
    {
        $a = $this->service()->create('A', '2027-04-10', 'americano');
        $b = $this->service()->create('B', '2027-04-11', 'americano');

        $this->service()->setDefault($a);
        $this->assertSame($a->id, $this->service()->defaultEvent()?->id);

        $this->service()->setDefault($b);

        $this->assertSame($b->id, $this->service()->defaultEvent()?->id);
        $this->assertSame(1, MixedEvent::query()->where('is_default', 1)->count());
        $this->assertFalse($a->fresh()->is_default);
    }

    #[Test]
    public function no_default_event_is_null(): void
    {
        $this->assertNull($this->service()->defaultEvent());
    }

    #[Test]
    public function grant_upsert_never_duplicates(): void
    {
        $event = $this->service()->create('A', '2027-04-10', 'americano');
        $user = User::factory()->create();

        $this->service()->setGrant($event, (int) $user->uid, 'score');
        $this->service()->setGrant($event, (int) $user->uid, 'admin');

        $this->assertSame(1, MixedEventGrant::query()->where('event_id', $event->id)->count());
        $this->assertSame('admin', $this->service()->grant($event, (int) $user->uid)?->role);
    }

    #[Test]
    public function unknown_grant_role_throws(): void
    {
        $event = $this->service()->create('A', '2027-04-10', 'americano');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown grant role');

        $this->service()->setGrant($event, 1, 'read');
    }

    #[Test]
    public function grant_is_per_event(): void
    {
        $a = $this->service()->create('A', '2027-04-10', 'americano');
        $b = $this->service()->create('B', '2027-04-11', 'americano');
        $user = User::factory()->create();

        $this->service()->setGrant($a, (int) $user->uid, 'score');

        $this->assertNotNull($this->service()->grant($a, (int) $user->uid));
        $this->assertNull($this->service()->grant($b, (int) $user->uid));
    }

    #[Test]
    public function remove_grant(): void
    {
        $event = $this->service()->create('A', '2027-04-10', 'americano');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->service()->setGrant($event, (int) $user->uid, 'score');
        $this->service()->setGrant($event, (int) $other->uid, 'score');

        $this->service()->removeGrant($event, (int) $user->uid);

        $this->assertNull($this->service()->grant($event, (int) $user->uid));
        $this->assertNotNull($this->service()->grant($event, (int) $other->uid));
    }

    #[Test]
    public function court_count_is_at_least_one(): void
    {
        $event = $this->service()->create('A', '2027-04-10', 'americano');

        $this->service()->setCourtCount($event, 0);
        $this->assertSame(1, $event->fresh()->court_count);

        $this->service()->setCourtCount($event, 6);
        $this->assertSame(6, $event->fresh()->court_count);
    }

    #[Test]
    public function test_mode_toggles(): void
    {
        $event = $this->service()->create('A', '2027-04-10', 'americano');

        $this->service()->setTestMode($event, true);
        $this->assertTrue($event->fresh()->is_test);

        $this->service()->setTestMode($event, false);
        $this->assertFalse($event->fresh()->is_test);
    }
}
