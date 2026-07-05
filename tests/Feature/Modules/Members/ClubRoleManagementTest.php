<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\ClubRole;
use App\Modules\Members\Models\MailCategory;
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
        $category = MailCategory::create(['name' => 'Vorstand']);

        $this->actingAs($this->admin())->post('/members/functions', [
            'name' => 'Obmann',
            'is_board' => '1',
            'mail_category_ids' => [$category->id],
            'tasks' => 'Vereinsleitung',
        ])->assertRedirect(route('members.functions.index'));

        $this->assertDatabaseHas('club_roles', [
            'name' => 'Obmann',
            'is_board' => true,
            'tasks' => 'Vereinsleitung',
        ]);
        $this->assertDatabaseHas('club_role_mail_category', [
            'club_role_id' => ClubRole::where('name', 'Obmann')->value('id'),
            'mail_category_id' => $category->id,
        ]);
    }

    #[Test]
    public function admin_can_assign_multiple_mail_categories_to_a_club_role(): void
    {
        $vorstand = MailCategory::create(['name' => 'Vorstand']);
        $finanzen = MailCategory::create(['name' => 'Finanzen']);

        $this->actingAs($this->admin())->post('/members/functions', [
            'name' => 'Obmann',
            'mail_category_ids' => [$vorstand->id, $finanzen->id],
        ])->assertRedirect(route('members.functions.index'));

        $role = ClubRole::where('name', 'Obmann')->first();
        $this->assertSame([$vorstand->id, $finanzen->id], $role->mailCategories->pluck('id')->sort()->values()->all());
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
        $category = MailCategory::create(['name' => 'Vorstand']);

        $this->actingAs($this->admin())->put("/members/functions/{$role->id}", [
            'name' => 'Kassier/in',
            'is_board' => '1',
            'mail_category_ids' => [$category->id],
            'tasks' => 'Finanzen',
        ])->assertRedirect(route('members.functions.index'));

        $role->refresh();
        $this->assertSame('Kassier/in', $role->name);
        $this->assertSame('Finanzen', $role->tasks);
        $this->assertSame([$category->id], $role->mailCategories->pluck('id')->all());
    }

    #[Test]
    public function updating_a_club_role_replaces_its_mail_categories(): void
    {
        $vorstand = MailCategory::create(['name' => 'Vorstand']);
        $finanzen = MailCategory::create(['name' => 'Finanzen']);
        $role = ClubRole::create(['name' => 'Kassier', 'is_board' => true]);
        $role->mailCategories()->sync([$vorstand->id]);

        $this->actingAs($this->admin())->put("/members/functions/{$role->id}", [
            'name' => 'Kassier',
            'mail_category_ids' => [$finanzen->id],
        ])->assertRedirect(route('members.functions.index'));

        $this->assertSame([$finanzen->id], $role->refresh()->mailCategories->pluck('id')->all());
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
