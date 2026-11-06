<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

use App\Models\User;
use App\Support\Ui\AdminNavGroup;
use App\Support\Ui\NavItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Navigation of the Members area in the left sidebar of the admin shell, as in the Mitglieder-* mockups: group
 * "Mitglieder" (overview, members, dues, mail, change log) and group "Einstellungen" (the settings pages). An entry
 * is active when the current route matches one of its patterns; only entries the user's gate allows are listed.
 */
final class MembersNavigation
{
    /**
     * group key => [group label key, rows]; row key => [route, gate, label key, active route patterns].
     *
     * @var array<string, array{0: string, 1: array<string, array{0: string, 1: string, 2: string, 3: list<string>}>}>
     */
    private const GROUPS = [
        'members' => ['members::admin.members.title', [
            'dashboard' => ['members.dashboard', 'admin.members', 'members::admin.dashboard.title', ['members.dashboard']],
            'members' => ['members.index', 'admin.members', 'members::admin.members.title', ['members.index', 'members.create', 'members.edit', 'members.payments.*']],
            'households' => ['members.households.index', 'admin.members', 'members::admin.households.title', ['members.households.*', 'members.household-dues.*']],
            'dues' => ['members.dues.index', 'admin.members', 'members::admin.dues.title', ['members.dues.*']],
            'mail' => ['members.mail.index', 'admin.members', 'members::admin.mail.title', ['members.mail.index', 'members.mail.templates.*']],
            'changes' => ['members.changes.index', 'admin.members', 'members::admin.changes.title', ['members.changes.*']],
        ]],
        'settings' => ['members::admin.settings.title', [
            'fiscal_years' => ['members.fiscal-years.index', 'admin.members', 'members::admin.fiscal_years.title', ['members.fiscal-years.*']],
            'fee_categories' => ['members.fee-categories.index', 'admin.members', 'members::admin.fee_categories.title', ['members.fee-categories.*']],
            'functions' => ['members.functions.index', 'admin.members', 'members::admin.functions.title', ['members.functions.*']],
            'mail_config' => ['members.mail.config', 'admin.members', 'members::admin.mail.config_title', ['members.mail.config']],
            'dsgvo' => ['members.dsgvo.index', 'admin.members', 'members::admin.dsgvo.title', ['members.dsgvo.*']],
        ]],
    ];

    /**
     * The sidebar groups of the Members area; a group without a visible entry is left out.
     *
     * @return list<AdminNavGroup>
     */
    public static function groups(?User $user, Request $request): array
    {
        if ($user === null) {
            return [];
        }

        $gate = Gate::forUser($user);
        $groups = [];
        foreach (self::GROUPS as $groupKey => [$groupLabel, $rows]) {
            $items = [];
            foreach ($rows as $key => [$route, $ability, $label, $patterns]) {
                if ($gate->allows($ability)) {
                    $items[] = new NavItem($key, (string) __($label), route($route), '', $request->routeIs(...$patterns));
                }
            }
            if ($items !== []) {
                $groups[] = new AdminNavGroup($groupKey, (string) __($groupLabel), $items);
            }
        }

        return $groups;
    }
}
