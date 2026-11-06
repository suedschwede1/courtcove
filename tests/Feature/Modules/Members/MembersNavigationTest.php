<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Support\MembersNavigation;
use App\Support\Ui\AdminNavGroup;
use App\Support\Ui\NavItem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The sidebar navigation of the Members area: entries, permissions, active entry and the settings sub-group. */
class MembersNavigationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Every GET route of the module => [route parameters, active entry key, active settings child key or null].
     *
     * @var array<string, array{0: array<string, int>, 1: string|null, 2: string|null}>
     */
    private const GET_ROUTES = [
        'members.dashboard' => [[], 'dashboard', null],
        'members.index' => [[], 'members', null],
        'members.create' => [[], 'members', null],
        'members.edit' => [['member' => 1], 'members', null],
        'members.payments.create' => [['member' => 1], 'members', null],
        'members.export' => [[], null, null],
        'members.dues.index' => [[], 'dues', null],
        'members.mail.index' => [[], 'mail', null],
        'members.changes.index' => [[], 'changes', null],
        'members.fiscal-years.index' => [[], null, 'fiscal_years'],
        'members.fiscal-years.create' => [[], null, 'fiscal_years'],
        'members.fiscal-years.edit' => [['fiscalYear' => 1], null, 'fiscal_years'],
        'members.fee-categories.index' => [[], null, 'fee_categories'],
        'members.functions.index' => [[], null, 'functions'],
        'members.functions.create' => [[], null, 'functions'],
        'members.functions.edit' => [['clubRole' => 1], null, 'functions'],
        'members.mail.config' => [[], null, 'mail_config'],
        'members.dsgvo.index' => [[], null, 'dsgvo'],
    ];

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function one_group_lists_the_areas_in_order_with_the_settings_pages_as_children(): void
    {
        $groups = MembersNavigation::groups($this->admin(), $this->requestFor('members.dashboard'));

        $this->assertCount(1, $groups);
        $this->assertSame('members', $groups[0]->key);
        $this->assertSame(__('members::admin.area.eyebrow'), $groups[0]->label);

        $expected = [
            'dashboard' => ['members.dashboard', 'members::admin.dashboard.title'],
            'members' => ['members.index', 'members::admin.members.title'],
            'dues' => ['members.dues.index', 'members::admin.dues.title'],
            'mail' => ['members.mail.index', 'members::admin.mail.title'],
            'changes' => ['members.changes.index', 'members::admin.changes.title'],
            'settings' => ['members.fiscal-years.index', 'members::admin.settings.title'],
        ];
        $this->assertSame(array_keys($expected), array_map(fn (NavItem $i) => $i->key, $groups[0]->items));
        foreach ($groups[0]->items as $item) {
            [$route, $label] = $expected[$item->key];
            $this->assertSame(route($route), $item->url, $item->key);
            $this->assertSame(__($label), $item->label, $item->key);
            $this->assertSame($item->key === 'settings' ? 5 : 0, count($item->children), $item->key);
        }

        $children = $this->settings($groups)->children;
        $this->assertSame(['fiscal_years', 'fee_categories', 'functions', 'mail_config', 'dsgvo'], array_map(fn (NavItem $i) => $i->key, $children));
        $this->assertSame(
            [route('members.fiscal-years.index'), route('members.fee-categories.index'), route('members.functions.index'), route('members.mail.config'), route('members.dsgvo.index')],
            array_map(fn (NavItem $i) => $i->url, $children),
        );
        $this->assertSame(
            [__('members::admin.fiscal_years.title'), __('members::admin.fee_categories.title'), __('members::admin.functions.title'), __('members::admin.mail.config_title'), __('members::admin.dsgvo.title')],
            array_map(fn (NavItem $i) => $i->label, $children),
        );
    }

    #[Test]
    public function german_labels_match_the_request(): void
    {
        app()->setLocale('de');
        $groups = MembersNavigation::groups($this->admin(), $this->requestFor('members.dashboard'));

        $this->assertSame(['Übersicht', 'Mitglieder', 'Beiträge', 'Rundmail', 'Protokoll', 'Einstellungen'], array_map(fn (NavItem $i) => $i->label, $groups[0]->items));
        $this->assertSame(['Fiskaljahre', 'Beitragskategorien', 'Funktionen', 'Mail-Einstellungen', 'DSGVO'], array_map(fn (NavItem $i) => $i->label, $this->settings($groups)->children));
    }

    #[Test]
    public function the_route_list_covers_every_get_route_of_the_module(): void
    {
        $names = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('GET', $route->methods(), true) && str_starts_with((string) $route->getName(), 'members.'))
            ->map(fn ($route) => (string) $route->getName())
            ->sort()->values()->all();

        $expected = array_keys(self::GET_ROUTES);
        sort($expected);
        $this->assertSame($expected, $names);
    }

    #[Test]
    public function every_get_route_marks_exactly_its_entry(): void
    {
        $admin = $this->admin();

        foreach (self::GET_ROUTES as $route => [$parameters, $entry, $child]) {
            $groups = MembersNavigation::groups($admin, $this->requestFor($route, $parameters));

            $this->assertSame($entry === null ? [] : [$entry], $this->activeKeys($groups[0]->items), $route);
            $this->assertSame($child === null ? [] : [$child], $this->activeKeys($this->settings($groups)->children), $route);
        }
    }

    #[Test]
    public function form_posts_keep_the_entry_of_their_page(): void
    {
        $admin = $this->admin();

        $groups = MembersNavigation::groups($admin, $this->requestFor('members.mail.templates.update', ['mailTemplate' => 1], 'PUT'));
        $this->assertSame(['mail'], $this->activeKeys($groups[0]->items));

        $groups = MembersNavigation::groups($admin, $this->requestFor('members.functions.update', ['clubRole' => 1], 'PUT'));
        $this->assertSame(['functions'], $this->activeKeys($this->settings($groups)->children));
    }

    #[Test]
    public function nothing_is_active_outside_the_module(): void
    {
        $groups = MembersNavigation::groups($this->admin(), $this->requestFor('admin.dashboard'));

        $this->assertSame([], $this->activeKeys($groups[0]->items));
        $this->assertSame([], $this->activeKeys($this->settings($groups)->children));
    }

    #[Test]
    public function entries_follow_the_members_permission(): void
    {
        $request = $this->requestFor('members.dashboard');

        $this->assertSame([], MembersNavigation::groups(null, $request));
        $this->assertSame([], MembersNavigation::groups(User::factory()->create(['status' => 'user']), $request));

        $bookingAssist = User::factory()->status('assist')->create();
        $bookingAssist->syncPrivileges(['admin.see-menu', 'admin.booking', 'admin.user']);
        $this->assertSame([], MembersNavigation::groups($bookingAssist->fresh(), $request));

        $membersAssist = User::factory()->status('assist')->create();
        $membersAssist->syncPrivileges(['admin.members']);
        $groups = MembersNavigation::groups($membersAssist->fresh(), $request);
        $this->assertSame(['dashboard', 'members', 'dues', 'mail', 'changes', 'settings'], array_map(fn (NavItem $i) => $i->key, $groups[0]->items));
        $this->assertCount(5, $this->settings($groups)->children);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function requestFor(string $name, array $parameters = [], string $method = 'GET'): Request
    {
        $request = Request::create(route($name, $parameters), $method);
        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    /**
     * @param  list<AdminNavGroup>  $groups
     */
    private function settings(array $groups): NavItem
    {
        $settings = collect($groups[0]->items)->firstWhere('key', 'settings');
        $this->assertInstanceOf(NavItem::class, $settings);

        return $settings;
    }

    /**
     * @param  list<NavItem>  $items
     * @return list<string>
     */
    private function activeKeys(array $items): array
    {
        return array_values(array_map(fn (NavItem $i) => $i->key, array_filter($items, fn (NavItem $i): bool => $i->active)));
    }
}
