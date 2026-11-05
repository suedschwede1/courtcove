<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Http\Controllers\Concerns;

use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use App\Modules\Pyramid\Support\PyramidNotFound;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * What every Pyramid page needs to know about the request: which ladder, which participant row
 * belongs to the logged-in user, and whether that user administers the ladder.
 */
trait ResolvesPyramidViewer
{
    /**
     * The ladder of the route, else the default ladder (no id and id 0 mean "the default", as in ZF2);
     * no ladder at all is a 404 with the old text, an unknown id a 404 naming the unknown ladder. Both render the
     * module's friendly not-found page (PyramidNotFound).
     *
     * Uses the controller's own injected `$repo`.
     */
    protected function resolveLadder(?int $ladderId = null): PyramidLadder
    {
        $byId = $ladderId !== null && $ladderId !== 0;
        $ladder = $byId ? $this->repo->ladder($ladderId) : $this->repo->defaultLadder();

        if ($ladder === null) {
            $fallback = $byId ? $this->repo->defaultLadder() : null;

            PyramidNotFound::abort(__($byId ? 'pyramid::ui.unknown_ladder' : 'pyramid::ui.no_ladder'), false, $fallback !== null);
        }

        return $ladder;
    }

    /**
     * The participant row of the logged-in user in this ladder (any status), or null.
     */
    protected function viewerFor(PyramidLadder $ladder): ?PyramidPlayer
    {
        $user = Auth::user();

        return $user === null ? null : $this->repo->playerByUid((int) $ladder->id, (int) $user->uid);
    }

    /**
     * Through the Gate on purpose: User::can() is the privilege check and never reaches the policy.
     */
    protected function canAdmin(?PyramidLadder $ladder = null): bool
    {
        $user = Auth::user();

        return $user !== null && Gate::forUser($user)->allows('administer', $ladder ?? PyramidLadder::class);
    }
}
