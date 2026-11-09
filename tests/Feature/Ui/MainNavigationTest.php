<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use App\Services\MainNavigation;
use App\Support\Ui\NavItem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MainNavigationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Schema::dropIfExists('bs_tournaments');
        parent::tearDown();
    }

    private function createTournamentsTable(): void
    {
        Schema::dropIfExists('bs_tournaments');
        Schema::create('bs_tournaments', function ($table): void {
            $table->increments('tid');
            $table->string('title');
            $table->string('status')->default('running');
            $table->integer('season')->default(2026);
            $table->tinyInteger('active')->default(0);
            $table->tinyInteger('is_test')->default(0);
        });
    }

    /** @return list<NavItem> */
    private function items(?User $user, string $path = '/calendar'): array
    {
        $request = Request::create(url($path));
        // Resolve the route like a real request so routeIs() works.
        $request->setRouteResolver(function () use ($request) {
            try {
                return app('router')->getRoutes()->match($request);
            } catch (\Throwable) {
                return null;
            }
        });

        return app(MainNavigation::class)->items($user, $request);
    }

    /**
     * @param  list<NavItem>  $items
     * @return list<string>
     */
    private function keys(array $items): array
    {
        return array_map(fn (NavItem $i) => $i->key, $items);
    }

    /**
     * @param  list<NavItem>  $items
     * @return list<string>
     */
    private function activeKeys(array $items): array
    {
        return array_values(array_map(fn (NavItem $i) => $i->key, array_filter($items, fn (NavItem $i) => $i->active)));
    }

    #[Test]
    public function guest_only_sees_calendar(): void
    {
        $items = $this->items(null);

        $this->assertSame(['calendar'], $this->keys($items));
        $this->assertSame(['calendar'], $this->activeKeys($items));
    }

    #[Test]
    public function member_gets_the_member_items(): void
    {
        $this->assertSame(
            ['calendar', 'bookings', 'pyramid', 'mixed', 'module-overview', 'account'],
            $this->keys($this->items(User::factory()->create())),
        );
    }

    #[Test]
    public function running_championship_follows_bookings(): void
    {
        $this->createTournamentsTable();
        DB::table('bs_tournaments')->insert(['title' => 'VM 2026', 'status' => 'running', 'season' => 2026, 'active' => 1, 'is_test' => 0]);

        $items = $this->items(User::factory()->create());

        $this->assertSame(['calendar', 'bookings', 'pyramid', 'championship', 'mixed', 'module-overview', 'account'], $this->keys($items));
        $this->assertSame('Meisterschaft', $items[3]->label);
        $this->assertSame(url('/tournament'), $items[3]->url);
    }

    #[Test]
    public function admin_gets_members_then_admin_last(): void
    {
        $keys = $this->keys($this->items(User::factory()->status('admin')->create()));

        $this->assertSame(['members', 'admin'], array_slice($keys, -2));
    }

    #[Test]
    public function members_pages_activate_members_and_not_admin(): void
    {
        $admin = User::factory()->status('admin')->create();

        $this->assertSame(['members'], $this->activeKeys($this->items($admin, '/members/dashboard')));
        $this->assertSame(['members'], $this->activeKeys($this->items($admin, '/members/fiscal-years')));
        $this->assertSame(['admin'], $this->activeKeys($this->items($admin, '/admin')));
    }

    #[Test]
    public function members_and_admin_follow_their_own_gates(): void
    {
        $menuOnly = User::factory()->status('assist')->create();
        $menuOnly->syncPrivileges(['admin.see-menu']);
        $keys = $this->keys($this->items($menuOnly->fresh()));
        $this->assertContains('admin', $keys);
        $this->assertNotContains('members', $keys);

        $membersOnly = User::factory()->status('assist')->create();
        $membersOnly->syncPrivileges(['admin.members']);
        $items = $this->items($membersOnly->fresh());
        $this->assertContains('members', $this->keys($items));
        $this->assertNotContains('admin', $this->keys($items));
        $this->assertSame(['calendar', 'bookings', 'modules', 'members'], $this->keys(app(MainNavigation::class)->bar($items)));
    }

    #[Test]
    public function tournament_path_activates_only_that_item(): void
    {
        $items = $this->items(User::factory()->create(), '/pyramide/1');

        $this->assertSame(['pyramid'], $this->activeKeys($items));
    }

    #[Test]
    public function account_is_active_but_not_in_the_bar(): void
    {
        $items = $this->items(User::factory()->create(), '/my-account');
        $account = collect($items)->firstWhere('key', 'account');

        $this->assertNotNull($account);
        $this->assertTrue($account->active);
        $this->assertFalse($account->inBar);
        $this->assertSame(['account'], $this->activeKeys($items));
    }

    #[Test]
    public function bar_groups_the_tournaments_under_one_item(): void
    {
        $this->createTournamentsTable();
        DB::table('bs_tournaments')->insert(['title' => 'VM 2026', 'status' => 'running', 'season' => 2026, 'active' => 1, 'is_test' => 0]);

        $bar = app(MainNavigation::class)->bar($this->items(User::factory()->create()));

        $this->assertSame(['calendar', 'bookings', 'modules'], $this->keys($bar));
        $this->assertSame('Module', $bar[2]->label);
        $this->assertSame(['pyramid', 'championship', 'mixed', 'module-overview'], $this->keys($bar[2]->children));
        $this->assertFalse($bar[2]->active);
    }

    #[Test]
    public function bar_shows_members_and_admin_as_own_entries(): void
    {
        $bar = app(MainNavigation::class)->bar($this->items(User::factory()->status('admin')->create()));

        $this->assertSame(['calendar', 'bookings', 'modules', 'members', 'admin'], $this->keys($bar));
        $this->assertSame([], $bar[3]->children);
        $this->assertSame([], $bar[4]->children);
        $this->assertSame('Mitglieder', $bar[3]->label);
        $this->assertSame(route('members.dashboard'), $bar[3]->url);
        $this->assertSame(route('admin.dashboard'), $bar[4]->url);
    }

    #[Test]
    public function bar_marks_only_members_active_on_member_pages(): void
    {
        $bar = app(MainNavigation::class)->bar($this->items(User::factory()->status('admin')->create(), '/members'));

        $this->assertSame(['members'], $this->activeKeys($bar));
    }

    #[Test]
    public function group_is_active_when_a_child_is_active(): void
    {
        $bar = app(MainNavigation::class)->bar($this->items(User::factory()->create(), '/pyramide/1'));
        $group = collect($bar)->firstWhere('key', 'modules');

        $this->assertTrue($group->active);
        $this->assertSame(['pyramid'], $this->activeKeys($group->children));
        $this->assertSame(['modules'], $this->activeKeys($bar));
    }

    #[Test]
    public function a_single_child_is_shown_directly_without_a_group(): void
    {
        $items = [
            new NavItem('calendar', 'Kalender', '/calendar', 'calendar', false),
            new NavItem('pyramid', 'Forderungspyramide', '/pyramide', 'pyramid', true),
            new NavItem('members', 'Mitglieder', '/members', 'members', false),
        ];

        $bar = app(MainNavigation::class)->bar($items);

        $this->assertSame(['calendar', 'pyramid', 'members'], $this->keys($bar));
        $this->assertSame([], $bar[1]->children);
        $this->assertSame('Forderungspyramide', $bar[1]->label);
        $this->assertTrue($bar[1]->active);
    }

    #[Test]
    public function bar_never_has_more_than_five_top_level_items(): void
    {
        $this->createTournamentsTable();
        DB::table('bs_tournaments')->insert(['title' => 'VM 2026', 'status' => 'running', 'season' => 2026, 'active' => 1, 'is_test' => 0]);

        $bar = app(MainNavigation::class)->bar($this->items(User::factory()->status('admin')->create()));

        $this->assertCount(5, $bar);
    }
}
