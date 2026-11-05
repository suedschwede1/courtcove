<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\Tournament\Services\NoCurrentTournament;
use App\Modules\Tournament\Support\TournamentAccess;
use Illuminate\Http\Request;

/**
 * Match page (old MatchController). Registered early with its final name and URL because the match cards
 * of the read pages link here; the page itself (edit, save, confirm, delete) is ported with the match
 * entry task. Until then a match of the viewer's tournament answers 501 and any other id 404.
 */
class MatchController extends Controller
{
    public function show(Request $request, TournamentAccess $access, int $tmid): never
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $tournament = $access->resolveTournament($user);
        } catch (NoCurrentTournament) {
            abort(404);
        }

        TournamentMatch::query()->where('tid', $tournament->tid)->where('tmid', $tmid)->firstOrFail();

        abort(501);
    }
}
