<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Support;

use App\Models\User;
use App\Modules\Pyramid\Models\PyramidGrant;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use App\Modules\Pyramid\Services\PyramidRepository;
use App\Modules\TournamentBase\Support\TournamentModuleNavigation;
use App\Support\Navigation\AreaNavBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Sidebar entries of the Pyramid (formerly the ladder tabs): Rangliste, Forderungen, Regeln (public, guests
 * included), Mein Profil (a logged-in participant of the shown ladder) and Verwaltung (global right
 * admin.pyramid or an admin grant on a ladder, both decided by the ladder policy and User::can()).
 * Detail pages count to their list: a challenge to Forderungen, a foreign player and the challenge
 * confirmation to Rangliste, the own player page to Mein Profil.
 */
final class PyramidNavigation implements TournamentModuleNavigation
{
    public function __construct(private readonly PyramidRepository $repo) {}

    public function key(): string
    {
        return 'pyramid';
    }

    public function group(): string
    {
        return 'pyramid';
    }

    public function label(): string
    {
        return __('tournament-base::common.nav.pyramid');
    }

    public function available(): bool
    {
        return Route::has('pyramide.index');
    }

    public function matches(Request $request): bool
    {
        return $request->routeIs('pyramide.*');
    }

    public function entries(AreaNavBuilder $nav): array
    {
        $own = $this->ownPlayer($nav->user, $nav->request);
        $ownId = $own !== null ? (int) $own->id : null;
        $isOwnProfile = static fn (Request $r): bool => $ownId !== null
            && $r->routeIs('pyramide.player') && (int) $r->route('playerId') === $ownId;
        $adminUrl = $this->adminUrl($nav->user);
        $rankingUrl = $nav->request->routeIs('pyramide.ladder')
            ? PyramidLinks::ladder((int) $nav->request->route('ladderId'))
            : route('pyramide.index');

        return [
            $nav->item('ranking', __('pyramid::ui.tab_ranking'), $rankingUrl,
                static fn (Request $r): bool => $r->routeIs('pyramide.index', 'pyramide.ladder', 'pyramide.challenge.confirm')
                    || ($r->routeIs('pyramide.player') && ! $isOwnProfile($r))),
            $nav->item('challenges', __('pyramid::ui.tab_challenges'), PyramidLinks::challenges(), ['pyramide.challenges', 'pyramide.challenge.*']),
            $nav->item('rules', __('pyramid::ui.tab_rules'), PyramidLinks::rules(), ['pyramide.rules']),
            $ownId === null ? null : $nav->item('profile', __('tournament-base::common.nav.my_profile'), PyramidLinks::player($ownId), $isOwnProfile,
                static fn (?User $user): bool => $user !== null),
            $nav->item('admin', __('pyramid::ui.tab_admin'), $adminUrl ?? PyramidLinks::adminIndex(), ['pyramide.admin.*'],
                static fn (?User $user): bool => $adminUrl !== null),
        ];
    }

    /**
     * The viewer's participant row in the shown ladder (route id, else the default ladder).
     */
    private function ownPlayer(?User $user, Request $request): ?PyramidPlayer
    {
        if ($user === null) {
            return null;
        }

        $ladderId = (int) $request->route('ladderId');
        $ladder = $ladderId > 0 ? $this->repo->ladder($ladderId) : $this->repo->defaultLadder();

        return $ladder === null ? null : $this->repo->playerByUid((int) $ladder->id, (int) $user->uid);
    }

    /**
     * Target of "Verwaltung": the overview for the global right, else the first ladder with an admin grant; null
     * when the viewer administers nothing. Through the Gate: the policy asks User::can('admin.pyramid').
     */
    private function adminUrl(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        if (Gate::forUser($user)->allows('administer', PyramidLadder::class)) {
            return PyramidLinks::adminIndex();
        }

        $grant = PyramidGrant::query()->where('uid', (int) $user->uid)->where('role', 'admin')->orderBy('ladder_id')->first();

        return $grant === null ? null : PyramidLinks::adminLadder((int) $grant->ladder_id);
    }
}
