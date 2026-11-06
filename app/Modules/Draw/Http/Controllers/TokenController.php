<?php

declare(strict_types=1);

namespace App\Modules\Draw\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\Draw\Services\TokenPicker;
use App\Modules\Draw\Support\TokenPageContext;
use App\Modules\Tournament\Services\NoCurrentTournament;
use App\Modules\Tournament\Services\TournamentManager;
use App\Modules\Tournament\Support\TournamentTabs;
use App\Modules\TournamentBase\Support\ActionRefused;
use App\Modules\TournamentBase\Support\ModuleFlash;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Token pick for the participants (ported from ZF2 Draw TokenController). The page is an offer, not a
 * mandatory step: whoever does not pick loses nothing, the free tokens are handed out on close.
 *
 * Login is checked by the route middleware before the draw id is resolved; an unknown draw is a 404.
 */
final class TokenController extends Controller
{
    public function show(Request $request, string $did, TournamentManager $m): View
    {
        $c = $this->context($request, $did);

        $tokens = DrawToken::query()->where('did', $c->did)->orderBy('position')->get()
            ->map(fn (DrawToken $t): array => $t->getAttributes())->all();

        $own = null;
        $free = 0;
        $selectable = 0;

        foreach ($tokens as $token) {
            if ($c->player !== null && (int) $token['claimed_tpid'] === (int) $c->player['tpid']) {
                $own = $token;
            }

            if ($token['claimed_tpid']) {
                continue;
            }

            // Two numbers on purpose: the header names what THIS player can take, otherwise "10 von 10 frei"
            // over seven visible entries looks like an error.
            $free++;

            if (! isset($c->blockedTokens[(int) $token['dtid']])) {
                $selectable++;
            }
        }

        return view('draw::tokens', [
            'c' => $c,
            'draw' => $c->draw,
            'tokens' => $tokens,
            'players' => $m->players($c->tid),
            'own' => $own,
            'free' => $free,
            'selectable' => $selectable,
            'hiddenCount' => count($c->blockedTokens),
            // tournament::layout
            'tournament' => $c->tournament,
            'access' => $c->access,
            'tabs' => TournamentTabs::for($c->access, $c->tournament, 'draw'),
            'stopUrl' => Route::has('tournament.admin.simulate') ? route('tournament.admin.simulate') : null,
            'testTournament' => $c->isTest ? (string) $c->tournament->title : null,
            'tokenHint' => null,
        ]);
    }

    public function pick(Request $request, string $did, TokenPicker $picker): RedirectResponse
    {
        $c = $this->context($request, $did);
        $raw = $request->input('dtid');
        $dtid = is_string($raw) && ctype_digit($raw) && strlen($raw) <= 10 ? (int) $raw : 0;

        try {
            foreach ($picker->pick($c, $dtid, $this->user($request)) as $flash) {
                $flash['type'] === 'error' ? ModuleFlash::error($flash['text']) : ModuleFlash::success($flash['text']);
            }
        } catch (ActionRefused $e) {
            ModuleFlash::error((string) __($e->key, $e->replace));
        }

        return redirect()->route('draw.tokens', ['did' => $c->did]);
    }

    /** GET on the pick path (ZF2 redirected to the token page). */
    public function redirectToShow(string $did): RedirectResponse
    {
        $draw = $this->draw($did);

        return redirect()->route('draw.tokens', ['did' => (int) $draw->did]);
    }

    private function context(Request $request, string $did): TokenPageContext
    {
        $draw = $this->draw($did);

        try {
            return TokenPageContext::load($draw, $this->user($request));
        } catch (NoCurrentTournament) {
            abort(404);
        }
    }

    private function draw(string $did): Draw
    {
        abort_unless(ctype_digit($did) && strlen($did) <= 10, 404);

        return Draw::query()->findOrFail((int) $did);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
