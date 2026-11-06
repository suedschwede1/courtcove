<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Services\Admin\BracketRefreshService;
use App\Modules\Tournament\Services\GenerationService;
use App\Modules\Tournament\Support\TournamentAccess;
use App\Modules\TournamentBase\Support\ModuleFlash;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * "Aus Format erzeugen" and "KO-Raster aktualisieren" (old AdminController generate/refreshBracket).
 * Access: route middleware (module login, then can:admin.tournament) before anything is read. Both work on
 * the admin's selected tournament (otherwise the current one) and return to the admin page with ZF2's
 * flash list.
 */
final class BracketAdminController extends Controller
{
    public function generate(Request $request, GenerationService $generation, TournamentAccess $access): RedirectResponse
    {
        try {
            $result = $generation->generate((int) $this->tournament($request, $access)->tid);
        } catch (Exception $e) {
            // The raw message may carry SQL or an internal English text: logged, a generic text is shown.
            Log::error('Generating the championship failed', ['exception' => $e]);
            ModuleFlash::error((string) __('tournament::admin.generate.failed', ['message' => __('tournament::admin.service.internal')]));

            return redirect()->route('tournament.admin');
        }

        $created = $result['created'];
        ModuleFlash::success((string) __('tournament::admin.generate.created', [
            'heads' => $created['heads'],
            'matches' => $created['matches'],
            'lines' => $created['lines'],
            'bracket' => $created['bracketMatches'],
        ]));

        foreach ($result['skipped'] as $note) {
            ModuleFlash::info((string) $note);
        }

        return redirect()->route('tournament.admin');
    }

    /** An exception rolls the whole refresh back and propagates (ZF2 rethrew it). */
    public function refreshBracket(Request $request, BracketRefreshService $refresh, TournamentAccess $access): RedirectResponse
    {
        $result = $refresh->refresh($this->tournament($request, $access));

        foreach ($result['notes'] as $note) {
            ModuleFlash::info($note);
        }

        ModuleFlash::success((string) __('tournament::admin.refresh.done', [
            'resolved' => $result['resolved'],
            'filled' => $result['filled'],
            'advanced' => $result['advanced'],
        ]));

        return redirect()->route('tournament.admin');
    }

    private function tournament(Request $request, TournamentAccess $access): Tournament
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $access->resolveTournament($user);
    }
}
