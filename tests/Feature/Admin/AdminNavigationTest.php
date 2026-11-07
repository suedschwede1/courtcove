<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Members\Models\Member;
use App\Services\AdminNavigation;
use App\Support\Navigation\AreaNavGroup;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AdminNavigationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return list<AreaNavGroup>
     */
    private function groupsFor(User $user, string $url, string $method = 'GET'): array
    {
        $request = Request::create($url, $method);
        $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));

        return app(AdminNavigation::class)->groups($user, $request);
    }

    /**
     * @param  list<AreaNavGroup>  $groups
     * @return list<string>
     */
    private function activeKeys(array $groups): array
    {
        $keys = [];
        foreach ($groups as $group) {
            foreach ($group->items as $item) {
                if ($item->active) {
                    $keys[] = $item->key;
                }
            }
        }

        return $keys;
    }

    #[Test]
    public function admin_sees_all_groups_in_order(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $groups = $this->groupsFor($admin, route('admin.dashboard'));

        $this->assertSame(['operations', 'users', 'tournaments', 'settings'], array_map(fn ($g) => $g->key, $groups));
        $this->assertSame(
            [
                ['dashboard', 'bookings', 'events', 'statistics'],
                ['users', 'login_history'],
                ['pyramid', 'championship', 'draws', 'mixed'],
                ['squares', 'texts', 'behavior', 'database', 'scheduler', 'api_tokens', 'testmail'],
            ],
            array_map(fn ($g) => array_map(fn ($i) => $i->key, $g->items), $groups),
        );
        $this->assertSame(['dashboard'], $this->activeKeys($groups));

        app()->setLocale('de');
        $groups = $this->groupsFor($admin, route('admin.dashboard'));
        $this->assertSame(['Betrieb', 'Benutzer', 'Turniere', 'Einstellungen'], array_map(fn ($g) => $g->label, $groups));
        $this->assertSame('Übersicht', $groups[0]->items[0]->label);
        $this->assertSame(['Benutzerkonten', 'Anmeldeverlauf'], array_map(fn ($i) => $i->label, $groups[1]->items));
    }

    #[Test]
    public function no_entry_leads_into_the_members_module(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $member = Member::factory()->create();

        foreach ([route('admin.dashboard'), route('members.dashboard'), route('members.edit', $member), route('members.dsgvo.index')] as $url) {
            $groups = $this->groupsFor($admin, $url);
            foreach ($groups as $group) {
                foreach ($group->items as $item) {
                    $this->assertStringNotContainsString('/members', $item->url, $url);
                }
            }
            if ($url !== route('admin.dashboard')) {
                $this->assertSame([], $this->activeKeys($groups), $url);
            }
        }
    }

    #[Test]
    public function assist_sees_only_granted_items(): void
    {
        $assist = User::factory()->create(['status' => 'assist']);
        $assist->syncPrivileges(['admin.see-menu', 'admin.booking']);

        $groups = $this->groupsFor($assist->fresh(), route('admin.dashboard'));

        $this->assertSame(['operations'], array_map(fn ($g) => $g->key, $groups));
        $this->assertSame(['dashboard', 'bookings', 'statistics'], array_map(fn ($i) => $i->key, $groups[0]->items));
    }

    #[Test]
    public function active_item_follows_the_route(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $other = User::factory()->create();

        $this->assertSame(['users'], $this->activeKeys($this->groupsFor($admin, route('admin.users.edit', $other))));
        $this->assertSame(['texts'], $this->activeKeys($this->groupsFor($admin, route('admin.config.update'), 'PUT')));
        $this->assertSame(['behavior'], $this->activeKeys($this->groupsFor($admin, route('admin.config.behavior.edit'))));
    }

    #[Test]
    public function the_users_group_needs_the_user_privilege_only(): void
    {
        $assist = User::factory()->create(['status' => 'assist']);
        $assist->syncPrivileges(['admin.see-menu', 'admin.members']);

        $groupKeys = fn (array $groups): array => array_map(fn ($g) => $g->key, $groups);
        $this->assertNotContains('users', $groupKeys($this->groupsFor($assist->fresh(), route('admin.dashboard'))));

        $assist->syncPrivileges(['admin.see-menu', 'admin.user']);
        $groups = $this->groupsFor($assist->fresh(), route('admin.dashboard'));
        $this->assertContains('users', $groupKeys($groups));
        $this->assertSame(['users', 'login_history'], array_map(fn ($i) => $i->key, collect($groups)->firstWhere('key', 'users')->items));
    }

    #[Test]
    public function tournament_entries_need_route_and_gate(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $assist = User::factory()->create(['status' => 'assist']);
        $assist->syncPrivileges(['admin.see-menu']);

        $keys = fn (array $groups) => array_map(fn ($g) => $g->key, $groups);

        // Only entries whose routes exist show up: Pyramid, Championship, Draws and Mixed are Laravel modules.
        $groups = $this->groupsFor($admin, route('admin.dashboard'));
        $this->assertContains('tournaments', $keys($groups));
        $tournaments = collect($groups)->firstWhere('key', 'tournaments');
        $this->assertSame(['pyramid', 'championship', 'draws', 'mixed'], array_map(fn ($i) => $i->key, $tournaments->items));
        $this->assertSame(route('pyramide.admin.index'), $tournaments->items[0]->url);
        $this->assertSame(route('tournament.admin'), $tournaments->items[1]->url);
        $this->assertSame(route('draw.index'), $tournaments->items[2]->url);
        $this->assertSame(route('mixed.admin.index'), $tournaments->items[3]->url);

        // An assist user sees the draws entry with the admin.draw privilege only.
        $drawAssist = User::factory()->create(['status' => 'assist']);
        $drawAssist->syncPrivileges(['admin.see-menu', 'admin.draw']);
        $drawTournaments = collect($this->groupsFor($drawAssist->fresh(), route('admin.dashboard')))->firstWhere('key', 'tournaments');
        $this->assertSame(['draws'], array_map(fn ($i) => $i->key, $drawTournaments->items));

        $this->assertNotContains('tournaments', $keys($this->groupsFor($assist->fresh(), route('admin.dashboard'))));
    }

    #[Test]
    public function labels_exist_in_both_locales(): void
    {
        $keys = ['label', 'eyebrow', 'group_operations', 'group_users', 'group_tournaments', 'group_settings',
            'pyramid', 'championship', 'draws', 'mixed', 'open', 'close', 'breadcrumb', 'back'];

        foreach (['de', 'en'] as $locale) {
            app()->setLocale($locale);
            foreach ($keys as $key) {
                $path = 'booking.admin.nav.'.$key;
                $this->assertNotSame($path, __($path), "$path missing in $locale");
            }
        }
    }
}
