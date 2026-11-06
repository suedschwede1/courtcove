<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Support;

use App\Models\User;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawEligible;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Services\FormatService;
use App\Modules\Tournament\Services\NoCurrentTournament;
use App\Modules\Tournament\Services\TournamentManager;
use Illuminate\Support\Facades\Route;

/**
 * Shared context of every championship page (old IndexController::context() and partial/nav.phtml):
 * the tournament (resolved first, then the access level), the test mode and test
 * tournament bars and the open token-draw hint.
 */
final class TournamentPage
{
    public function __construct(
        private readonly TournamentAccess $access,
        private readonly TournamentManager $m,
    ) {}

    /**
     * The page context, or null when no championship exists at all.
     *
     * @param  string  $active  key of the page (kept for the callers; the sidebar finds the active entry by route)
     * @return array{tournament: Tournament, tid: int, format: FormatService, access: AccessContext, showContacts: bool, tokenHint: array{did: int, title: string, deadline: string|null, url: string|null}|null, stopUrl: string|null, testTournament: string|null, players: array<int, array<string, mixed>>}|null
     */
    public function build(User $user, string $active): ?array
    {
        try {
            $t = $this->access->resolveTournament($user);
        } catch (NoCurrentTournament) {
            return null;
        }

        $tid = (int) $t->tid;
        $access = $this->access->context($user, $t);

        return [
            'tournament' => $t,
            'tid' => $tid,
            'format' => $this->m->format($tid),
            'access' => $access,
            'showContacts' => $access->level >= TournamentAccess::PARTICIPANT,
            'tokenHint' => $this->tokenHint($tid, $access->tpid),
            'stopUrl' => Route::has('tournament.admin.simulate') ? route('tournament.admin.simulate') : null,
            'testTournament' => ! empty($t->is_test) ? (string) $t->title : null,
            'players' => $this->m->players($tid),
        ];
    }

    /**
     * Open hint on a running token draw (old IndexController::tokenHint()).
     *
     * Only a hint: the token pick is an offer, not a mandatory step. Shown when the viewer is in the pot of a
     * picking token draw of this tournament and has no token yet.
     *
     * @return array{did: int, title: string, deadline: string|null, url: string|null}|null
     */
    private function tokenHint(int $tid, ?int $tpid): ?array
    {
        if (! $tpid) {
            return null;
        }

        $draws = Draw::query()->where('tid', $tid)->orderBy('did')->get();

        foreach ($draws as $draw) {
            if ($draw->status !== 'picking' || $draw->mode !== 'token') {
                continue;
            }

            if (! DrawEligible::query()->where('did', $draw->did)->where('tpid', $tpid)->exists()) {
                continue;
            }

            if (DrawToken::query()->where('did', $draw->did)->where('claimed_tpid', $tpid)->exists()) {
                continue;
            }

            return [
                'did' => (int) $draw->did,
                'title' => (string) $draw->title,
                'deadline' => $draw->pick_deadline,
                'url' => Route::has('draw.tokens') ? route('draw.tokens', ['did' => (int) $draw->did]) : null,
            ];
        }

        return null;
    }
}
