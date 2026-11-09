<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\Modules\ModuleRegistry;
use App\Support\Navigation\AreaNavBuilder;
use App\Support\Navigation\AreaNavGroup;
use App\Support\Navigation\AreaNavigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Navigation of the administration area in the area shell, built from one list of rows.
 */
final class AdminNavigation implements AreaNavigation
{
    private const MODULES_GROUP = 'modules';

    public function __construct(private readonly ModuleRegistry $registry) {}

    public function eyebrow(): string
    {
        return __('booking.admin.nav.eyebrow');
    }

    public function label(): string
    {
        return __('booking.admin.nav.label');
    }

    /**
     * Group key => [label key, rows]; row = [key, route name, gate, label key, active patterns, route must exist].
     *
     * @return list<array{0: string, 1: string, 2: list<array{0: string, 1: string, 2: string|null, 3: string, 4: list<string>, 5?: bool}>}>
     */
    private function definition(): array
    {
        return [
            ['operations', 'booking.admin.nav.group_operations', [
                ['dashboard', 'admin.dashboard', 'admin.see-menu', 'booking.admin.overview', ['admin.dashboard']],
                ['bookings', 'admin.bookings.index', 'admin.booking', 'booking.admin.bookings.title', ['admin.bookings.*']],
                ['events', 'admin.events.index', 'admin.event', 'booking.admin.events.title', ['admin.events.*']],
                ['statistics', 'admin.statistics.index', 'admin.booking', 'booking.admin.statistics.title', ['admin.statistics.*']],
            ]],
            // Booking-system logins only; club member management is its own area (header entry "Mitglieder").
            ['users', 'booking.admin.nav.group_users', [
                ['users', 'admin.users.index', 'admin.user', 'booking.admin.users.accounts', ['admin.users.*']],
                ['login_history', 'admin.login-history.index', 'admin.user', 'booking.admin.login_history.title', ['admin.login-history.*']],
            ]],
            // Rows come from the module registry (modules() below); empty here on purpose.
            [self::MODULES_GROUP, 'booking.admin.nav.group_modules', []],
            ['settings', 'booking.admin.nav.group_settings', [
                ['squares', 'admin.squares.index', 'admin.config', 'booking.admin.squares.title', ['admin.squares.*']],
                ['texts', 'admin.config.edit', 'admin.config', 'booking.admin.texts', ['admin.config.edit', 'admin.config.update']],
                ['behavior', 'admin.config.behavior.edit', 'admin.config', 'booking.admin.behavior', ['admin.config.behavior.*']],
                ['database', 'admin.database.index', 'admin.config', 'booking.admin.database.title', ['admin.database.*']],
                ['scheduler', 'admin.scheduler.index', 'admin.config', 'booking.admin.scheduler.title', ['admin.scheduler.*']],
                ['api_tokens', 'admin.api-tokens.index', 'admin.api-tokens', 'booking.admin.api_tokens.title', ['admin.api-tokens.*']],
                ['whatsapp', 'admin.whatsapp.edit', 'admin.config', 'booking.admin.whatsapp.title', ['admin.whatsapp.*']],
                ['security', 'admin.security.edit', 'admin.config', 'booking.admin.security.title', ['admin.security.*']],
                ['testmail', 'admin.testmail.index', 'admin.config', 'booking.admin.testmail.title', ['admin.testmail.*']],
            ]],
        ];
    }

    /**
     * The administration entries of all registered modules as rows; each is shown by its own ability, so the group
     * is left out for a viewer who may use none of them.
     *
     * @return list<array{0: string, 1: string, 2: string|null, 3: string, 4: list<string>, 5?: bool}>
     */
    private function moduleRows(): array
    {
        $rows = [];
        foreach ($this->registry->adminEntries() as $entry) {
            $rows[] = [$entry->key, $entry->route, $entry->ability, $entry->label, $entry->patterns, true];
        }

        return $rows;
    }

    /**
     * @return list<AreaNavGroup>
     */
    public function groups(?User $user, Request $request): array
    {
        if ($user === null) {
            return [];
        }

        $nav = new AreaNavBuilder($user, $request);
        $groups = [];

        foreach ($this->definition() as [$groupKey, $groupLabel, $rows]) {
            if ($groupKey === self::MODULES_GROUP) {
                $rows = $this->moduleRows();
            }

            $items = [];
            foreach ($rows as $row) {
                [$key, $routeName, $ability, $label, $patterns] = $row;
                if (($row[5] ?? false) && ! Route::has($routeName)) {
                    continue;
                }

                $items[] = $nav->item($key, __($label), route($routeName), $patterns, $ability);
            }

            $groups[] = $nav->group($groupKey, __($groupLabel), $items);
        }

        return AreaNavBuilder::groups($groups);
    }
}
