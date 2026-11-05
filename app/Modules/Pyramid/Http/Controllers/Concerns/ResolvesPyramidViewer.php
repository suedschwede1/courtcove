<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Http\Controllers\Concerns;

use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use App\Modules\Pyramid\Services\PyramidRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * What every Pyramid page needs to know about the request: which ladder, which participant row
 * belongs to the logged-in user, and whether that user administers the ladder.
 */
trait ResolvesPyramidViewer
{
    /**
     * The ladder of the route, else the default ladder; none at all is a 404 with the old text.
     */
    protected function resolveLadder(?int $ladderId = null): PyramidLadder
    {
        $repo = app(PyramidRepository::class);
        $ladder = $ladderId !== null ? $repo->ladder($ladderId) : $repo->defaultLadder();

        if ($ladder === null) {
            abort(404, __('pyramid::ui.no_ladder'));
        }

        return $ladder;
    }

    /**
     * The participant row of the logged-in user in this ladder (any status), or null.
     */
    protected function viewerFor(PyramidLadder $ladder): ?PyramidPlayer
    {
        $user = Auth::user();

        return $user === null ? null : app(PyramidRepository::class)->playerByUid((int) $ladder->id, (int) $user->uid);
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
