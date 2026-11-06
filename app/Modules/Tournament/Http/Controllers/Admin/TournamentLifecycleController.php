<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\Tournament\Services\Admin\TournamentLifecycleService;
use App\Modules\Tournament\Support\AdminInput;
use App\Modules\Tournament\Support\TournamentAccess;
use App\Modules\TournamentBase\Support\ActionRefused;
use App\Modules\TournamentBase\Support\TestModeSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Create, activate, select and test-copy (old AdminController create/activate/selectTournament/cloneTest).
 * Access: route middleware (module login, then can:admin.tournament) before any posted id is read. Every
 * action returns to the admin page with ZF2's flash.
 */
final class TournamentLifecycleController extends Controller
{
    public function create(Request $request, TournamentLifecycleService $lifecycle): RedirectResponse
    {
        $templateTid = AdminInput::id($request, 'source_tid');
        $withPlayers = ! in_array(AdminInput::string($request, 'with_players'), ['', '0'], true);
        $title = trim(AdminInput::string($request, 'title'));

        try {
            $tournament = $lifecycle->create($title, AdminInput::int($request, 'season'), $templateTid ?: null, $withPlayers, $this->user($request));
        } catch (ActionRefused $e) {
            return $this->back('error', (string) __($e->key, $e->replace));
        } catch (Throwable $e) {
            // The raw message may carry SQL; the admin gets a generic text, the log the detail.
            report($e);

            return $this->back('error', (string) __('tournament::admin.lifecycle.create_failed'));
        }

        $copied = TournamentPlayer::query()->where('tid', $tournament->tid)->count();
        $source = Tournament::query()->find($templateTid);

        return $this->back('success', (string) __('tournament::admin.lifecycle.created', [
            'title' => $title,
            'season' => (int) $tournament->season,
            'source' => $source !== null ? (string) $source->title : '',
            'copied' => $copied > 0 ? (string) __('tournament::admin.lifecycle.created_players', ['count' => $copied]) : '',
        ]));
    }

    public function activate(Request $request, TournamentLifecycleService $lifecycle): RedirectResponse
    {
        $tid = AdminInput::id($request, 'tid');
        $tournament = $tid > 0 ? Tournament::query()->find($tid) : null;

        if ($tournament === null) {
            return $this->back('error', (string) __('tournament::admin.lifecycle.not_found'));
        }

        try {
            $lifecycle->activate($tournament);
        } catch (ActionRefused $e) {
            return $this->back('error', (string) __($e->key, $e->replace));
        } catch (Throwable $e) {
            report($e);

            return $this->back('error', (string) __('tournament::admin.lifecycle.activate_failed'));
        }

        return $this->back('success', (string) __('tournament::admin.lifecycle.activated', ['title' => (string) $tournament->title]));
    }

    public function cloneTest(Request $request, TournamentLifecycleService $lifecycle, TournamentAccess $access): RedirectResponse
    {
        $user = $this->user($request);

        try {
            $copy = $lifecycle->cloneTest($access->resolveTournament($user), $user);
        } catch (ActionRefused $e) {
            return $this->back('error', (string) __($e->key, $e->replace));
        }

        return $this->back('success', (string) __('tournament::admin.lifecycle.cloned', [
            'tid' => (int) $copy->tid,
            'count' => TournamentPlayer::query()->where('tid', $copy->tid)->count(),
        ]));
    }

    /**
     * The tournament the admin works on. Kept in the session, not in the URL, so it can be forgotten in no
     * link; an empty value switches back to the current real tournament.
     */
    public function select(Request $request, TestModeSession $testMode): RedirectResponse
    {
        $user = $this->user($request);
        $tid = AdminInput::id($request, 'tid');

        if ($tid === 0) {
            $testMode->selectTid($user, null);

            return $this->back('success', (string) __('tournament::admin.lifecycle.selected_current'));
        }

        $tournament = Tournament::query()->find($tid);

        if ($tournament === null) {
            return $this->back('error', (string) __('tournament::admin.lifecycle.not_found'));
        }

        $testMode->selectTid($user, $tid);

        return $this->back('success', (string) __('tournament::admin.lifecycle.selected', [
            'title' => (string) $tournament->title,
            'test' => $tournament->is_test ? (string) __('tournament::admin.lifecycle.selected_test') : '',
        ]));
    }

    /** A GET on a POST-only action returns to the admin page (ZF2). */
    public function redirectToAdmin(): RedirectResponse
    {
        return redirect()->route('tournament.admin');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function back(string $type, string $message): RedirectResponse
    {
        return redirect()->route('tournament.admin')->with($type, $message);
    }
}
