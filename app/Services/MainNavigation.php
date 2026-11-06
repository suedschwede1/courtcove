<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\Ui\NavItem;
use Illuminate\Http\Request;

/**
 * Builds the main navigation once for the header bar and the phone menu.
 *
 * items() is the flat list (phone menu); bar() folds the tournaments into one entry so
 * the desktop header has at most five top-level entries and never wraps:
 * Calendar, My bookings, Tournaments, Members, Admin.
 */
final class MainNavigation
{
    public function __construct(private TournamentLinks $links) {}

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

            $championship = $this->links->currentChampionship();
            if ($championship !== null) {
                $add('championship', $championship['title'], $championship['url'], 'medal', $request->is('tournament', 'tournament/*'));
            }

            $add('pyramid', __('booking.nav.challenge_pyramid'), $this->links->pyramidUrl(), 'pyramid', $request->is('pyramide', 'pyramide/*'));
            $add('mixed', __('booking.nav.mixed_tournament'), $this->links->mixedUrl(), 'trophy', $request->is('mixed-tournament', 'mixed-tournament/*'));
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

    /** Item keys folded into one "Tournaments" dropdown in the header bar. */
    private const TOURNAMENT_KEYS = ['championship', 'pyramid', 'mixed'];

    /** Route names of the tournament area (all its modules, the draws included). */
    private const TOURNAMENT_ROUTES = ['pyramide.*', 'mixed.*', 'tournament.*', 'draw.*'];

    /** Whether the request is a page of the tournament area: the "Tournaments" entry is then current. */
    public static function inTournamentArea(Request $request): bool
    {
        return $request->routeIs(...self::TOURNAMENT_ROUTES);
    }

    /**
     * The header bar's entries: bar-only items, the tournaments collapsed into one group.
     * A group with a single entry shows that entry directly. With the request, the tournaments entry is current on
     * every page of the tournament area, also where none of its children matches (e.g. a draw record).
     *
     * @param  list<NavItem>  $items  as returned by items()
     * @return list<NavItem>
     */
    public function bar(array $items, ?Request $request = null): array
    {
        $inTournaments = $request !== null && self::inTournamentArea($request);
        $groups = [
            'tournaments' => ['label' => __('booking.nav.tournaments'), 'icon' => 'trophy', 'keys' => self::TOURNAMENT_KEYS],
        ];

        $bar = [];
        $placed = [];
        foreach ($items as $item) {
            if (! $item->inBar) {
                continue;
            }

            $groupKey = null;
            foreach ($groups as $key => $group) {
                if (in_array($item->key, $group['keys'], true)) {
                    $groupKey = $key;
                }
            }

            if ($groupKey === null) {
                $bar[] = $item;

                continue;
            }

            if (isset($placed[$groupKey])) {
                continue;
            }
            $placed[$groupKey] = true;

            $children = array_values(array_filter($items, fn (NavItem $i) => $i->inBar && in_array($i->key, $groups[$groupKey]['keys'], true)));
            $areaActive = $inTournaments; // the tournaments group is the only group
            if (count($children) === 1) {
                $child = $children[0];
                $bar[] = $areaActive && ! $child->active
                    ? new NavItem($child->key, $child->label, $child->url, $child->icon, true, $child->inBar, $child->children)
                    : $child;

                continue;
            }

            $active = $areaActive || array_filter($children, fn (NavItem $i) => $i->active) !== [];
            $bar[] = new NavItem($groupKey, $groups[$groupKey]['label'], $children[0]->url, $groups[$groupKey]['icon'], $active, true, $children);
        }

        return $bar;
    }
}
