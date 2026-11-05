<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pyramid\Http\Controllers\Concerns\ResolvesPyramidViewer;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use App\Modules\Pyramid\Services\ChallengeService;
use App\Modules\Pyramid\Services\PyramidNotifier;
use App\Modules\Pyramid\Services\PyramidRepository;
use App\Modules\Pyramid\Services\ScheduleParser;
use App\Modules\Pyramid\Support\PyramidLinks;
use App\Modules\Pyramid\Support\PyramidRuleException;
use App\Modules\Pyramid\Support\Verdict;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Issuing a challenge: GET asks for confirmation, POST creates it (routes sit behind the login middleware).
 */
class ChallengeController extends Controller
{
    use ResolvesPyramidViewer;

    public function __construct(
        private readonly PyramidRepository $repo,
        private readonly ChallengeService $challenges,
        private readonly ScheduleParser $parser,
        private readonly PyramidNotifier $notifier,
    ) {}

    public function confirm(int $playerId): View|RedirectResponse
    {
        [$ladder, $challenger, $opponent] = $this->parties($playerId);

        $verdict = $this->challenges->check($ladder, $challenger, $opponent);

        if ($verdict instanceof Verdict) {
            return $this->back($ladder, $verdict->message());
        }

        return view('pyramid::challenge-confirm', [
            'ladder' => $ladder,
            'ladders' => $this->repo->ladders(),
            'challenger' => $challenger,
            'opponent' => $opponent,
            'canAdmin' => $this->canAdmin($ladder),
        ]);
    }

    public function store(Request $request, int $playerId): RedirectResponse
    {
        [$ladder, $challenger, $opponent] = $this->parties($playerId);
        $uid = (int) Auth::user()->uid;

        $verdict = $this->challenges->check($ladder, $challenger, $opponent);

        if ($verdict instanceof Verdict) {
            return $this->back($ladder, $verdict->message());
        }

        try {
            $challenge = $this->challenges->create($ladder, $challenger, $opponent, $uid);
        } catch (PyramidRuleException $e) {
            return $this->back($ladder, $e->verdict->message());
        }

        $challengeId = (int) $challenge->id;

        // The transaction has returned: the challenge is booked whatever the mail server does.
        $this->notifier->challengeCreated($challengeId);

        // A bad date never discards the booked challenge, it only has to be entered again.
        $raw = trim((string) $request->input('scheduled_at', ''));
        $when = $raw === '' ? null : $this->parser->forSchedule($raw);
        $target = redirect()->to(PyramidLinks::challenge($challengeId));

        if ($when instanceof Verdict) {
            return $target->with('error', $when->message().' '.__('pyramid::ui.challenge.schedule_missing'));
        }

        if ($when === null) {
            return $target->with('success', __('pyramid::ui.challenge.created'));
        }

        $scheduledAt = date('Y-m-d H:i:s', $when);

        try {
            $this->challenges->transition($challengeId, [
                'scheduled_at' => $scheduledAt,
                'status' => 'scheduled',
            ], 'schedule', [
                'challenger' => (int) $challenger->id,
                'opponent' => (int) $opponent->id,
                'scheduled_at' => $scheduledAt,
            ], $uid);
        } catch (PyramidRuleException $e) {
            return $target->with('error', $e->verdict->message().' '.__('pyramid::ui.challenge.schedule_missing'));
        }

        $this->notifier->scheduled($challengeId);

        return $target->with('success', __('pyramid::ui.challenge.created_scheduled'));
    }

    /**
     * Ladder, the viewer's own participant row and the challenged player. Only an active or
     * released ("entering") participant may challenge; a waiting player is not released yet.
     * A logged-in non-participant gets a 403 here, never a login prompt (the middleware only handles guests).
     *
     * @return array{0: PyramidLadder, 1: PyramidPlayer, 2: PyramidPlayer}
     */
    private function parties(int $playerId): array
    {
        $opponent = $this->repo->player($playerId);

        if ($opponent === null) {
            abort(404, __('pyramid::ui.player.missing'));
        }

        $ladder = $this->resolveLadder((int) $opponent->ladder_id);
        $challenger = $this->viewerFor($ladder);

        if ($challenger === null || ! in_array($challenger->status, ['active', 'entering'], true)) {
            abort(403, __('pyramid::ui.challenge.not_participating'));
        }

        return [$ladder, $challenger, $opponent];
    }

    private function back(PyramidLadder $ladder, string $message): RedirectResponse
    {
        return redirect()->to(PyramidLinks::ladder((int) $ladder->id))->with('error', $message);
    }
}
