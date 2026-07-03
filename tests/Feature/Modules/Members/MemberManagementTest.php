<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function admin_members_is_a_registered_privilege(): void
    {
        $this->assertContains('admin.members', User::PRIVILEGES);
    }

    #[Test]
    public function admin_holds_the_members_privilege(): void
    {
        $this->assertTrue($this->admin()->can('admin.members'));
    }

    #[Test]
    public function a_regular_enabled_user_does_not_hold_the_members_privilege(): void
    {
        $user = User::factory()->create(['status' => 'enabled']);

        $this->assertFalse($user->can('admin.members'));
    }

    #[Test]
    public function a_non_admin_cannot_access_the_members_area(): void
    {
        $user = User::factory()->create(['status' => 'enabled']);

        $this->actingAs($user)->get('/members')->assertForbidden();
    }

    #[Test]
    public function index_lists_active_members_and_hides_departed_ones(): void
    {
        \App\Modules\Members\Models\Member::factory()->create(['firstname' => 'Aktiv', 'lastname' => 'Mitglied']);
        \App\Modules\Members\Models\Member::factory()->create(['firstname' => 'Ausgetreten', 'lastname' => 'Mitglied', 'left_at' => '2025-01-01']);

        $this->actingAs($this->admin())->get('/members')
            ->assertOk()->assertSee('Aktiv Mitglied')->assertDontSee('Ausgetreten Mitglied');
    }

    #[Test]
    public function admin_can_create_a_member(): void
    {
        $this->actingAs($this->admin())->post('/members', [
            'member_number' => 1,
            'firstname' => 'Neu',
            'lastname' => 'Mitglied',
            'email' => 'neu@example.com',
            'joined_at' => '2026-01-01',
        ])->assertRedirect(route('members.index'));

        $this->assertDatabaseHas('members', ['member_number' => 1, 'firstname' => 'Neu', 'lastname' => 'Mitglied']);
    }

    #[Test]
    public function create_form_prefills_the_next_free_member_number(): void
    {
        \App\Modules\Members\Models\Member::factory()->create(['member_number' => 5]);

        $this->actingAs($this->admin())->get('/members/create')
            ->assertOk()->assertSee('value="6"', false);
    }

    #[Test]
    public function creating_a_member_with_a_duplicate_number_fails_validation(): void
    {
        \App\Modules\Members\Models\Member::factory()->create(['member_number' => 7]);

        $this->actingAs($this->admin())->post('/members', [
            'member_number' => 7,
            'firstname' => 'Doppelt',
            'lastname' => 'Vergeben',
            'joined_at' => '2026-01-01',
        ])->assertSessionHasErrors('member_number');
    }

    #[Test]
    public function admin_can_mark_a_member_as_departed(): void
    {
        $member = \App\Modules\Members\Models\Member::factory()->create();

        $this->actingAs($this->admin())->delete("/members/{$member->id}")
            ->assertRedirect(route('members.index'));

        $this->assertNotNull($member->fresh()->left_at);
    }
}
