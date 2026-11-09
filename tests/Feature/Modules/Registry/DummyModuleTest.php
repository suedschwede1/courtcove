<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Registry;

use App\Models\User;
use App\Services\AdminNavigation;
use App\Services\MainNavigation;
use App\Support\Modules\ModuleAdminEntry;
use App\Support\Modules\ModuleAreaNavigation;
use App\Support\Modules\ModuleDefinition;
use App\Support\Modules\ModuleNavigation;
use App\Support\Modules\ModuleRegistry;
use App\Support\Navigation\AreaNavBuilder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A module that registers itself (as a service provider would in boot()) shows up in the public menu, the module
 * overview, the admin group, the rights list and the module sidebar without any change to core navigation code.
 */
final class DummyModuleTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');

        Route::middleware('web')->group(function (): void {
            Route::get('/dummy-module', fn () => 'dummy')->name('dummy.index');
            Route::get('/dummy-module/admin', fn () => 'dummy admin')->name('dummy.admin');
        });

        app('router')->getRoutes()->refreshNameLookups();

        app(ModuleRegistry::class)->register(new ModuleDefinition(
            key: 'dummy',
            name: 'dummy.name',
            icon: 'trophy',
            description: 'dummy.description',
            route: 'dummy.index',
            routePatterns: ['dummy.*'],
            navigation: DummyNavigation::class,
            adminEntries: [new ModuleAdminEntry('dummy', 'dummy.admin', 'admin.dummy', 'dummy.admin_label', ['dummy.admin'])],
            privileges: ['admin.dummy' => 'dummy.privilege'],
            order: 500,
        ));

        app('translator')->addLines([
            'dummy.name' => 'Dummy-Modul', 'dummy.description' => 'Ein Testmodul.', 'dummy.admin_label' => 'Dummy-Verwaltung',
            'dummy.privilege' => 'Darf das Dummy-Modul verwalten', 'dummy.entry' => 'Dummy-Start',
        ], 'de');
    }

    private function request(string $path): Request
    {
        $request = Request::create($path);
        $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));

        return $request;
    }

    #[Test]
    public function the_public_menu_lists_the_module_and_the_overview(): void
    {
        $user = User::factory()->create();
        $nav = app(MainNavigation::class);
        $items = $nav->items($user, $this->request('/calendar'));

        $keys = array_map(fn ($i) => $i->key, $items);
        $this->assertContains('dummy', $keys);
        $this->assertContains('module-overview', $keys);

        $bar = $nav->bar($items, $this->request('/calendar'));
        $group = collect($bar)->firstWhere('key', 'modules');
        $this->assertSame('Module', $group->label);
        $this->assertContains('dummy', array_map(fn ($i) => $i->key, $group->children));
    }

    #[Test]
    public function a_public_module_shows_for_guests_and_a_module_outside_the_menu_only_in_the_overview(): void
    {
        $registry = new ModuleRegistry;
        $this->app->instance(ModuleRegistry::class, $registry);
        $registry->register(new ModuleDefinition(
            key: 'open', name: 'dummy.name', icon: 'trophy', description: 'dummy.description',
            route: 'dummy.index', routePatterns: ['dummy.*'], visible: fn () => true, order: 10,
        ));
        $registry->register(new ModuleDefinition(
            key: 'hidden', name: 'dummy.name', icon: 'trophy', description: 'dummy.description',
            route: 'dummy.admin', routePatterns: ['dummy.admin'], visible: fn () => true, inMenu: false, order: 20,
        ));

        $keys = array_map(fn ($i) => $i->key, app(MainNavigation::class)->items(null, $this->request('/calendar')));
        $this->assertSame(['calendar', 'open', 'module-overview'], $keys);

        $this->get('/modules')->assertOk()->assertSee('module-open', false)->assertSee('module-hidden', false);
    }

    #[Test]
    public function a_module_entry_without_an_ability_is_visible_to_every_admin_user(): void
    {
        $registry = new ModuleRegistry;
        $this->app->instance(ModuleRegistry::class, $registry);
        $registry->register(new ModuleDefinition(
            key: 'open', name: 'dummy.name', icon: 'trophy', description: 'dummy.description', route: 'dummy.index',
            adminEntries: [new ModuleAdminEntry('open', 'dummy.admin', null, 'dummy.admin_label', ['dummy.admin'])],
        ));

        $assist = User::factory()->status('assist')->create();
        $assist->syncPrivileges(['admin.see-menu']);
        $group = collect(app(AdminNavigation::class)->groups($assist->fresh(), $this->request('/admin')))->firstWhere('key', 'modules');

        $this->assertSame(['open'], array_map(fn ($i) => $i->key, $group->items));
    }

    #[Test]
    public function the_module_group_is_current_on_its_pages(): void
    {
        $user = User::factory()->create();
        $nav = app(MainNavigation::class);
        $request = $this->request('/dummy-module');
        $bar = $nav->bar($nav->items($user, $request), $request);

        $this->assertSame(['modules'], array_values(array_map(fn ($i) => $i->key, array_filter($bar, fn ($i) => $i->active))));
    }

    #[Test]
    public function the_overview_page_lists_the_module_for_a_member(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/modules')
            ->assertOk()
            ->assertSee('Dummy-Modul')
            ->assertSee('Ein Testmodul.')
            ->assertSee(route('dummy.index'), false);
    }

    #[Test]
    public function the_overview_is_titled_module(): void
    {
        $this->actingAs(User::factory()->create())->get('/modules')->assertSee('Module - ', false);
    }

    #[Test]
    public function the_overview_needs_a_login_when_every_module_does(): void
    {
        $this->get('/modules')->assertRedirect();
    }

    #[Test]
    public function the_admin_group_shows_the_module_entry_by_permission(): void
    {
        $admin = User::factory()->status('admin')->create();
        $groups = app(AdminNavigation::class)->groups($admin, $this->request('/admin'));
        $group = collect($groups)->firstWhere('key', 'modules');

        $this->assertNotNull($group);
        $this->assertSame('Module', $group->label);
        $this->assertContains('dummy', array_map(fn ($i) => $i->key, $group->items));
        $this->assertContains('Dummy-Verwaltung', array_map(fn ($i) => $i->label, $group->items));
    }

    #[Test]
    public function an_assist_without_the_module_right_does_not_see_the_entry(): void
    {
        $assist = User::factory()->status('assist')->create();
        $assist->syncPrivileges(['admin.see-menu']);
        $groups = app(AdminNavigation::class)->groups($assist->fresh(), $this->request('/admin'));

        $keys = collect($groups)->flatMap(fn ($g) => array_map(fn ($i) => $i->key, $g->items))->all();
        $this->assertNotContains('dummy', $keys);
    }

    #[Test]
    public function the_admin_group_disappears_without_any_visible_entry(): void
    {
        $this->app->instance(ModuleRegistry::class, new ModuleRegistry);

        $admin = User::factory()->status('admin')->create();
        $groups = app(AdminNavigation::class)->groups($admin, $this->request('/admin'));

        $this->assertNotContains('modules', array_map(fn ($g) => $g->key, $groups));
    }

    #[Test]
    public function the_module_right_is_assignable_and_labelled(): void
    {
        $this->assertContains('admin.dummy', User::privileges());
        $this->assertContains('admin.dummy', User::privilegedPrivileges());

        $root = User::factory()->status('admin')->create();
        $this->actingAs($root)->get('/admin/users/create')->assertOk()
            ->assertSee('admin.dummy', false)->assertSee('Darf das Dummy-Modul verwalten');
    }

    #[Test]
    public function the_module_sidebar_lists_the_module_group(): void
    {
        $user = User::factory()->create();
        $area = app(ModuleAreaNavigation::class);
        $groups = $area->groups($user, $this->request('/dummy-module'));

        $this->assertSame(['dummy'], array_map(fn ($g) => $g->key, $groups));
        $this->assertSame('Dummy-Start', $groups[0]->items[0]->label);
        $this->assertSame('Dummy-Modul', $groups[0]->label);
        $this->assertSame('Dummy-Modul', $area->moduleLabel($this->request('/dummy-module')));
    }
}

final class DummyNavigation implements ModuleNavigation
{
    public function entries(AreaNavBuilder $nav): array
    {
        return [$nav->item('start', __('dummy.entry'), route('dummy.index'), ['dummy.index'])];
    }

    public function settingsEntries(AreaNavBuilder $nav): array
    {
        return [];
    }
}
