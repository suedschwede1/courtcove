<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\AdminNavigation;
use App\Support\Ui\AdminNavGroup;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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

        $this->assertSame(['operations', 'members', 'settings'], array_map(fn ($g) => $g->key, $groups));
        $this->assertSame(
            [
                ['dashboard', 'bookings', 'events', 'statistics'],
                ['users', 'login_history'],
                ['squares', 'texts', 'behavior', 'database', 'api_tokens', 'testmail'],
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
    public function tournament_entries_need_route_and_gate(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $assist = User::factory()->create(['status' => 'assist']);
        $assist->syncPrivileges(['admin.see-menu']);

        $keys = fn (array $groups) => array_map(fn ($g) => $g->key, $groups);
        $this->assertNotContains('tournaments', $keys($this->groupsFor($admin, route('admin.dashboard'))));

        Route::get('/_test-pyramid', fn () => '')->name('pyramide.admin.index');
        app('router')->getRoutes()->refreshNameLookups();

        $groups = $this->groupsFor($admin, route('admin.dashboard'));
        $this->assertContains('tournaments', $keys($groups));
        $tournaments = collect($groups)->firstWhere('key', 'tournaments');
        $this->assertSame(['pyramid'], array_map(fn ($i) => $i->key, $tournaments->items));
        $this->assertSame(url('/_test-pyramid'), $tournaments->items[0]->url);

        $this->assertNotContains('tournaments', $keys($this->groupsFor($assist->fresh(), route('admin.dashboard'))));
    }

    #[Test]
    public function labels_exist_in_both_locales(): void
    {
        $keys = ['label', 'eyebrow', 'group_operations', 'group_members', 'group_tournaments', 'group_settings',
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
