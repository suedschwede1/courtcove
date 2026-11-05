<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Members\Models\Member;
use App\Services\AdminNavigation;
use App\Support\Ui\AdminNavGroup;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AdminNavigationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return list<AdminNavGroup>
     */
    private function groupsFor(User $user, string $url, string $method = 'GET'): array
    {
        $request = Request::create($url, $method);
        $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));

        return app(AdminNavigation::class)->groups($user, $request);
    }

    /**
     * @param  list<AdminNavGroup>  $groups
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

        $this->assertSame(['operations', 'members', 'tournaments', 'settings'], array_map(fn ($g) => $g->key, $groups));
        $this->assertSame(
            [
                ['dashboard', 'bookings', 'events', 'statistics'],
                ['members', 'users', 'login_history'],
                ['pyramid', 'mixed'],
                ['squares', 'texts', 'behavior', 'database', 'api_tokens', 'testmail', 'members_settings'],
            ],
            array_map(fn ($g) => array_map(fn ($i) => $i->key, $g->items), $groups),
        );
        $this->assertSame(['dashboard'], $this->activeKeys($groups));

        app()->setLocale('de');
        $groups = $this->groupsFor($admin, route('admin.dashboard'));
        $this->assertSame('Betrieb', $groups[0]->label);
        $this->assertSame('Übersicht', $groups[0]->items[0]->label);
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
    public function members_module_entries_follow_its_two_sections(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $member = Member::factory()->create();

        $groups = $this->groupsFor($admin, route('members.dashboard'));
        $members = collect($groups)->firstWhere('key', 'members');
        $this->assertSame(route('members.dashboard'), $members->items[0]->url);
        $this->assertSame(__('booking.nav.members'), $members->items[0]->label);
        $settings = collect($groups)->firstWhere('key', 'settings');
        $last = $settings->items[count($settings->items) - 1];
        $this->assertSame(route('members.fiscal-years.index'), $last->url);
        $this->assertSame(__('booking.admin.nav.members_settings'), $last->label);

        foreach ([
            route('members.dashboard'), route('members.index'), route('members.create'), route('members.edit', $member),
            route('members.payments.create', $member), route('members.dues.index'), route('members.mail.index'),
        ] as $url) {
            $this->assertSame(['members'], $this->activeKeys($this->groupsFor($admin, $url)), $url);
        }
        $this->assertSame(['members'], $this->activeKeys($this->groupsFor($admin, route('members.mail.templates.update', ['mailTemplate' => 1]), 'PUT')));

        foreach ([
            route('members.fiscal-years.index'), route('members.fiscal-years.create'), route('members.fee-categories.index'),
            route('members.functions.index'), route('members.functions.create'), route('members.mail.config'), route('members.dsgvo.index'),
        ] as $url) {
            $this->assertSame(['members_settings'], $this->activeKeys($this->groupsFor($admin, $url)), $url);
        }
    }

    #[Test]
    public function members_module_entries_need_the_members_privilege(): void
    {
        $assist = User::factory()->create(['status' => 'assist']);
        $assist->syncPrivileges(['admin.see-menu', 'admin.user', 'admin.config']);

        $keys = fn (array $groups): array => array_merge(...array_map(fn ($g) => array_map(fn ($i) => $i->key, $g->items), $groups));

        $without = $keys($this->groupsFor($assist->fresh(), route('admin.dashboard')));
        $this->assertNotContains('members', $without);
        $this->assertNotContains('members_settings', $without);

        $assist->syncPrivileges(['admin.see-menu', 'admin.user', 'admin.config', 'admin.members']);
        $with = $keys($this->groupsFor($assist->fresh(), route('admin.dashboard')));
        $this->assertContains('members', $with);
        $this->assertContains('members_settings', $with);
    }

    #[Test]
    public function tournament_entries_need_route_and_gate(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $assist = User::factory()->create(['status' => 'assist']);
        $assist->syncPrivileges(['admin.see-menu']);

        $keys = fn (array $groups) => array_map(fn ($g) => $g->key, $groups);

        // Only entries whose routes exist show up: Pyramid and Mixed are Laravel modules, the rest still lives in ZF2.
        $groups = $this->groupsFor($admin, route('admin.dashboard'));
        $this->assertContains('tournaments', $keys($groups));
        $tournaments = collect($groups)->firstWhere('key', 'tournaments');
        $this->assertSame(['pyramid', 'mixed'], array_map(fn ($i) => $i->key, $tournaments->items));
        $this->assertSame(route('pyramide.admin.index'), $tournaments->items[0]->url);
        $this->assertSame(route('mixed.admin.index'), $tournaments->items[1]->url);

        $this->assertNotContains('tournaments', $keys($this->groupsFor($assist->fresh(), route('admin.dashboard'))));
    }

    #[Test]
    public function labels_exist_in_both_locales(): void
    {
        $keys = ['label', 'eyebrow', 'group_operations', 'group_members', 'group_tournaments', 'group_settings',
            'pyramid', 'championship', 'draws', 'mixed', 'members_settings', 'open', 'close', 'breadcrumb', 'back'];

        foreach (['de', 'en'] as $locale) {
            app()->setLocale($locale);
            foreach ($keys as $key) {
                $path = 'booking.admin.nav.'.$key;
                $this->assertNotSame($path, __($path), "$path missing in $locale");
            }
        }
    }
}
