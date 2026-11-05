<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tournament\Services\Admin\ResetService;
use App\Modules\Tournament\Services\Admin\ResultSimulator;
use App\Modules\Tournament\Services\TournamentManager;
use App\Modules\Tournament\Support\AdminInput;
use App\Modules\Tournament\Support\TournamentAccess;
use App\Modules\TournamentBase\Support\ActionRefused;
use App\Modules\TournamentBase\Support\ModuleFlash;
use App\Modules\TournamentBase\Support\TestModeSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Random\Randomizer;

/**
 * Test tools of the championship admin (old AdminController simulate, simulateResults, reset).
 * Access: route middleware (module login, then can:admin.tournament) before any posted id is read. All
 * three work on the admin's selected tournament (otherwise the current one).
 *
 * The test mode only stores a view in its own session key (TestModeSession); TournamentAccess can only
 * lower the level from it. Random results and the reset refuse what ZF2 refused: random results exist only
 * in a test tournament, and a real tournament is reset only with its exact title typed in.
 */
final class TestToolsController extends Controller
{
    public function simulate(Request $request, TestModeSession $testMode, TournamentAccess $access, TournamentManager $m): RedirectResponse
    {
        $user = $this->user($request);
        $mode = $request->has('mode') ? AdminInput::string($request, 'mode') : 'player';

        if ($mode === 'off' || $mode === 'stop') {
            $testMode->stop();

            return redirect()->route('tournament.admin')->with('success', __('tournament::admin.simulate.stopped'));
        }

        $tid = (int) $access->resolveTournament($user)->tid;
        $tpid = AdminInput::id($request, 'tpid');
        $player = null;

        if ($mode === 'player' && $tpid > 0) {
            // Parent scoping: only a participant of the selected tournament can be simulated (ZF2 accepted
            // any number and ended the simulation silently on the next page).
            $player = $m->player($tid, $tpid);

            if ($player === null) {
                return redirect()->route('tournament.admin')->with('error', __('tournament::admin.simulate.foreign_player'));
            }
        }

        try {
            $testMode->simulate($user, $mode, $tpid > 0 ? $tpid : null);
        } catch (InvalidArgumentException) {
            return redirect()->route('tournament.admin')->with('error', __('tournament::admin.simulate.invalid'));
        }

        $message = $mode === 'member'
            ? __('tournament::admin.simulate.member')
            : __('tournament::admin.simulate.player', ['label' => (string) $player['label']]);

        return redirect()->route('tournament.index')->with('success', $message);
    }

    public function simulateResults(Request $request, ResultSimulator $simulator, TournamentAccess $access, Randomizer $randomizer): RedirectResponse
    {
        $user = $this->user($request);

        try {
            $result = $simulator->simulate($access->resolveTournament($user), $randomizer, $user);
        } catch (ActionRefused $e) {
            return redirect()->route('tournament.admin')->with('error', __($e->key, $e->replace));
        }

        foreach ($result['problems'] as $problem) {
            ModuleFlash::error($problem);
        }

        ModuleFlash::success((string) __('tournament::admin.simulate_results.done', ['filled' => $result['filled'], 'skipped' => $result['skipped']]));

        return redirect()->route('tournament.admin');
    }

    public function reset(Request $request, ResetService $reset, TournamentAccess $access): RedirectResponse
    {
        $user = $this->user($request);
        $scope = AdminInput::string($request, 'scope');
        $confirm = $request->has('confirm') && is_scalar($request->input('confirm')) ? (string) $request->input('confirm') : null;

        try {
            $lines = $reset->reset($access->resolveTournament($user), $scope, $confirm, AdminInput::string($request, 'reason'), $user);
        } catch (ActionRefused $e) {
            return redirect()->route('tournament.admin')->with('error', __($e->key, $e->replace));
        }

        foreach ($lines as $line) {
            ModuleFlash::success($line);
        }

        if ($scope !== 'results') {
            ModuleFlash::info((string) __('tournament::admin.reset.done_next'));
        }

        return redirect()->route('tournament.admin');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
