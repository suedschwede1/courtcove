<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

use Illuminate\Http\Request;

/**
 * Sidebar navigation of the Members module: two groups of links, each item
 * marked active when the current route matches one of its patterns.
 */
final class MembersNavigation
{
    /**
     * key => [route, active route patterns, label key]
     *
     * @var list<array{label: string, items: array<string, array{0: string, 1: list<string>, 2: string}>}>
     */
    private const GROUPS = [
        [
            'label' => 'booking.nav.members',
            'items' => [
                'dashboard' => ['members.dashboard', ['members.dashboard'], 'members::admin.dashboard.title'],
                'members' => ['members.index', ['members.index', 'members.create', 'members.edit', 'members.payments.*'], 'members::admin.members.title'],
                'dues' => ['members.dues.index', ['members.dues.*'], 'members::admin.dues.title'],
                'mail' => ['members.mail.index', ['members.mail.index', 'members.mail.templates.*'], 'members::admin.mail.title'],
            ],
        ],
        [
            'label' => 'members::admin.dashboard.configuration',
            'items' => [
                'fiscal_years' => ['members.fiscal-years.index', ['members.fiscal-years.*'], 'members::admin.fiscal_years.title'],
                'fee_categories' => ['members.fee-categories.index', ['members.fee-categories.*'], 'members::admin.fee_categories.title'],
                'functions' => ['members.functions.index', ['members.functions.*'], 'members::admin.functions.title'],
                'mail_config' => ['members.mail.config', ['members.mail.config'], 'members::admin.mail.config_title'],
                'dsgvo' => ['members.dsgvo.index', ['members.dsgvo.*'], 'members::admin.dsgvo.title'],
            ],
        ],
    ];

    /**
     * @return list<array{label: string, items: list<array{key: string, label: string, url: string, active: bool}>}>
     */
    public static function groups(Request $request): array
    {
        $groups = [];

        foreach (self::GROUPS as $group) {
            $items = [];
            foreach ($group['items'] as $key => [$route, $patterns, $label]) {
                $items[] = [
                    'key' => $key,
                    'label' => (string) __($label),
                    'url' => route($route),
                    'active' => $request->routeIs(...$patterns),
                ];
            }

            $groups[] = ['label' => (string) __($group['label']), 'items' => $items];
        }

        return $groups;
    }
}
