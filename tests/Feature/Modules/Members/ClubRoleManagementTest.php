<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\ClubRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClubRoleManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function admin_can_create_a_club_role(): void
    {
        $this->actingAs($this->admin())->post('/members/functions', [
            'name' => 'Obmann',
            'is_board' => '1',
            'tasks' => 'Vereinsleitung',
        ])->assertRedirect(route('members.functions.index'));

        $this->assertDatabaseHas('club_roles', [
            'name' => 'Obmann',
            'is_board' => true,
            'tasks' => 'Vereinsleitung',
        ]);
    }

    #[Test]
    public function creating_a_club_role_without_is_board_defaults_to_false(): void
    {
        $this->actingAs($this->admin())->post('/members/functions', [
            'name' => 'Platzwart',
        ])->assertRedirect(route('members.functions.index'));

        $this->assertDatabaseHas('club_roles', ['name' => 'Platzwart', 'is_board' => false]);
    }

    #[Test]
    public function admin_can_update_a_club_role(): void
    {
        $role = ClubRole::create(['name' => 'Kassier', 'is_board' => true]);

        $this->actingAs($this->admin())->put("/members/functions/{$role->id}", [
            'name' => 'Kassier/in',
            'is_board' => '1',
            'tasks' => 'Finanzen',
        ])->assertRedirect(route('members.functions.index'));

        $role->refresh();
        $this->assertSame('Kassier/in', $role->name);
        $this->assertSame('Finanzen', $role->tasks);
    }

    #[Test]
    public function admin_can_delete_a_club_role(): void
    {
        $role = ClubRole::create(['name' => 'Kassaprüfer', 'is_board' => true]);

        $this->actingAs($this->admin())->delete("/members/functions/{$role->id}")
            ->assertRedirect(route('members.functions.index'));

        $this->assertDatabaseMissing('club_roles', ['id' => $role->id]);
    }

    #[Test]
    public function index_lists_board_roles_before_other_roles(): void
    {
        ClubRole::create(['name' => 'Platzwart', 'is_board' => false]);
        ClubRole::create(['name' => 'Obmann', 'is_board' => true]);

        $this->actingAs($this->admin())->get('/members/functions')
            ->assertOk()->assertSeeInOrder(['Obmann', 'Platzwart']);
    }
}
