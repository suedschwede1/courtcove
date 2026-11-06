<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

use App\Models\User;
use App\Support\Ui\AdminNavGroup;
use App\Support\Ui\NavItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Navigation of the Members area: one group in the left sidebar of the admin shell (overview, members, dues,
 * mail, change log, settings). "Einstellungen" carries the settings pages as children; the sidebar expands them
 * on the settings pages. An entry is active when the current route matches one of its patterns, and only
 * entries the user's gate allows are listed.
 */
final class MembersNavigation
{
    /**
     * key => [route, gate, label key, active route patterns]; the settings entry takes the patterns of SETTINGS.
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: list<string>}>
     */
    private const ENTRIES = [
        'dashboard' => ['members.dashboard', 'admin.members', 'members::admin.dashboard.title', ['members.dashboard']],
        'members' => ['members.index', 'admin.members', 'members::admin.members.title', ['members.index', 'members.create', 'members.edit', 'members.payments.*']],
        'dues' => ['members.dues.index', 'admin.members', 'members::admin.dues.title', ['members.dues.*']],
        'mail' => ['members.mail.index', 'admin.members', 'members::admin.mail.title', ['members.mail.index', 'members.mail.templates.*']],
        'changes' => ['members.changes.index', 'admin.members', 'members::admin.changes.title', ['members.changes.*']],
        'settings' => ['members.fiscal-years.index', 'admin.members', 'members::admin.settings.title', []],
    ];

    /**
     * Children of the settings entry: key => [route, gate, label key, active route patterns].
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: list<string>}>
     */
    private const SETTINGS = [
        'fiscal_years' => ['members.fiscal-years.index', 'admin.members', 'members::admin.fiscal_years.title', ['members.fiscal-years.*']],
        'fee_categories' => ['members.fee-categories.index', 'admin.members', 'members::admin.fee_categories.title', ['members.fee-categories.*']],
        'functions' => ['members.functions.index', 'admin.members', 'members::admin.functions.title', ['members.functions.*']],
        'mail_config' => ['members.mail.config', 'admin.members', 'members::admin.mail.config_title', ['members.mail.config']],
        'dsgvo' => ['members.dsgvo.index', 'admin.members', 'members::admin.dsgvo.title', ['members.dsgvo.*']],
    ];

    /**
     * The sidebar groups of the Members area (one group; empty when the user may see no entry).
     *
     * @return list<AdminNavGroup>
     */
    public static function groups(?User $user, Request $request): array
    {
        if ($user === null) {
            return [];
        }

        $gate = Gate::forUser($user);
        $items = [];
        foreach (self::ENTRIES as $key => [$route, $ability, $label, $patterns]) {
            if (! $gate->allows($ability)) {
                continue;
            }

            $children = [];
            if ($key === 'settings') {
                foreach (self::SETTINGS as $childKey => [$childRoute, $childAbility, $childLabel, $childPatterns]) {
                    if ($gate->allows($childAbility)) {
                        $children[] = self::item($request, $childKey, $childRoute, $childLabel, $childPatterns);
                    }
                }
                if ($children === []) {
                    continue;
                }
                // The entry leads to the first settings page the user may open.
                $route = self::SETTINGS[$children[0]->key][0];
            }

            $items[] = self::item($request, $key, $route, $label, $key === 'settings' ? [] : $patterns, $children);
        }

        return $items === [] ? [] : [new AdminNavGroup('members', (string) __('members::admin.area.eyebrow'), $items)];
    }

    /**
     * @param  list<string>  $patterns
     * @param  list<NavItem>  $children
     */
    private static function item(Request $request, string $key, string $route, string $label, array $patterns, array $children = []): NavItem
    {
        $active = $patterns !== [] && $request->routeIs(...$patterns);

        return new NavItem($key, (string) __($label), route($route), '', $active, true, $children);
    }
}
