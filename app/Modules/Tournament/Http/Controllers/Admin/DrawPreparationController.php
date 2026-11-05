<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Draw\Models\Draw;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Services\Draws\BracketDrawService;
use App\Modules\Tournament\Services\Draws\DrawApplier;
use App\Modules\Tournament\Services\Draws\TokenDrawService;
use App\Modules\Tournament\Support\AdminInput;
use App\Modules\Tournament\Support\TournamentAccess;
use App\Modules\TournamentBase\Support\ActionRefused;
use App\Modules\TournamentBase\Support\ModuleFlash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The link to the Draw module (old AdminController prepareDraw/prepareBracketDraw/applyDraw). ZF2's
 * forward()->dispatch() calls are direct service calls. Access: route middleware (module login, then
 * can:admin.tournament) before any posted or route id is read; everything works on the admin's selected
 * tournament. A refusal returns to the admin page; a prepared draw goes to the draws overview as in ZF2.
 */
final class DrawPreparationController extends Controller
{
    public function prepareDraw(Request $request, TokenDrawService $service): RedirectResponse
    {
        [$user, $tournament] = $this->context($request);

        try {
            $result = $service->prepare(
                $tournament,
                AdminInput::string($request, 'phase'),
                AdminInput::int($request, 'round'),
                AdminInput::string($request, 'deadline'),
                $this->leadMinutes($request),
                AdminInput::int($request, 'in_minutes'),
                $user,
            );
        } catch (ActionRefused $e) {
            return redirect()->route('tournament.admin')->with('error', __($e->key, $e->replace));
        }

        return $this->toDraws($user, $result['flashes']);
    }

    public function prepareBracketDraw(Request $request, BracketDrawService $service): RedirectResponse
    {
        [$user, $tournament] = $this->context($request);

        try {
            $result = $service->prepare(
                $tournament,
                AdminInput::string($request, 'phase'),
                AdminInput::string($request, 'pool'),
                AdminInput::int($request, 'round'),
                $this->leadMinutes($request),
                $user,
            );
        } catch (ActionRefused $e) {
            return redirect()->route('tournament.admin')->with('error', __($e->key, $e->replace));
        }

        return $this->toDraws($user, $result['flashes']);
    }

    /**
     * Transfers an executed draw into the group allocation or the bracket lines. Deliberately a separate
     * step: between draw and effect the record should be looked at. Only a draw of the selected tournament
     * passes DrawApplier's checks.
     */
    public function applyDraw(Request $request, string $did, DrawApplier $applier): RedirectResponse
    {
        [$user, $tournament] = $this->context($request);
        $id = AdminInput::digits($did);
        $draw = $id > 0 ? Draw::query()->find($id) : null;

        if ($draw === null) {
            return redirect()->route('tournament.admin')->with('error', __('tournament::admin.apply.invalid'));
        }

        try {
            $count = $applier->apply($draw, (int) $tournament->tid, $user);
        } catch (ActionRefused $e) {
            return redirect()->route('tournament.admin')->with('error', __($e->key, $e->replace));
        }

        return redirect()->route('tournament.admin')->with('success', __('tournament::admin.apply.success', ['count' => $count]));
    }

    /** A GET on a POST-only action returns to the admin page (ZF2). */
    public function redirectToAdmin(): RedirectResponse
    {
        return redirect()->route('tournament.admin');
    }

    /** ZF2's fromPost('lead_minutes', 2): the default only when the field is missing. */
    private function leadMinutes(Request $request): int
    {
        return $request->has('lead_minutes') ? AdminInput::int($request, 'lead_minutes') : 2;
    }

    /** @return array{0: User, 1: Tournament} */
    private function context(Request $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return [$user, app(TournamentAccess::class)->resolveTournament($user)];
    }

    /**
     * ZF2 went on to the draws overview. A tournament admin without the draw privilege could not open it, so
     * the flashes are shown on the tournament admin page instead.
     *
     * @param  list<array{type: string, text: string}>  $flashes
     */
    private function toDraws(User $user, array $flashes): RedirectResponse
    {
        foreach ($flashes as $flash) {
            $flash['type'] === 'error' ? ModuleFlash::error($flash['text']) : ModuleFlash::success($flash['text']);
        }

        return redirect()->route($user->can('admin.draw') ? 'draw.index' : 'tournament.admin');
    }
}
