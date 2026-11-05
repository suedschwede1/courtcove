<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Pyramid\Http\Controllers\Concerns\ResolvesPyramidViewer;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use App\Modules\Pyramid\Services\LadderAdminService;
use App\Modules\Pyramid\Services\PyramidNotifier;
use App\Modules\Pyramid\Services\PyramidRepository;
use App\Modules\Pyramid\Support\PyramidLinks;
use App\Modules\Pyramid\Support\PyramidNotFound;
use App\Modules\Pyramid\Support\PyramidRuleException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Actions on one participant: admit from the waiting list, release to challenge, status, master data and position.
 *
 * The ladder is always taken from the player row, never from the request, and the Gate decides on THAT ladder: a
 * grant holder cannot touch a player of another ladder by guessing an id.
 */
class PlayerAdminController extends Controller
{
    use ResolvesPyramidViewer;

    public function __construct(
        private readonly PyramidRepository $repo,
        private readonly LadderAdminService $admin,
        private readonly PyramidNotifier $notifier,
    ) {}

    public function admit(Request $request, int $playerId): RedirectResponse
    {
        [$player, $ladder] = $this->administered($playerId);
        $back = redirect()->to(PyramidLinks::adminLadder((int) $ladder->id));

        try {
            $position = $this->admin->admit($player, $request->string('itn')->toString(), $this->actorUid());
        } catch (PyramidRuleException $e) {
            return $back->with('error', $e->verdict->message());
        }

        // After the commit, like the old code; a mail problem never undoes the admission.
        $this->notifier->admittedFromWaitlist((int) $player->id, $position);

        return $back->with('success', __('pyramid::admin.flash.admitted', ['label' => $player->label, 'position' => $position]));
    }

    public function releaseEntry(Request $request, int $playerId): RedirectResponse
    {
        [$player, $ladder] = $this->administered($playerId);
        $back = redirect()->to(PyramidLinks::adminLadder((int) $ladder->id));
        $release = $request->boolean('release');

        try {
            $this->admin->releaseEntry($player, $release, $this->actorUid());
        } catch (PyramidRuleException $e) {
            return $back->with('error', $e->verdict->message());
        }

        if (! $release) {
            return $back->with('success', __('pyramid::admin.flash.release_withdrawn'));
        }

        $this->notifier->releasedToEnter((int) $player->id);

        return $back->with('success', __('pyramid::admin.flash.released', ['label' => $player->label]));
    }

    public function status(Request $request, int $playerId): RedirectResponse
    {
        [$player, $ladder] = $this->administered($playerId);

        try {
            $applied = $this->admin->setStatus($player, $request->string('status')->toString(), $this->actorUid());
        } catch (InvalidArgumentException) {
            // ZF2 raised a RuntimeException here (an error page); a rejected request is the same outcome.
            abort(422, __('pyramid::admin.unknown_status'));
        }

        $back = redirect()->to(PyramidLinks::adminLadder((int) $ladder->id));

        // The row can vanish between the authorisation read and the lock: nothing changed, so no success message.
        if (! $applied) {
            return $back->with('error', __('pyramid::admin.no_player'));
        }

        return $back
            ->with('success', __('pyramid::admin.flash.status_changed', ['label' => $player->label]));
    }

    public function update(Request $request, int $playerId): RedirectResponse
    {
        [$player, $ladder] = $this->administered($playerId);
        $back = redirect()->to(PyramidLinks::adminLadder((int) $ladder->id));

        // A blank name must be reported, not silently replaced by the old one (empty strings arrive as null).
        $label = $request->has('label') ? trim((string) $request->input('label')) : (string) $player->label;

        try {
            $this->admin->updatePlayer(
                $player,
                ['label' => $label] + $request->only(['itn', 'gender', 'age', 'phone', 'note']),
                (int) $request->input('position', 0),
            );
        } catch (PyramidRuleException $e) {
            return $back->with('error', $e->verdict->message());
        }

        return $back->with('success', __('pyramid::admin.flash.player_updated', ['label' => $label]));
    }

    /**
     * @return array{0: PyramidPlayer, 1: PyramidLadder}
     */
    private function administered(int $playerId): array
    {
        $player = $this->repo->player($playerId);

        if ($player === null) {
            PyramidNotFound::abort(__('pyramid::admin.no_player'), true);
        }

        $ladder = $this->repo->ladder((int) $player->ladder_id);

        if ($ladder === null) {
            PyramidNotFound::abort(__('pyramid::ui.unknown_ladder'), true);
        }

        // Through the Gate (policy) on the ladder the player belongs to.
        abort_unless($this->canAdmin($ladder), 403, __('pyramid::admin.forbidden'));

        return [$player, $ladder];
    }

    private function actorUid(): int
    {
        return (int) Auth::user()->uid;
    }
}
