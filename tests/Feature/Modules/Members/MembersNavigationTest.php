<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Modules\Members\Support\MembersNavigation;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MembersNavigationTest extends TestCase
{
    #[Test]
    public function tabs_list_the_module_areas_in_order(): void
    {
        $tabs = MembersNavigation::tabs($this->requestFor('members.dashboard'));

        $this->assertSame(['dashboard', 'members', 'dues', 'mail', 'settings'], array_column($tabs, 'key'));
        $expected = [
            'dashboard' => ['members.dashboard', 'members::admin.dashboard.title'],
            'members' => ['members.index', 'members::admin.members.title'],
            'dues' => ['members.dues.index', 'members::admin.dues.title'],
            'mail' => ['members.mail.index', 'members::admin.mail.title'],
            'settings' => ['members.fiscal-years.index', 'members::admin.settings.title'],
        ];
        foreach ($tabs as $tab) {
            [$route, $label] = $expected[$tab['key']];
            $this->assertSame(route($route), $tab['url'], $tab['key']);
            $this->assertSame(__($label), $tab['label'], $tab['key']);
        }

        $this->assertSame(['dashboard'], $this->activeKeys($tabs));
    }

    #[Test]
    public function member_pages_mark_the_members_tab(): void
    {
        $this->assertSame(['members'], $this->activeKeys(MembersNavigation::tabs($this->requestFor('members.edit', ['member' => 1]))));
        $this->assertSame(['members'], $this->activeKeys(MembersNavigation::tabs($this->requestFor('members.payments.create', ['member' => 1]))));
        $this->assertSame(['mail'], $this->activeKeys(MembersNavigation::tabs($this->requestFor('members.mail.templates.update', ['mailTemplate' => 1], 'PUT'))));
    }

    #[Test]
    public function every_settings_page_marks_the_settings_tab_and_its_own_entry(): void
    {
        $pages = [
            'members.fiscal-years.index' => [[], 'fiscal_years'],
            'members.fiscal-years.edit' => [['fiscalYear' => 1], 'fiscal_years'],
            'members.fee-categories.index' => [[], 'fee_categories'],
            'members.functions.edit' => [['clubRole' => 1], 'functions'],
            'members.mail.config' => [[], 'mail_config'],
            'members.dsgvo.index' => [[], 'dsgvo'],
        ];

        foreach ($pages as $route => [$parameters, $key]) {
            $request = $this->requestFor($route, $parameters);
            $this->assertSame(['settings'], $this->activeKeys(MembersNavigation::tabs($request)), $route);

            $settings = MembersNavigation::settings($request);
            $this->assertNotNull($settings, $route);
            $this->assertSame(['fiscal_years', 'fee_categories', 'functions', 'mail_config', 'dsgvo'], array_column($settings, 'key'));
            $this->assertSame([$key], $this->activeKeys($settings), $route);
        }
    }

    #[Test]
    public function the_settings_row_is_only_shown_on_settings_pages(): void
    {
        $this->assertNull(MembersNavigation::settings($this->requestFor('members.index')));
        $this->assertNull(MembersNavigation::settings($this->requestFor('members.mail.index')));
        $this->assertSame([], $this->activeKeys(MembersNavigation::tabs($this->requestFor('admin.dashboard'))));
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
     * @param  list<array{key: string, label: string, url: string, active: bool}>  $items
     * @return list<string>
     */
    private function activeKeys(array $items): array
    {
        return array_values(array_column(array_filter($items, fn (array $item): bool => $item['active']), 'key'));
    }
}
