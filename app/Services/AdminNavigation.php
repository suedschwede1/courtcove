<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\Ui\AdminNavGroup;
use App\Support\Ui\NavItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Builds the grouped admin sidebar navigation from one list of rows.
 */
final class AdminNavigation
{
    /**
     * Group key => [label key, rows]; row = [key, route name, gate, label key, active patterns, route must exist].
     *
     * @return list<array{0: string, 1: string, 2: list<array{0: string, 1: string, 2: string, 3: string, 4: list<string>, 5?: bool}>}>
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
            ['tournaments', 'booking.admin.nav.group_tournaments', [
                ['pyramid', 'pyramide.admin.index', 'admin.pyramid', 'booking.admin.nav.pyramid', ['pyramide.admin.*'], true],
                ['championship', 'tournament.admin', 'admin.tournament', 'booking.admin.nav.championship', ['tournament.admin*'], true],
                ['draws', 'draw.index', 'admin.draw', 'booking.admin.nav.draws', ['draw.*'], true],
                ['mixed', 'mixed.admin.index', 'admin.mixed', 'booking.admin.nav.mixed', ['mixed.admin.*'], true],
            ]],
            ['settings', 'booking.admin.nav.group_settings', [
                ['squares', 'admin.squares.index', 'admin.config', 'booking.admin.squares.title', ['admin.squares.*']],
                ['texts', 'admin.config.edit', 'admin.config', 'booking.admin.texts', ['admin.config.edit', 'admin.config.update']],
                ['behavior', 'admin.config.behavior.edit', 'admin.config', 'booking.admin.behavior', ['admin.config.behavior.*']],
                ['database', 'admin.database.index', 'admin.config', 'booking.admin.database.title', ['admin.database.*']],
                ['api_tokens', 'admin.api-tokens.index', 'admin.api-tokens', 'booking.admin.api_tokens.title', ['admin.api-tokens.*']],
                ['testmail', 'admin.testmail.index', 'admin.config', 'booking.admin.testmail.title', ['admin.testmail.*']],
            ]],
        ];
    }

    /**
     * @return list<AdminNavGroup>
     */
    public function groups(User $user, Request $request): array
    {
        $gate = Gate::forUser($user);
        $activeSeen = false;
        $groups = [];

        foreach ($this->definition() as [$groupKey, $groupLabel, $rows]) {
            $items = [];
            foreach ($rows as $row) {
                [$key, $routeName, $ability, $label, $patterns] = $row;
                $mustExist = $row[5] ?? false;

                if ($mustExist && ! Route::has($routeName)) {
                    continue;
                }
                if (! $gate->allows($ability)) {
                    continue;
                }

                $active = ! $activeSeen && $request->routeIs(...$patterns);
                $activeSeen = $activeSeen || $active;
                $items[] = new NavItem($key, __($label), route($routeName), '', $active, true);
            }

            if ($items !== []) {
                $groups[] = new AdminNavGroup($groupKey, __($groupLabel), $items);
            }
        }

        return $groups;
    }
}
