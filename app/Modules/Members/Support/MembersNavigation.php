<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

use Illuminate\Http\Request;

/**
 * Sub-navigation of the Members module inside the shared admin shell: two sections
 * (members, configuration), each one entry in the admin sidebar and a row of tabs
 * on its pages. An item is active when the current route matches one of its patterns.
 */
final class MembersNavigation
{
    /**
     * section key => [label key, items]; item key => [route, active route patterns, label key]
     *
     * @var array<string, array{label: string, items: array<string, array{0: string, 1: list<string>, 2: string}>}>
     */
    private const GROUPS = [
        'members' => [
            'label' => 'booking.nav.members',
            'items' => [
                'dashboard' => ['members.dashboard', ['members.dashboard'], 'members::admin.dashboard.title'],
                'members' => ['members.index', ['members.index', 'members.create', 'members.edit', 'members.payments.*'], 'members::admin.members.title'],
                'dues' => ['members.dues.index', ['members.dues.*'], 'members::admin.dues.title'],
                'mail' => ['members.mail.index', ['members.mail.index', 'members.mail.templates.*'], 'members::admin.mail.title'],
            ],
        ],
        'configuration' => [
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
     * @return list<array{key: string, label: string, items: list<array{key: string, label: string, url: string, active: bool}>}>
     */
    public static function groups(Request $request): array
    {
        $groups = [];

        foreach (self::GROUPS as $groupKey => $group) {
            $items = [];
            foreach ($group['items'] as $key => [$route, $patterns, $label]) {
                $items[] = [
                    'key' => $key,
                    'label' => (string) __($label),
                    'url' => route($route),
                    'active' => $request->routeIs(...$patterns),
                ];
            }

            $groups[] = ['key' => $groupKey, 'label' => (string) __($group['label']), 'items' => $items];
        }

        return $groups;
    }

    /**
     * The section that holds the current page, shown as tabs; null outside the module.
     *
     * @return array{key: string, label: string, items: list<array{key: string, label: string, url: string, active: bool}>}|null
     */
    public static function current(Request $request): ?array
    {
        foreach (self::groups($request) as $group) {
            foreach ($group['items'] as $item) {
                if ($item['active']) {
                    return $group;
                }
            }
        }

        return null;
    }

    /**
     * All active route patterns of one section, for its admin sidebar entry.
     *
     * @return list<string>
     */
    public static function patterns(string $groupKey): array
    {
        $patterns = [];
        foreach (self::GROUPS[$groupKey]['items'] as [, $itemPatterns]) {
            array_push($patterns, ...$itemPatterns);
        }

        return $patterns;
    }
}
