<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerSuggestionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_cannot_query_players(): void
    {
        $this->getJson('/bookings/players?q=ab')->assertStatus(401);
    }

    #[Test]
    public function returns_matching_active_aliases(): void
    {
        $u = User::factory()->create(['status' => 'enabled']);
        User::factory()->create(['alias' => 'Roger Federer', 'status' => 'enabled']);
        User::factory()->create(['alias' => 'Rafael Nadal', 'status' => 'enabled']);
        User::factory()->create(['alias' => 'Roger Geloescht', 'status' => 'deleted']);

        $resp = $this->actingAs($u)->getJson('/bookings/players?q=Rog')->assertOk();
        $aliases = $resp->json();
        $this->assertContains('Roger Federer', $aliases);
        $this->assertNotContains('Rafael Nadal', $aliases);
        $this->assertNotContains('Roger Geloescht', $aliases); // deleted excluded
    }

    #[Test]
    public function short_query_returns_empty(): void
    {
        $u = User::factory()->create();
        User::factory()->create(['alias' => 'Roger Federer']);
        $this->actingAs($u)->getJson('/bookings/players?q=R')->assertOk()->assertExactJson([]);
    }
}
