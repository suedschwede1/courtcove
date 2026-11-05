<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SplitUserRoleCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, User> */
    private function seedUsers(): array
    {
        return [
            'admin' => User::factory()->create(['status' => 'admin']),
            'assist' => User::factory()->create(['status' => 'assist']),
            'enabled' => User::factory()->create(['status' => 'enabled']),
            'blocked' => User::factory()->create(['status' => 'blocked']),
            'deleted' => User::factory()->create(['status' => 'deleted']),
        ];
    }

    #[Test]
    public function it_refuses_to_run_while_the_flag_is_off(): void
    {
        config(['users.split_role' => false]);
        $users = $this->seedUsers();

        $this->artisan('users:split-role')
            ->expectsOutputToContain('USERS_SPLIT_ROLE')
            ->assertFailed();

        $this->assertSame('admin', $users['admin']->fresh()->status);
    }

    #[Test]
    public function the_flag_guard_also_applies_to_a_dry_run(): void
    {
        config(['users.split_role' => false]);

        $this->artisan('users:split-role --dry-run')->assertFailed();
    }

    #[Test]
    public function dry_run_only_reports_counts(): void
    {
        config(['users.split_role' => true]);
        $users = $this->seedUsers();

        $this->artisan('users:split-role --dry-run')
            ->expectsOutputToContain('admin: 1')
            ->expectsOutputToContain('assist: 1')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame('admin', $users['admin']->fresh()->status);
        $this->assertSame('member', $users['admin']->fresh()->role);
    }

    #[Test]
    public function it_moves_privileged_statuses_into_the_role_column(): void
    {
        config(['users.split_role' => true]);
        $users = $this->seedUsers();
        $users['assist']->syncPrivileges(['admin.pyramid']);

        $this->artisan('users:split-role')
            ->expectsOutputToContain('Updated 2')
            ->assertSuccessful();

        $expected = [
            'admin' => ['enabled', 'admin'],
            'assist' => ['enabled', 'assist'],
            'enabled' => ['enabled', 'member'],
            'blocked' => ['blocked', 'member'],
            'deleted' => ['deleted', 'member'],
        ];
        foreach ($expected as $key => [$status, $role]) {
            $fresh = $users[$key]->fresh();
            $this->assertSame($status, $fresh->status, $key);
            $this->assertSame($role, $fresh->role, $key);
        }
        $this->assertTrue($users['admin']->fresh()->can('admin.config'));
        $this->assertTrue($users['assist']->fresh()->can('admin.pyramid'));
    }

    #[Test]
    public function a_second_run_changes_nothing(): void
    {
        config(['users.split_role' => true]);
        $users = $this->seedUsers();

        $this->artisan('users:split-role')->assertSuccessful();
        $this->artisan('users:split-role')
            ->expectsOutputToContain('Updated 0')
            ->assertSuccessful();

        $this->assertSame('admin', $users['admin']->fresh()->role);
        $this->assertSame('enabled', $users['admin']->fresh()->status);
    }
}
