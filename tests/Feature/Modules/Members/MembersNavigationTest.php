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
    public function groups_list_labels_and_items_in_order(): void
    {
        $groups = MembersNavigation::groups($this->requestFor('members.dashboard'));

        $this->assertSame(
            [__('booking.nav.members'), __('members::admin.dashboard.configuration')],
            array_column($groups, 'label'),
        );
        $this->assertSame(['dashboard', 'members', 'dues', 'mail'], array_column($groups[0]['items'], 'key'));
        $this->assertSame(
            ['fiscal_years', 'fee_categories', 'functions', 'mail_config', 'dsgvo'],
            array_column($groups[1]['items'], 'key'),
        );

        $items = $this->items($groups);
        $expected = [
            'dashboard' => ['members.dashboard', 'members::admin.dashboard.title'],
            'members' => ['members.index', 'members::admin.members.title'],
            'dues' => ['members.dues.index', 'members::admin.dues.title'],
            'mail' => ['members.mail.index', 'members::admin.mail.title'],
            'fiscal_years' => ['members.fiscal-years.index', 'members::admin.fiscal_years.title'],
            'fee_categories' => ['members.fee-categories.index', 'members::admin.fee_categories.title'],
            'functions' => ['members.functions.index', 'members::admin.functions.title'],
            'mail_config' => ['members.mail.config', 'members::admin.mail.config_title'],
            'dsgvo' => ['members.dsgvo.index', 'members::admin.dsgvo.title'],
        ];
        foreach ($expected as $key => [$route, $label]) {
            $this->assertSame(route($route), $items[$key]['url'], $key);
            $this->assertSame(__($label), $items[$key]['label'], $key);
        }

        $this->assertSame(['dashboard'], $this->activeKeys($groups));
    }

    #[Test]
    public function member_edit_marks_only_members_active(): void
    {
        $this->assertSame(['members'], $this->activeKeys(
            MembersNavigation::groups($this->requestFor('members.edit', ['member' => 1])),
        ));
    }

    #[Test]
    public function payment_page_marks_members_active(): void
    {
        $this->assertSame(['members'], $this->activeKeys(
            MembersNavigation::groups($this->requestFor('members.payments.create', ['member' => 1])),
        ));
    }

    #[Test]
    public function mail_config_marks_only_mail_config_active(): void
    {
        $this->assertSame(['mail_config'], $this->activeKeys(
            MembersNavigation::groups($this->requestFor('members.mail.config')),
        ));
    }

    #[Test]
    public function template_update_marks_mail_active(): void
    {
        $this->assertSame(['mail'], $this->activeKeys(
            MembersNavigation::groups($this->requestFor('members.mail.templates.update', ['mailTemplate' => 1], 'PUT')),
        ));
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
     * @param  list<array{label: string, items: list<array{key: string, label: string, url: string, active: bool}>}>  $groups
     * @return array<string, array{key: string, label: string, url: string, active: bool}>
     */
    private function items(array $groups): array
    {
        $items = [];
        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                $items[$item['key']] = $item;
            }
        }

        return $items;
    }

    /**
     * @param  list<array{label: string, items: list<array{key: string, label: string, url: string, active: bool}>}>  $groups
     * @return list<string>
     */
    private function activeKeys(array $groups): array
    {
        return array_keys(array_filter(
            $this->items($groups),
            fn (array $item): bool => $item['active'],
        ));
    }
}
