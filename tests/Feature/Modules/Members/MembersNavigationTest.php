<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Support\MembersNavigation;
use App\Support\Navigation\AreaNavGroup;
use App\Support\Navigation\AreaNavItem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The sidebar navigation of the Members area: groups, entries, permissions and the active entry. */
class MembersNavigationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Every GET route of the module => [route parameters, active entry key or null].
     *
     * @var array<string, array{0: array<string, int>, 1: string|null}>
     */
    private const GET_ROUTES = [
        'members.dashboard' => [[], 'dashboard'],
        'members.index' => [[], 'members'],
        'members.create' => [[], 'members'],
        'members.edit' => [['member' => 1], 'members'],
        'members.payments.create' => [['member' => 1], 'members'],
        'members.export' => [[], null],
        'members.dues.index' => [[], 'dues'],
        'members.mail.index' => [[], 'mail'],
        'members.changes.index' => [[], 'changes'],
        'members.fiscal-years.index' => [[], 'fiscal_years'],
        'members.fiscal-years.create' => [[], 'fiscal_years'],
        'members.fiscal-years.edit' => [['fiscalYear' => 1], 'fiscal_years'],
        'members.fee-categories.index' => [[], 'fee_categories'],
        'members.fee-categories.edit' => [['feeCategory' => 1], 'fee_categories'],
        'members.functions.index' => [[], 'functions'],
        'members.functions.create' => [[], 'functions'],
        'members.functions.edit' => [['clubRole' => 1], 'functions'],
        'members.mail.config' => [[], 'mail_config'],
        'members.dsgvo.index' => [[], 'dsgvo'],
    ];

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function two_groups_list_the_areas_and_the_settings_pages_in_order(): void
    {
        $groups = app(MembersNavigation::class)->groups($this->admin(), $this->requestFor('members.dashboard'));

        $this->assertSame(['members', 'settings'], array_map(fn (AreaNavGroup $g) => $g->key, $groups));
        $this->assertSame([__('members::admin.members.title'), __('members::admin.settings.title')], array_map(fn (AreaNavGroup $g) => $g->label, $groups));

        $expected = [
            'dashboard' => ['members.dashboard', 'members::admin.dashboard.title'],
            'members' => ['members.index', 'members::admin.members.title'],
            'dues' => ['members.dues.index', 'members::admin.dues.title'],
            'mail' => ['members.mail.index', 'members::admin.mail.title'],
            'changes' => ['members.changes.index', 'members::admin.changes.title'],
            'fiscal_years' => ['members.fiscal-years.index', 'members::admin.fiscal_years.title'],
            'fee_categories' => ['members.fee-categories.index', 'members::admin.fee_categories.title'],
            'functions' => ['members.functions.index', 'members::admin.functions.title'],
            'mail_config' => ['members.mail.config', 'members::admin.mail.config_title'],
            'dsgvo' => ['members.dsgvo.index', 'members::admin.dsgvo.title'],
        ];
        $this->assertSame(['dashboard', 'members', 'dues', 'mail', 'changes'], $this->keys($groups[0]->items));
        $this->assertSame(['fiscal_years', 'fee_categories', 'functions', 'mail_config', 'dsgvo'], $this->keys($groups[1]->items));
        foreach ([...$groups[0]->items, ...$groups[1]->items] as $item) {
            [$route, $label] = $expected[$item->key];
            $this->assertSame(route($route), $item->url, $item->key);
            $this->assertSame(__($label), $item->label, $item->key);
            $this->assertSame([], $item->children, $item->key);
        }
    }

    #[Test]
    public function german_labels(): void
    {
        app()->setLocale('de');
        $groups = app(MembersNavigation::class)->groups($this->admin(), $this->requestFor('members.dashboard'));

        $this->assertSame(['Mitglieder', 'Einstellungen'], array_map(fn (AreaNavGroup $g) => $g->label, $groups));
        $this->assertSame(['Übersicht', 'Mitglieder', 'Beiträge', 'Rundmail', 'Protokoll'], array_map(fn (AreaNavItem $i) => $i->label, $groups[0]->items));
        $this->assertSame(['Geschäftsjahre', 'Beitragskategorien', 'Funktionen', 'Mail-Konfiguration', 'Datenschutz'], array_map(fn (AreaNavItem $i) => $i->label, $groups[1]->items));
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

        foreach (self::GET_ROUTES as $route => [$parameters, $entry]) {
            $groups = app(MembersNavigation::class)->groups($admin, $this->requestFor($route, $parameters));

            $this->assertSame($entry === null ? [] : [$entry], $this->activeKeys($groups), $route);
        }
    }

    #[Test]
    public function form_posts_keep_the_entry_of_their_page(): void
    {
        $admin = $this->admin();

        $this->assertSame(['mail'], $this->activeKeys(app(MembersNavigation::class)->groups($admin, $this->requestFor('members.mail.templates.update', ['mailTemplate' => 1], 'PUT'))));
        $this->assertSame(['functions'], $this->activeKeys(app(MembersNavigation::class)->groups($admin, $this->requestFor('members.functions.update', ['clubRole' => 1], 'PUT'))));
    }

    #[Test]
    public function nothing_is_active_outside_the_module(): void
    {
        $this->assertSame([], $this->activeKeys(app(MembersNavigation::class)->groups($this->admin(), $this->requestFor('admin.dashboard'))));
    }

    #[Test]
    public function entries_follow_the_members_permission(): void
    {
        $request = $this->requestFor('members.dashboard');

        $this->assertSame([], app(MembersNavigation::class)->groups(null, $request));
        $this->assertSame([], app(MembersNavigation::class)->groups(User::factory()->create(['status' => 'user']), $request));

        $bookingAssist = User::factory()->status('assist')->create();
        $bookingAssist->syncPrivileges(['admin.see-menu', 'admin.booking', 'admin.user']);
        $this->assertSame([], app(MembersNavigation::class)->groups($bookingAssist->fresh(), $request));

        $membersAssist = User::factory()->status('assist')->create();
        $membersAssist->syncPrivileges(['admin.members']);
        $groups = app(MembersNavigation::class)->groups($membersAssist->fresh(), $request);
        $this->assertSame(['members', 'settings'], array_map(fn (AreaNavGroup $g) => $g->key, $groups));
        $this->assertCount(5, $groups[0]->items);
        $this->assertCount(5, $groups[1]->items);
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
     * @param  list<AreaNavItem>  $items
     * @return list<string>
     */
    private function keys(array $items): array
    {
        return array_map(fn (AreaNavItem $i) => $i->key, $items);
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
}
