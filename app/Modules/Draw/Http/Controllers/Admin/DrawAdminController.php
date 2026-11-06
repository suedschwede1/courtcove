<?php

declare(strict_types=1);

namespace App\Modules\Draw\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Draw\Contracts\BeaconService;
use App\Modules\Draw\Http\Requests\CommitDrawRequest;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Services\CommitRefused;
use App\Modules\Draw\Services\DrawCommitService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Draws overview and the manual commit form (old Draw AdminController::indexAction / commitAction).
 * Access: route middleware (module login, then can:admin.draw).
 */
final class DrawAdminController extends Controller
{
    public function index(BeaconService $beacon): View
    {
        // Suggested beacon round: the one in about an hour. A dead beacon only shows a notice.
        $suggestedRound = null;
        $beaconError = null;

        try {
            $suggestedRound = $beacon->roundAt(now()->getTimestamp() + 3600);
        } catch (Exception $e) {
            $beaconError = $e->getMessage();
        }

        $errors = session('draw_errors');

        return view('draw::admin.index', [
            'draws' => Draw::query()->orderByDesc('did')->get(),
            'suggestedRound' => $suggestedRound,
            'beaconError' => $beaconError,
            'commitErrors' => is_array($errors) ? array_values(array_filter($errors, 'is_string')) : [],
        ]);
    }

    public function commit(CommitDrawRequest $request, DrawCommitService $commits): RedirectResponse
    {
        $input = $request->commitInput();
        /** @var User $user */
        $user = $request->user();

        try {
            $draw = $commits->commitManual($input, $user);
        } catch (CommitRefused $e) {
            return redirect()->route('draw.index')->with('draw_errors', $e->messages())->withInput($input);
        }

        return redirect()->route('draw.index')->with('success', __('draw::admin.commit.success', [
            'title' => $draw->title,
            'round' => (int) $input['round'],
        ]));
    }

    public function redirectToIndex(): RedirectResponse
    {
        return redirect()->route('draw.index');
    }
}
