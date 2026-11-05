<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Pyramid\Http\Controllers\Concerns\ResolvesPyramidViewer;
use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Services\LadderAdminService;
use App\Modules\Pyramid\Services\PyramidNotifier;
use App\Modules\Pyramid\Services\PyramidRepository;
use App\Modules\Pyramid\Support\PyramidLinks;
use App\Modules\Pyramid\Support\PyramidRuleException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Administration decisions on one challenge: overdue handling (extend, expire, walkover) and result corrections.
 *
 * The ladder is always taken from the challenge row, never from the request, and the Gate decides on THAT ladder: a
 * grant holder cannot touch a challenge of another ladder by guessing an id. Mails go out after the service's
 * transaction has returned, like ZF2.
 */
class ChallengeAdminController extends Controller
{
    use ResolvesPyramidViewer;

    public function __construct(
        private readonly PyramidRepository $repo,
        private readonly LadderAdminService $admin,
        private readonly PyramidNotifier $notifier,
    ) {}

    public function override(Request $request, int $challengeId): RedirectResponse
    {
        [$challenge, $ladder] = $this->administered($challengeId);
        $back = redirect()->to(PyramidLinks::adminLadder((int) $ladder->id));

        $decision = $request->string('decision')->toString();
        $note = trim((string) $request->input('admin_note', ''));

        // All three ways out need a reason (spec 6.2).
        if ($note === '') {
            return $back->with('error', __('pyramid::admin.note_required'));
        }

        if (! in_array($decision, ['extend', 'expired', 'forfeit_challenger', 'forfeit_opponent'], true)) {
            abort(422, __('pyramid::admin.unknown_decision'));
        }

        $actor = $this->actorUid();

        try {
            if ($decision === 'extend') {
                $deadline = $this->admin->extendDeadline($challenge, $request->string('new_deadline')->toString(), $note, $actor);
                $this->notifier->deadlineExtended((int) $challenge->id, $note);

                return $back->with('success', __('pyramid::admin.flash.extended', ['date' => $deadline]));
            }

            if ($decision === 'expired') {
                $this->admin->expire($challenge, $note, $actor);
                $this->notifier->expired((int) $challenge->id, $note);

                return $back->with('success', __('pyramid::admin.flash.expired'));
            }

            // A walkover has the effect of a played match on the ranking, hence the result service behind forfeit().
            $this->admin->forfeit($challenge, $decision === 'forfeit_opponent', $note, $actor);
            $this->notifier->forfeited((int) $challenge->id, $note);
        } catch (PyramidRuleException $e) {
            return $back->with('error', $e->verdict->message());
        }

        return $back->with('success', __('pyramid::admin.flash.forfeited'));
    }

    public function correctResult(Request $request, int $challengeId): RedirectResponse
    {
        [$challenge, $ladder] = $this->administered($challengeId);
        $back = redirect()->to(PyramidLinks::adminLadder((int) $ladder->id));

        try {
            $this->admin->correctResult(
                $challenge,
                (int) $request->input('winner_id', 0),
                (string) $request->input('score', ''),
                (string) $request->input('admin_note', ''),
                $this->actorUid(),
            );
        } catch (PyramidRuleException $e) {
            return $back->with('error', $e->verdict->message());
        } catch (InvalidArgumentException) {
            // ZF2 raised a RuntimeException here (an error page); a rejected request is the same outcome.
            abort(422, __('pyramid::admin.winner_not_participant'));
        }

        $this->notifier->resultCorrected((int) $challenge->id, trim((string) $request->input('admin_note', '')));

        return $back->with('success', __('pyramid::admin.flash.corrected'));
    }

    /**
     * @return array{0: PyramidChallenge, 1: PyramidLadder}
     */
    private function administered(int $challengeId): array
    {
        $challenge = $this->repo->challenge($challengeId);

        if ($challenge === null) {
            abort(404, __('pyramid::check.challenge_missing'));
        }

        $ladder = $this->repo->ladder((int) $challenge->ladder_id);

        if ($ladder === null) {
            abort(404, __('pyramid::ui.no_ladder'));
        }

        // Through the Gate (policy) on the ladder the challenge belongs to.
        abort_unless($this->canAdmin($ladder), 403, __('pyramid::admin.forbidden'));

        return [$challenge, $ladder];
    }

    private function actorUid(): int
    {
        return (int) Auth::user()->uid;
    }
}
