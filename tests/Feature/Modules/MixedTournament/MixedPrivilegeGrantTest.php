<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\MixedTournament;

use App\Models\User;
use App\Modules\MixedTournament\Policies\MixedEventPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MixedPrivilegeGrantTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function admin_mixed_is_offered_in_the_user_admin(): void
    {
        $admin = User::factory()->status('admin')->create();
        $assist = User::factory()->status('assist')->create();

        $this->actingAs($admin)->get(route('admin.users.edit', $assist))
            ->assertOk()
            ->assertSee('value="admin.mixed"', false)
            ->assertSee('Darf das Mixed-/Fun-Turnier verwalten');
    }

    #[Test]
    public function saving_it_makes_the_assist_user_a_global_mixed_admin(): void
    {
        $admin = User::factory()->status('admin')->create();
        $assist = User::factory()->status('assist')->create();
        $this->assertFalse(MixedEventPolicy::isGlobalAdmin($assist));

        $this->actingAs($admin)->put(route('admin.users.update', $assist), [
            'alias' => $assist->alias, 'email' => $assist->email, 'status' => 'assist',
            'privileges' => ['admin.mixed'],
        ])->assertRedirect(route('admin.users.index'));

        $this->assertSame('true', $assist->fresh()->getMeta('allow.admin.mixed'));
        $this->assertTrue(MixedEventPolicy::isGlobalAdmin($assist->fresh()));
    }
}
