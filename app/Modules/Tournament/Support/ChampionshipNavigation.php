<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Support;

use App\Models\User;
use App\Modules\TournamentBase\Support\TestModeSession;
use App\Modules\TournamentBase\Support\TournamentModuleNavigation;
use App\Services\TournamentLinks;
use App\Support\Navigation\AreaNavBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Sidebar entries of the club championship (formerly the module tabs, every page needs a login): Übersicht,
 * Gruppen, KO-Raster, Spielplan (with the match pages), Meine Spiele, Teilnehmer, Modus and Leitung (right
 * admin.tournament through User::can(), hidden while the admin views the tournament in the test mode, as the old
 * tabs were). Listed while a championship runs (as the header entry) or for its administrators; the draws add their
 * entries to the same group (DrawNavigation).
 */
final class ChampionshipNavigation implements TournamentModuleNavigation
{
    public function __construct(
        private readonly TournamentLinks $links,
        private readonly TestModeSession $testMode,
    ) {}

    public function key(): string
    {
        return 'championship';
    }

    public function group(): string
    {
        return 'championship';
    }

    public function label(): string
    {
        return __('tournament-base::common.nav.championship');
    }

    public function available(): bool
    {
        return Route::has('tournament.index');
    }

    public function matches(Request $request): bool
    {
        return $request->routeIs('tournament.*');
    }

    public function entries(AreaNavBuilder $nav): array
    {
        $user = $nav->user;
        if ($user === null) {
            return [];
        }

        if (! $this->matches($nav->request) && $this->links->currentChampionship() === null && ! $user->can('admin.tournament')) {
            return [];
        }

        $loggedIn = static fn (?User $viewer): bool => $viewer !== null;

        return [
            $nav->item('overview', __('tournament::common.tabs.overview'), route('tournament.index'), ['tournament.index'], $loggedIn),
            $nav->item('groups', __('tournament::common.tabs.groups'), route('tournament.groups'), ['tournament.groups'], $loggedIn),
            $nav->item('bracket', __('tournament::common.tabs.bracket'), route('tournament.bracket'), ['tournament.bracket'], $loggedIn),
            $nav->item('schedule', __('tournament::common.tabs.schedule'), route('tournament.schedule'), ['tournament.schedule', 'tournament.match*'], $loggedIn),
            $nav->item('mine', __('tournament::common.tabs.mine'), route('tournament.mine'), ['tournament.mine'], $loggedIn),
            $nav->item('players', __('tournament::common.tabs.players'), route('tournament.players'), ['tournament.players'], $loggedIn),
            $nav->item('mode', __('tournament::common.tabs.mode'), route('tournament.mode'), ['tournament.mode'], $loggedIn),
        ];
    }

    public function settingsEntries(AreaNavBuilder $nav): array
    {
        if ($nav->user === null) {
            return [];
        }

        return [
            $nav->item('admin', __('tournament::common.tabs.admin'), route('tournament.admin'), ['tournament.admin*'],
                fn (?User $viewer): bool => $viewer !== null && $viewer->can('admin.tournament') && $this->testMode->simulation($viewer) === null),
        ];
    }
}
