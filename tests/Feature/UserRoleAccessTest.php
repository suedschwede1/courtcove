<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Permission and login matrix for both storage modes of the user role: merged into
 * bs_users.status (flag off, what the old system reads) or split into bs_users.role.
 */
final class UserRoleAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Builds a user for (role, status) in the storage of the given mode.
     *
     * @param  array<int, string>  $privileges
     */
    private function makeUser(bool $flag, string $role, string $status, array $privileges = []): User
    {
        $attributes = $flag
            ? ['status' => $status, 'role' => $role]
            : ['status' => $status === 'enabled' && $role !== 'member' ? $role : $status];

        $user = User::factory()->create($attributes + ['email' => uniqid('u', true).'@example.com', 'pw' => bcrypt('secret123')]);
        $user->syncPrivileges($privileges);

        return $user->fresh();
    }

    /** @return array<string, array{bool, string, string, bool, bool, bool, bool}> */
    public static function matrix(): array
    {
        // flag, role, status, can(admin.config), can(admin.pyramid) with the flag granted, privileged, login allowed
        $rows = [
            ['admin', 'enabled', true, true, true, true],
            ['assist', 'enabled', false, true, true, true],
            ['member', 'enabled', false, false, false, true],
            ['member', 'blocked', false, false, false, false],
            ['member', 'disabled', false, false, false, false],
            ['member', 'deleted', false, false, false, false],
            ['member', 'placeholder', false, false, false, false],
        ];
        // In split storage a role can sit on a non-enabled account: it must not grant anything.
        $splitOnly = [
            ['admin', 'blocked', false, false, false, false],
            ['admin', 'disabled', false, false, false, false],
            ['assist', 'blocked', false, false, false, false],
            ['assist', 'disabled', false, false, false, false],
        ];

        $cases = [];
        foreach ([false, true] as $flag) {
            foreach (array_merge($rows, $flag ? $splitOnly : []) as [$role, $status, $config, $pyramid, $privileged, $login]) {
                $cases[($flag ? 'on' : 'off')." {$role}/{$status}"] = [$flag, $role, $status, $config, $pyramid, $privileged, $login];
            }
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('matrix')]
    public function can_privileged_and_login_follow_role_and_status(
        bool $flag, string $role, string $status, bool $config, bool $pyramid, bool $privileged, bool $login,
    ): void {
        config(['users.split_role' => $flag]);
        $user = $this->makeUser($flag, $role, $status, ['admin.pyramid']);

        $this->assertSame($config, $user->can('admin.config'));
        $this->assertSame($pyramid, $user->can('admin.pyramid'));
        $this->assertSame($privileged, $user->isPrivileged());
        $this->assertSame($login, $user->isEnabled());

        $this->post('/login', ['email' => $user->email, 'password' => 'secret123']);
        $login ? $this->assertAuthenticated() : $this->assertGuest();
    }

    #[Test]
    public function an_unsplit_privileged_status_still_counts_with_the_flag_on(): void
    {
        config(['users.split_role' => true]);
        $admin = User::factory()->create(['status' => 'admin']);
        $assist = User::factory()->create(['status' => 'assist']);
        $assist->syncPrivileges(['admin.pyramid']);

        $this->assertTrue($admin->can('admin.config'));
        $this->assertTrue($admin->isEnabled());
        $this->assertSame('enabled', $admin->accountStatus());
        $this->assertTrue($assist->fresh()->can('admin.pyramid'));
        $this->assertSame('assist', $assist->effectiveRole());
    }

    #[Test]
    #[DataProvider('flags')]
    public function the_active_scope_counts_enabled_accounts_of_every_role(bool $flag): void
    {
        config(['users.split_role' => $flag]);
        $this->makeUser($flag, 'admin', 'enabled');
        $this->makeUser($flag, 'assist', 'enabled');
        $this->makeUser($flag, 'member', 'enabled');
        foreach (['blocked', 'disabled', 'deleted', 'placeholder'] as $status) {
            $this->makeUser($flag, 'member', $status);
        }

        $this->assertSame(3, User::query()->active()->count());
        $this->assertSame(1, User::query()->withRole('admin')->count());
        $this->assertSame(1, User::query()->withRole('assist')->count());
        $this->assertSame(5, User::query()->withRole('member')->count());
        $this->assertSame(3, User::query()->withStatus('enabled')->count());
        $this->assertSame(1, User::query()->withStatus('blocked')->count());
    }

    #[Test]
    public function with_the_flag_on_a_blocked_admin_is_neither_active_nor_admin(): void
    {
        config(['users.split_role' => true]);
        User::factory()->create(['status' => 'blocked', 'role' => 'admin']);

        $this->assertSame(0, User::query()->active()->count());
        $this->assertSame(0, User::query()->withRole('admin')->count());
        $this->assertSame(1, User::query()->withRole('member')->count());
    }

    #[Test]
    #[DataProvider('flags')]
    public function the_dashboard_counts_active_members_and_admins(bool $flag): void
    {
        config(['users.split_role' => $flag]);
        $admin = $this->makeUser($flag, 'admin', 'enabled');
        $this->makeUser($flag, 'member', 'enabled');
        $this->makeUser($flag, 'member', 'blocked');

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertViewHas('activeMembersCount', 2)
            ->assertViewHas('adminCount', 1);
    }

    #[Test]
    #[DataProvider('flags')]
    public function the_user_list_filters_by_status_and_role(bool $flag): void
    {
        config(['users.split_role' => $flag]);
        $admin = $this->makeUser($flag, 'admin', 'enabled');
        $admin->update(['alias' => 'Signed In']);
        $this->makeUser($flag, 'admin', 'enabled')->update(['alias' => 'Ada Admin']);
        $this->makeUser($flag, 'assist', 'enabled')->update(['alias' => 'Abe Assist']);
        $this->makeUser($flag, 'member', 'enabled')->update(['alias' => 'Mia Member']);
        $this->makeUser($flag, 'member', 'blocked')->update(['alias' => 'Bo Blocked']);

        $this->actingAs($admin)->get('/admin/users?status=enabled')
            ->assertOk()->assertSee('Ada Admin')->assertSee('Abe Assist')->assertSee('Mia Member')->assertDontSee('Bo Blocked');

        $this->actingAs($admin)->get('/admin/users?role=assist')
            ->assertOk()->assertSee('Abe Assist')->assertDontSee('Ada Admin')->assertDontSee('Mia Member');

        $this->actingAs($admin)->get('/admin/users?role=member&status=blocked')
            ->assertOk()->assertSee('Bo Blocked')->assertDontSee('Mia Member');
    }

    #[Test]
    #[DataProvider('flags')]
    public function bulk_actions_never_touch_admins_nor_demote_assistants(bool $flag): void
    {
        config(['users.split_role' => $flag]);
        $actor = $this->makeUser($flag, 'admin', 'enabled');
        $otherAdmin = $this->makeUser($flag, 'admin', 'enabled');
        $assist = $this->makeUser($flag, 'assist', 'enabled', ['admin.pyramid']);
        $waiting = $this->makeUser($flag, 'member', 'disabled');

        $this->actingAs($actor)->post('/admin/users/bulk', [
            'uids' => [$otherAdmin->uid, $assist->uid, $waiting->uid], 'action' => 'enabled',
        ])->assertRedirect();

        $this->assertTrue($otherAdmin->fresh()->can('admin.config'));
        $this->assertTrue($assist->fresh()->can('admin.pyramid'));
        $this->assertTrue($waiting->fresh()->isEnabled());

        $this->actingAs($actor)->post('/admin/users/bulk', [
            'uids' => [$otherAdmin->uid, $waiting->uid], 'action' => 'blocked',
        ])->assertRedirect();

        $this->assertTrue($otherAdmin->fresh()->isEnabled());
        $this->assertSame('blocked', $waiting->fresh()->accountStatus());
    }

    /** @return array<string, array{bool}> */
    public static function flags(): array
    {
        return ['flag off' => [false], 'flag on' => [true]];
    }
}
