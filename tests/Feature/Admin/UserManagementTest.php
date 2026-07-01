<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function index_lists_non_deleted_users(): void
    {
        User::factory()->create(['alias' => 'Aktiv Mitglied', 'status' => 'enabled']);
        User::factory()->create(['alias' => 'Geloescht', 'status' => 'deleted']);

        $this->actingAs($this->admin())->get('/admin/users')
            ->assertOk()->assertSee('Aktiv Mitglied')->assertDontSee('Geloescht');
    }

    #[Test]
    public function assist_without_flag_is_forbidden(): void
    {
        $assist = User::factory()->create(['status' => 'assist']);
        $this->actingAs($assist)->get('/admin/users')->assertForbidden();
    }

    #[Test]
    public function admin_can_create_user_with_password_profile_and_privileges(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', [
            'alias' => 'Neu Mitglied', 'email' => 'neu@example.com', 'status' => 'assist',
            'password' => 'geheim123', 'firstname' => 'Neu', 'phone' => '+43123',
            'privileges' => ['admin.user', 'calendar.see-data'],
        ])->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'neu@example.com')->firstOrFail();
        $this->assertSame('assist', $user->status);
        $this->assertTrue(Hash::check('geheim123', $user->pw));
        $this->assertEquals('Neu', $user->getMeta('firstname'));
        $this->assertTrue($user->can('admin.user'));
        $this->assertTrue($user->can('calendar.see-data'));
    }

    #[Test]
    public function create_validates_unique_email_and_required_fields(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);
        $this->actingAs($this->admin())->post('/admin/users', [
            'alias' => '', 'email' => 'dup@example.com', 'status' => 'enabled', 'password' => 'x',
        ])->assertSessionHasErrors(['alias', 'email']);
    }

    #[Test]
    public function admin_can_update_user_and_toggle_privileges(): void
    {
        $u = User::factory()->create(['alias' => 'Alt', 'status' => 'assist']);
        $u->syncPrivileges(['admin.user']);

        $this->actingAs($this->admin())->put("/admin/users/{$u->uid}", [
            'alias' => 'Neu', 'email' => $u->email, 'status' => 'assist',
            'privileges' => ['calendar.see-data'],
        ])->assertRedirect(route('admin.users.index'));

        $u->refresh();
        $this->assertSame('Neu', $u->alias);
        $this->assertFalse($u->can('admin.user'));
        $this->assertTrue($u->can('calendar.see-data'));
    }

    #[Test]
    public function admin_can_reset_password(): void
    {
        $u = User::factory()->create();
        $this->actingAs($this->admin())->post("/admin/users/{$u->uid}/password", ['password' => 'neuespass1'])
            ->assertRedirect();
        $this->assertTrue(Hash::check('neuespass1', $u->fresh()->pw));
    }

    #[Test]
    public function destroy_soft_deletes_user(): void
    {
        $u = User::factory()->create(['status' => 'enabled']);
        $this->actingAs($this->admin())->delete("/admin/users/{$u->uid}")->assertRedirect(route('admin.users.index'));
        $this->assertSame('deleted', $u->fresh()->status);
    }

    #[Test]
    public function update_validates_email_uniqueness_ignoring_self(): void
    {
        $u = User::factory()->create(['email' => 'self@example.com']);
        $this->actingAs($this->admin())->put("/admin/users/{$u->uid}", [
            'alias' => 'Self', 'email' => 'self@example.com', 'status' => 'enabled',
        ])->assertRedirect(route('admin.users.index')); // own email allowed
    }

    #[Test]
    public function update_leaves_quote_group_untouched_when_field_is_absent(): void
    {
        $u = User::factory()->create();
        $u->setMeta('quote_group', 'a_group_that_no_longer_exists');

        $this->actingAs($this->admin())->put("/admin/users/{$u->uid}", [
            'alias' => $u->alias, 'status' => 'enabled',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertSame('a_group_that_no_longer_exists', $u->fresh()->getMeta('quote_group'));
    }

    #[Test]
    public function update_accepts_resubmitting_a_stale_quote_group_value(): void
    {
        $u = User::factory()->create();
        $u->setMeta('quote_group', 'a_group_that_no_longer_exists');

        $this->actingAs($this->admin())->put("/admin/users/{$u->uid}", [
            'alias' => $u->alias, 'status' => 'enabled', 'quote_group' => 'a_group_that_no_longer_exists',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $this->assertSame('a_group_that_no_longer_exists', $u->fresh()->getMeta('quote_group'));
    }

    #[Test]
    public function update_clears_quote_group_when_explicitly_submitted_empty(): void
    {
        $u = User::factory()->create();
        $u->setMeta('quote_group', 'a_group_that_no_longer_exists');

        $this->actingAs($this->admin())->put("/admin/users/{$u->uid}", [
            'alias' => $u->alias, 'status' => 'enabled', 'quote_group' => '',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertNull($u->fresh()->getMeta('quote_group'));
    }
}
