<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\Ui\NavItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Builds the main navigation once for the header bar and the phone menu.
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

            $gate = Gate::forUser($user);
            if ($gate->allows('admin.see-menu')) {
                $add('admin', __('booking.nav.admin'), route('admin.dashboard'), 'settings', $request->routeIs('admin.*'));
            }
            if ($gate->allows('admin.members')) {
                $add('members', __('booking.nav.members'), route('members.dashboard'), 'members', $request->routeIs('members.*'));
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
}
