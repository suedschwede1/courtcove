<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\Modules\ModuleRegistry;
use App\Support\Ui\NavItem;
use Illuminate\Http\Request;

/**
 * Builds the main navigation once for the header bar and the phone menu.
 *
 * items() is the flat list (phone menu); bar() folds the modules (every module of the ModuleRegistry that the
 * viewer sees, plus the module overview) into one entry so the desktop header has at most five top-level entries
 * and never wraps: Calendar, My bookings, Modules, Members, Admin.
 */
final class MainNavigation
{
    /** Item key of the module overview link. */
    public const OVERVIEW_KEY = 'module-overview';

    public function __construct(private ModuleRegistry $registry) {}

    /**
     * @return list<NavItem>
     */
    public function items(?User $user, Request $request): array
    {
        /** @var list<array{key: string, label: string, url: string, icon: string, match: bool, inBar: bool}> $rows */
        $rows = [];
        $add = static function (string $key, string $label, string $url, string $icon, bool $match, bool $inBar = true) use (&$rows): void {
            $rows[] = ['key' => $key, 'label' => $label, 'url' => $url, 'icon' => $icon, 'match' => $match, 'inBar' => $inBar];
        };

        $add('calendar', __('booking.nav.calendar'), route('calendar.index'), 'calendar', $request->routeIs('calendar.*', 'bookings.*'));

        if ($user !== null) {
            $add('bookings', __('booking.nav.my_bookings'), route('account.bookings'), 'bookings', $request->routeIs('account.bookings'));
        }

        $visible = array_values(array_filter($this->registry->available(), static fn ($module): bool => $module->visibleTo($user)));
        foreach ($visible as $module) {
            if ($module->inMenu) {
                $add($module->key, $module->label(), $module->entryUrl(), $module->icon, $module->matches($request));
            }
        }
        if ($visible !== []) {
            $add(self::OVERVIEW_KEY, __('modules.overview'), route('modules.index'), 'trophy', $request->routeIs('modules.index'));
        }

        if ($user !== null) {
            $add('account', __('booking.nav.my_account'), route('account.edit'), 'user', $request->routeIs('account.edit'), false);

            // Members (club membership records) and Admin (booking system) are separate areas with their own gates.
            // RAW privilege on purpose: the entries stay visible while admin mode is off and lead to the
            // "Switch on admin mode" page there (middleware admin.mode).
            if ($user->hasPrivilege('admin.members')) {
                $add('members', __('booking.nav.members'), route('members.dashboard'), 'members', $request->routeIs('members.*'));
            }
            if ($user->hasPrivilege('admin.see-menu')) {
                $add('admin', __('booking.nav.admin'), route('admin.dashboard'), 'settings', $request->routeIs('admin.*'));
            }
        }

        $activeSeen = false;
        $items = [];
        foreach ($rows as $row) {
            $active = $row['match'] && ! $activeSeen;
            $activeSeen = $activeSeen || $active;
            $items[] = new NavItem($row['key'], $row['label'], $row['url'], $row['icon'], $active, $row['inBar']);
        }

        return $items;
    }

    /**
     * Whether the request is a page of the module area (the overview or any module): the "Modules" entry is then
     * current.
     */
    public function inModuleArea(Request $request): bool
    {
        return $request->routeIs('modules.index') || $this->registry->inArea($request);
    }

    /**
     * The header bar's entries: bar-only items, the modules collapsed into one group.
     * A group with a single entry shows that entry directly. With the request, the modules entry is current on
     * every page of the module area, also where none of its children matches (e.g. a draw record).
     *
     * @param  list<NavItem>  $items  as returned by items()
     * @return list<NavItem>
     */
    public function bar(array $items, ?Request $request = null): array
    {
        $areaActive = $request !== null && $this->inModuleArea($request);
        $moduleKeys = [...array_map(static fn ($module): string => $module->key, $this->registry->available()), self::OVERVIEW_KEY];
        $groupKey = 'modules';

        $bar = [];
        $placed = false;
        foreach ($items as $item) {
            if (! $item->inBar) {
                continue;
            }

            if (! in_array($item->key, $moduleKeys, true)) {
                $bar[] = $item;

                continue;
            }

            if ($placed) {
                continue;
            }
            $placed = true;

            $children = array_values(array_filter($items, static fn (NavItem $i): bool => $i->inBar && in_array($i->key, $moduleKeys, true)));
            if (count($children) === 1) {
                $child = $children[0];
                $bar[] = $areaActive && ! $child->active
                    ? new NavItem($child->key, $child->label, $child->url, $child->icon, true, $child->inBar, $child->children)
                    : $child;

                continue;
            }

            $active = $areaActive || array_filter($children, static fn (NavItem $i): bool => $i->active) !== [];
            $bar[] = new NavItem($groupKey, __('modules.area'), $children[0]->url, 'trophy', $active, true, $children);
        }

        return $bar;
    }
}
