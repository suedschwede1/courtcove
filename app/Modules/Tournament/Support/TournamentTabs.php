<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Support;

use App\Models\User;
use App\Modules\Tournament\Models\Tournament;
use Illuminate\Support\Facades\Route;

/**
 * Module tabs of the championship (old partial/nav.phtml): Übersicht, Gruppen, Spielplan, KO-Raster,
 * Teilnehmer, Meine Spiele, Modus; admins additionally Auslosung and Leitung.
 *
 * The admin tabs follow the access level, so an admin in the test mode (level lowered) does not see them,
 * as in ZF2. Auslosung additionally needs the draw privilege. A tab whose route is not registered (yet)
 * is left out instead of linking nowhere.
 */
final class TournamentTabs
{
    private const TABS = [
        'overview' => 'tournament.index',
        'groups' => 'tournament.groups',
        'schedule' => 'tournament.schedule',
        'bracket' => 'tournament.bracket',
        'players' => 'tournament.players',
        'mine' => 'tournament.mine',
        'mode' => 'tournament.mode',
    ];

    /**
     * @param  string  $active  overview|groups|schedule|bracket|players|mine|mode|draw|admin
     * @return list<array{label: string, href: string, active: bool}>
     */
    public static function for(AccessContext $a, Tournament $t, string $active): array
    {
        $tabs = self::TABS;

        if ($a->isAdmin()) {
            $user = auth()->user();
            if ($user instanceof User && $user->can('admin.draw')) {
                $tabs['draw'] = 'draw.index';
            }
            $tabs['admin'] = 'tournament.admin';
        }

        $items = [];
        foreach ($tabs as $key => $route) {
            if (! Route::has($route)) {
                continue;
            }

            $items[] = [
                'label' => __('tournament::common.tabs.'.$key),
                'href' => route($route),
                'active' => $key === $active,
            ];
        }

        return $items;
    }
}
