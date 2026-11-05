<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The admin user form has a separate status and permission (role) field. With the
 * split flag off both are merged into bs_users.status (legacy storage the old system
 * still reads); with the flag on the role lives in bs_users.role.
 */
final class UserRoleFormTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    /** @return array<string, array{string, string, string}> */
    public static function mergeCases(): array
    {
        return [
            'enabled admin' => ['enabled', 'admin', 'admin'],
            'enabled assist' => ['enabled', 'assist', 'assist'],
            'enabled member' => ['enabled', 'member', 'enabled'],
            'blocked member' => ['blocked', 'member', 'blocked'],
            'waiting member' => ['disabled', 'member', 'disabled'],
            'placeholder member' => ['placeholder', 'member', 'placeholder'],
            'deleted member' => ['deleted', 'member', 'deleted'],
        ];
    }

    #[Test]
    #[DataProvider('mergeCases')]
    public function flag_off_merges_status_and_role_into_the_status_column(string $status, string $role, string $stored): void
    {
        config(['users.split_role' => false]);
        $u = User::factory()->create(['status' => 'enabled']);

        $this->actingAs($this->admin())->put("/admin/users/{$u->uid}", [
            'alias' => $u->alias, 'email' => $u->email, 'status' => $status, 'role' => $role,
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $u->refresh();
        $this->assertSame($stored, $u->status);
        $this->assertSame('member', $u->role);
    }

    #[Test]
    #[DataProvider('mergeCases')]
    public function flag_on_stores_status_and_role_separately(string $status, string $role, string $mergedStatus): void
    {
        config(['users.split_role' => true]);
        $u = User::factory()->create(['status' => 'enabled']);

        $this->actingAs($this->admin())->put("/admin/users/{$u->uid}", [
            'alias' => $u->alias, 'email' => $u->email, 'status' => $status, 'role' => $role,
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $u->refresh();
        $this->assertSame($status, $u->status);
        $this->assertSame($role, $u->role);
    }

    #[Test]
    public function create_merges_like_update_with_the_flag_off(): void
    {
        config(['users.split_role' => false]);

        $this->actingAs($this->admin())->post('/admin/users', [
            'alias' => 'Neu', 'email' => 'neu@example.com', 'status' => 'enabled', 'role' => 'assist', 'password' => 'geheim123',
        ])->assertSessionHasNoErrors();

        $this->assertSame('assist', User::where('email', 'neu@example.com')->value('status'));
    }

    #[Test]
    public function create_stores_the_role_column_with_the_flag_on(): void
    {
        config(['users.split_role' => true]);

        $this->actingAs($this->admin())->post('/admin/users', [
            'alias' => 'Neu', 'email' => 'neu@example.com', 'status' => 'enabled', 'role' => 'admin', 'password' => 'geheim123',
        ])->assertSessionHasNoErrors();

        $user = User::where('email', 'neu@example.com')->firstOrFail();
        $this->assertSame('enabled', $user->status);
        $this->assertSame('admin', $user->role);
        $this->assertTrue($user->can('admin.config'));
    }

    /** @return array<string, array{bool, string, string}> */
    public static function rejectedCombinations(): array
    {
        $cases = [];
        foreach ([false, true] as $flag) {
            foreach (['blocked', 'disabled', 'placeholder', 'deleted'] as $status) {
                foreach (['admin', 'assist'] as $role) {
                    $cases[($flag ? 'on' : 'off')." {$status} {$role}"] = [$flag, $status, $role];
                }
            }
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('rejectedCombinations')]
    public function a_permission_needs_the_enabled_status(bool $flag, string $status, string $role): void
    {
        config(['users.split_role' => $flag]);
        $u = User::factory()->create(['status' => 'enabled']);

        $this->actingAs($this->admin())->put("/admin/users/{$u->uid}", [
            'alias' => $u->alias, 'email' => $u->email, 'status' => $status, 'role' => $role,
        ])->assertSessionHasErrors(['role' => __('booking.admin.users.role_requires_enabled')]);

        $this->assertSame('enabled', $u->fresh()->status);
    }

    #[Test]
    public function legacy_combined_status_values_are_no_longer_accepted(): void
    {
        $u = User::factory()->create(['status' => 'enabled']);

        $this->actingAs($this->admin())->put("/admin/users/{$u->uid}", [
            'alias' => $u->alias, 'status' => 'admin', 'role' => 'member',
        ])->assertSessionHasErrors('status');
    }

    #[Test]
    public function a_missing_role_counts_as_member(): void
    {
        config(['users.split_role' => false]);
        $u = User::factory()->create(['status' => 'assist']);

        $this->actingAs($this->admin())->put("/admin/users/{$u->uid}", [
            'alias' => $u->alias, 'status' => 'blocked',
        ])->assertSessionHasNoErrors();

        $this->assertSame('blocked', $u->fresh()->status);
    }

    /** @return array<string, array{bool}> */
    public static function flags(): array
    {
        return ['flag off' => [false], 'flag on' => [true]];
    }

    #[Test]
    #[DataProvider('flags')]
    public function privileges_are_only_kept_for_assistants(bool $flag): void
    {
        config(['users.split_role' => $flag]);
        $u = User::factory()->create(['status' => 'enabled']);
        $admin = $this->admin();

        $this->actingAs($admin)->put("/admin/users/{$u->uid}", [
            'alias' => $u->alias, 'status' => 'enabled', 'role' => 'assist', 'privileges' => ['admin.pyramid'],
        ])->assertSessionHasNoErrors();
        $this->assertTrue($u->fresh()->can('admin.pyramid'));

        $this->actingAs($admin)->put("/admin/users/{$u->uid}", [
            'alias' => $u->alias, 'status' => 'enabled', 'role' => 'member', 'privileges' => ['admin.pyramid'],
        ])->assertSessionHasNoErrors();
        $this->assertNull($u->fresh()->getMeta('allow.admin.pyramid'));

        $this->actingAs($admin)->put("/admin/users/{$u->uid}", [
            'alias' => $u->alias, 'status' => 'enabled', 'role' => 'admin', 'privileges' => ['admin.pyramid'],
        ])->assertSessionHasNoErrors();
        $this->assertSame([], $u->fresh()->grantedPrivileges());
    }

    /** @return array<string, array{bool, array<string, string>, string, string}> */
    public static function loadCases(): array
    {
        return [
            'off admin' => [false, ['status' => 'admin'], 'enabled', 'admin'],
            'off assist' => [false, ['status' => 'assist'], 'enabled', 'assist'],
            'off enabled' => [false, ['status' => 'enabled'], 'enabled', 'member'],
            'off blocked' => [false, ['status' => 'blocked'], 'blocked', 'member'],
            'off deleted' => [false, ['status' => 'deleted'], 'deleted', 'member'],
            'on enabled admin' => [true, ['status' => 'enabled', 'role' => 'admin'], 'enabled', 'admin'],
            'on blocked assist' => [true, ['status' => 'blocked', 'role' => 'assist'], 'blocked', 'member'],
            'on unsplit admin' => [true, ['status' => 'admin', 'role' => 'member'], 'enabled', 'admin'],
        ];
    }

    /** @param array<string, string> $attributes */
    #[Test]
    #[DataProvider('loadCases')]
    public function the_edit_form_splits_the_stored_values(bool $flag, array $attributes, string $status, string $role): void
    {
        config(['users.split_role' => $flag]);
        $u = User::factory()->create($attributes);

        $html = $this->actingAs($this->admin())->get("/admin/users/{$u->uid}/edit")->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<select name="status" id="uf-status"[^>]*>.*<option value="'.$status.'" selected>/sU', $html);
        $this->assertMatchesRegularExpression('/<select name="role" id="uf-role"[^>]*>.*<option value="'.$role.'" selected>/sU', $html);
    }

    #[Test]
    public function the_status_select_offers_five_states_and_the_role_select_three(): void
    {
        $u = User::factory()->create();

        $this->actingAs($this->admin())->get("/admin/users/{$u->uid}/edit")
            ->assertOk()
            ->assertSeeInOrder(['Platzhalter', 'aktiviert', 'auf Aktivierung wartend', 'gesperrt', 'gelöscht'])
            ->assertSeeInOrder(['Berechtigung', 'Mitglied', 'Assistent', 'Admin'])
            ->assertDontSee('<option value="assist" selected>', false)
            ->assertSee('x-show="role === \'assist\'"', false);
    }
}
