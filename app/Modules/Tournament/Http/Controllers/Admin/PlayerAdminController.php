<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\Tournament\Services\Admin\LotDecisionService;
use App\Modules\Tournament\Services\Admin\PartnerService;
use App\Modules\Tournament\Services\TournamentManager;
use App\Modules\Tournament\Support\AdminInput;
use App\Modules\Tournament\Support\TournamentAccess;
use App\Modules\TournamentBase\Support\ActionRefused;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Couples, player comments and manual lot decisions (old AdminController partner/comment/lot). Access: route
 * middleware (module login, then can:admin.tournament) before any posted or route id is read. Every id is
 * resolved through the admin's selected tournament.
 */
final class PlayerAdminController extends Controller
{
    public function partner(Request $request, PartnerService $partners, TournamentManager $m): RedirectResponse
    {
        $tournament = $this->tournament($request);
        $a = AdminInput::id($request, 'tpid_a');
        $b = AdminInput::id($request, 'tpid_b');

        try {
            if ($a > 0 && $b === 0) {
                $partners->unlink($tournament, $a);

                return $this->back('success', (string) __('tournament::admin.partner.unlinked'));
            }

            $partners->link($tournament, $a, $b);
        } catch (ActionRefused $e) {
            return $this->back('error', (string) __($e->key, $e->replace));
        }

        $tid = (int) $tournament->tid;

        return $this->back('success', (string) __('tournament::admin.partner.linked', [
            'a' => (string) ($m->player($tid, $a)['label'] ?? ''),
            'b' => (string) ($m->player($tid, $b)['label'] ?? ''),
        ]));
    }

    /** The tournament management's comment on a player (shown on the players page), at most 160 characters. */
    public function comment(Request $request, string $tpid, TournamentManager $m): RedirectResponse
    {
        $tournament = $this->tournament($request);
        $id = AdminInput::digits($tpid);
        $player = $id > 0 ? TournamentPlayer::query()->where('tid', $tournament->tid)->whereKey($id)->first() : null;

        abort_if($player === null, 404);

        // An array where the text belongs (note[]=x) is refused; it must not clear the comment.
        if ($request->has('note') && ! is_scalar($request->input('note')) && $request->input('note') !== null) {
            return redirect()->route('tournament.players')->with('error', __('tournament::admin.comment.invalid'));
        }

        $note = trim(AdminInput::string($request, 'note'));

        TournamentPlayer::query()->where('tid', $tournament->tid)->whereKey($id)
            ->update(['note' => $note === '' ? null : mb_substr($note, 0, 160)]);
        $m->forget((int) $tournament->tid);

        return redirect()->route('tournament.players')->with('success', __('tournament::admin.comment.saved'));
    }

    public function lot(Request $request, LotDecisionService $lots): RedirectResponse
    {
        $scope = trim(AdminInput::string($request, 'scope'));
        $clear = AdminInput::string($request, 'clear') === '1';

        try {
            if ($clear) {
                $lots->clear($this->tournament($request), $scope);
            } else {
                $lots->store($this->tournament($request), $scope, AdminInput::string($request, 'order'));
            }
        } catch (ActionRefused $e) {
            return $this->back('error', (string) __($e->key, $e->replace));
        }

        return $clear
            ? $this->back('success', (string) __('tournament::admin.lot.cleared', ['scope' => $scope]))
            : $this->back('success', (string) __('tournament::admin.lot.saved', ['scope' => $scope]));
    }

    /** A GET on the comment action returns to the players page (ZF2). */
    public function redirectToPlayers(): RedirectResponse
    {
        return redirect()->route('tournament.players');
    }

    private function tournament(Request $request): Tournament
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return app(TournamentAccess::class)->resolveTournament($user);
    }

    private function back(string $type, string $message): RedirectResponse
    {
        return redirect()->route('tournament.admin')->with($type, $message);
    }
}
