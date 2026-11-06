<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\Member;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every "active members" figure of the administration and the members module, on one
 * club with accounts in every status and club members linked to such accounts.
 * Placeholder accounts never count as active, in both storages of users.split_role.
 */
class ActiveMemberCountTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $admin;

    /** @return array<string, array{bool}> */
    public static function flags(): array
    {
        return ['split_role off' => [false], 'split_role on' => [true]];
    }

    private function makeUser(bool $flag, string $alias, string $status, string $role = 'member'): User
    {
        $attributes = $flag
            ? ['status' => $status, 'role' => $role]
            : ['status' => $status === 'enabled' && $role !== 'member' ? $role : $status];

        return User::factory()->create($attributes + ['alias' => $alias]);
    }

    /**
     * Accounts: admin, assist and two members enabled, two placeholders, one each disabled
     * (waiting for activation), blocked and deleted. Club members: six with status
     * aktiv (without account, linked to an enabled, placeholder, disabled, blocked and
     * deleted account), two ruhend (one linked to a placeholder) and one ausgetreten.
     */
    private function seedClub(bool $flag): void
    {
        config(['users.split_role' => $flag]);

        $this->admin = $this->makeUser($flag, 'Admin Anna', 'enabled', 'admin');
        $assist = $this->makeUser($flag, 'Assist Arno', 'enabled', 'assist');
        $enabled = $this->makeUser($flag, 'Aktiv Alma', 'enabled');
        $enabledToo = $this->makeUser($flag, 'Aktiv Bodo', 'enabled');
        $placeholder = $this->makeUser($flag, 'Platzhalter Paul', 'placeholder');
        $placeholderToo = $this->makeUser($flag, 'Platzhalter Petra', 'placeholder');
        $disabled = $this->makeUser($flag, 'Wartend Wim', 'disabled');
        $blocked = $this->makeUser($flag, 'Gesperrt Gerd', 'blocked');
        $deleted = $this->makeUser($flag, 'Geloescht Gina', 'deleted');

        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $member = fn (array $attributes) => Member::factory()->create($attributes + [
            'birthdate' => now()->subYears(40)->toDateString(),
            'fee_category_id' => $category->id,
        ]);

        $member(['user_id' => null]);
        $member(['user_id' => $enabled->uid]);
        $member(['user_id' => $placeholder->uid]);
        $member(['user_id' => $disabled->uid]);
        $member(['user_id' => $blocked->uid]);
        $member(['user_id' => $deleted->uid]);
        $member(['user_id' => $assist->uid, 'status' => 'ruhend']);
        $member(['user_id' => $placeholderToo->uid, 'status' => 'ruhend']);
        $member(['user_id' => $enabledToo->uid, 'status' => 'ausgetreten', 'left_at' => '2025-01-01']);
    }

    #[Test]
    #[DataProvider('flags')]
    public function admin_dashboard_counts_enabled_accounts_only(bool $flag): void
    {
        $this->seedClub($flag);

        $response = $this->actingAs($this->admin)->get('/admin')->assertOk();

        $this->assertSame(4, $response->viewData('activeMembersCount'));
        $this->assertSame(1, $response->viewData('adminCount'));
    }

    #[Test]
    #[DataProvider('flags')]
    public function statistics_list_enabled_accounts_only(bool $flag): void
    {
        $this->seedClub($flag);

        $stats = $this->actingAs($this->admin)->get('/admin/statistics?searched=1')->assertOk()->viewData('stats');

        $this->assertSame(['Admin Anna', 'Aktiv Alma', 'Aktiv Bodo', 'Assist Arno'], $stats->pluck('alias')->all());
    }

    #[Test]
    #[DataProvider('flags')]
    public function user_list_total_hides_placeholders_unless_asked(bool $flag): void
    {
        $this->seedClub($flag);
        $total = fn (string $query): int => $this->actingAs($this->admin)->get('/admin/users?'.$query)->assertOk()->viewData('users')->total();

        $this->assertSame(6, $total('q='));
        $this->assertSame(8, $total('placeholders=1'));
        $this->assertSame(4, $total('status=enabled'));
        $this->assertSame(2, $total('status=placeholder'));
    }

    #[Test]
    #[DataProvider('flags')]
    public function members_overview_does_not_count_members_linked_to_a_placeholder_as_active(bool $flag): void
    {
        $this->seedClub($flag);

        $response = $this->actingAs($this->admin)->get('/members/dashboard')->assertOk();

        // Aktiv: status aktiv, without account or linked to any account but a placeholder.
        $this->assertSame(5, $response->viewData('activeCount'));
        $this->assertSame(5, array_sum($response->viewData('ageStructure')['buckets']) + $response->viewData('ageStructure')['unknown']);
        $this->assertSame(['Vollmitglied' => 5], $response->viewData('categoryBreakdown'));
        // Unchanged figures: Mitglieder (aktiv + ruhend), ruhend, without account.
        $this->assertSame(8, $response->viewData('memberCount'));
        $this->assertSame(2, $response->viewData('dormantCount'));
        $this->assertSame(1, $response->viewData('withoutAccountCount'));
    }
}
