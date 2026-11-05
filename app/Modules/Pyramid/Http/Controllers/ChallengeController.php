<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pyramid\Http\Controllers\Concerns\ResolvesPyramidViewer;
use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use App\Modules\Pyramid\Services\ChallengeService;
use App\Modules\Pyramid\Services\PyramidNotifier;
use App\Modules\Pyramid\Services\PyramidRepository;
use App\Modules\Pyramid\Services\ResultService;
use App\Modules\Pyramid\Services\ScheduleParser;
use App\Modules\Pyramid\Support\EventText;
use App\Modules\Pyramid\Support\PyramidLinks;
use App\Modules\Pyramid\Support\PyramidNotFound;
use App\Modules\Pyramid\Support\PyramidRuleException;
use App\Modules\Pyramid\Support\Verdict;
use App\Modules\TournamentBase\Services\InvalidScoreException;
use App\Modules\TournamentBase\Services\ScoreService;
use App\Modules\TournamentBase\Support\WhatsApp;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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
        private readonly ResultService $results,
        private readonly ScoreService $scores,
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
     * The challenge page. Public to read; phone numbers only for the administration and, while the
     * challenge runs, for the other side.
     */
    public function show(Request $request, int $challengeId): View
    {
        $challenge = $this->repo->challenge($challengeId);
        $challenger = $challenge === null ? null : $this->repo->player((int) $challenge->challenger_id);
        $opponent = $challenge === null ? null : $this->repo->player((int) $challenge->opponent_id);

        if ($challenge === null || $challenger === null || $opponent === null) {
            PyramidNotFound::abort(__('pyramid::ui.detail.missing'));
        }

        $ladder = $this->resolveLadder((int) $challenge->ladder_id);
        $viewer = $this->viewerFor($ladder);
        $isChallenger = $viewer !== null && (int) $viewer->id === (int) $challenger->id;
        $isInvolved = $isChallenger || ($viewer !== null && (int) $viewer->id === (int) $opponent->id);
        $canAdmin = $this->canAdmin($ladder);
        $canSeeContact = $canAdmin || ($isInvolved && $challenge->isRunning());

        $other = $isChallenger ? $opponent : $challenger;
        $contact = null;
        $adminContacts = [];

        if ($isInvolved && $canSeeContact && filled($other->phone)) {
            $contact = $this->contact($other);
        }

        // The administration has no "other side": it sees both numbers.
        if ($canAdmin && ! $isInvolved) {
            $adminContacts = [$this->contact($challenger), $this->contact($opponent)];
        }

        // After a refused submit the kept input is explained once more, in the viewer's language.
        $old = array_filter($request->old(), 'is_scalar');
        $scoreHint = $old === [] ? null : $this->scores->describe($old);

        return view('pyramid::challenge', [
            'ladder' => $ladder,
            'ladders' => $this->repo->ladders(),
            'challenge' => $challenge,
            'challenger' => $challenger,
            'opponent' => $opponent,
            'viewer' => $viewer,
            'isChallenger' => $isChallenger,
            'isInvolved' => $isInvolved,
            'canAdmin' => $canAdmin,
            'canSeeContact' => $canSeeContact,
            'contact' => $contact,
            'adminContacts' => $adminContacts,
            'scoreHint' => $scoreHint,
        ]);
    }

    /**
     * Live check of the result form: the validator of the submit (ResultService::evaluate), nothing is written.
     * Only the two parties may ask, like for the submit itself. States: empty, incomplete, invalid, valid.
     */
    public function scoreCheck(Request $request, int $challengeId): JsonResponse
    {
        $challenge = $this->repo->challenge($challengeId);

        if ($challenge === null) {
            PyramidNotFound::abort(__('pyramid::ui.detail.missing'));
        }

        $viewer = $this->viewerFor($this->resolveLadder((int) $challenge->ladder_id));

        if ($viewer === null || ! in_array((int) $viewer->id, [(int) $challenge->challenger_id, (int) $challenge->opponent_id], true)) {
            abort(403, __('pyramid::ui.detail.not_involved'));
        }

        $input = [];

        foreach (['s1a', 's1b', 's2a', 's2b', 'mtba', 'mtbb'] as $key) {
            $value = $request->query($key);
            $input[$key] = is_scalar($value) ? (string) $value : null;
        }

        if (array_filter($input, static fn (?string $value): bool => $value !== null && trim($value) !== '') === []) {
            return response()->json(['valid' => false, 'state' => 'empty']);
        }

        try {
            $evaluated = $this->results->evaluate($input);
        } catch (InvalidScoreException $e) {
            // Not finished yet is no mistake: the form says what is missing, not that it is wrong.
            $incomplete = in_array($e->key, ['tournament-base::score.sets_incomplete', 'tournament-base::score.mtb_required', 'tournament-base::score.mtb_both_scores'], true);

            return response()->json([
                'valid' => false,
                'state' => $incomplete ? 'incomplete' : 'invalid',
                'message' => __($e->key, $e->replace),
            ]);
        }

        $winnerId = $evaluated['winner_side'] === 'a' ? (int) $challenge->challenger_id : (int) $challenge->opponent_id;
        $winner = (string) ($this->repo->player($winnerId)->label ?? '');

        return response()->json([
            'valid' => true,
            'state' => 'valid',
            'winner' => $winner,
            'score' => (string) $evaluated['score'],
            'message' => __('pyramid::ui.detail.score_valid', ['name' => $winner]),
        ]);
    }

    public function schedule(Request $request, int $challengeId): RedirectResponse
    {
        [$ladder, $challenge, $viewer] = $this->involvement($challengeId);

        if ($challenge instanceof RedirectResponse) {
            return $challenge;
        }

        $target = redirect()->to(PyramidLinks::challenge($challengeId));
        $when = $this->parser->forSchedule($request->string('scheduled_at')->toString());

        if ($when instanceof Verdict) {
            return $target->with('error', $when->message());
        }

        $scheduledAt = date('Y-m-d H:i:s', $when);

        try {
            $this->challenges->transition($challengeId, [
                'scheduled_at' => $scheduledAt,
                'status' => 'scheduled',
            ], 'schedule', [
                'challenger' => (int) $challenge->challenger_id,
                'opponent' => (int) $challenge->opponent_id,
                'scheduled_at' => $scheduledAt,
            ], (int) Auth::user()->uid);
        } catch (PyramidRuleException $e) {
            return $target->with('error', $e->verdict->message());
        }

        $this->notifier->scheduled($challengeId);

        // A date after the deadline is allowed (rules 3.6, 3.7); the deadline itself does not move.
        // As in ZF2 the info notice is flashed first, then the confirmation.
        $deadline = $challenge->deadline ? strtotime($challenge->deadline.' 23:59:59') : false;

        if ($deadline && $when > $deadline) {
            $target->with('info', __('pyramid::ui.detail.after_deadline', ['date' => date('d.m.Y', (int) strtotime($challenge->deadline))]));
        }

        return $target->with('success', __('pyramid::ui.detail.scheduled_saved'));
    }

    public function result(Request $request, int $challengeId): RedirectResponse
    {
        [$ladder, $challenge] = $this->involvement($challengeId);

        if ($challenge instanceof RedirectResponse) {
            return $challenge;
        }

        $target = redirect()->to(PyramidLinks::challenge($challengeId));
        $input = [];

        foreach (['s1a', 's1b', 's2a', 's2b', 'mtba', 'mtbb'] as $key) {
            $value = $request->input($key);
            $input[$key] = is_scalar($value) ? $value : null;
        }

        try {
            $this->results->record($ladder, $challenge, $input, (int) Auth::user()->uid);
        } catch (InvalidScoreException $e) {
            return $target->with('error', __($e->key, $e->replace))->withInput($input);
        } catch (PyramidRuleException $e) {
            return $target->with('error', $e->verdict->message());
        }

        $this->notifier->resultEntered($challengeId);

        return redirect()->to(PyramidLinks::ladder((int) $ladder->id))
            ->with('success', __('pyramid::ui.detail.result_saved'));
    }

    /**
     * Rule 5.2: the challenged player declines with one of the two permitted reasons.
     */
    public function decline(Request $request, int $challengeId): RedirectResponse
    {
        [$ladder, $challenge, $viewer] = $this->involvement($challengeId);

        if ($challenge instanceof RedirectResponse) {
            return $challenge;
        }

        if ((int) $viewer->id !== (int) $challenge->opponent_id) {
            abort(403, __('pyramid::ui.detail.only_opponent_declines'));
        }

        $target = redirect()->to(PyramidLinks::challenge($challengeId));
        $reason = $request->input('reason');

        if (! in_array($reason, ['other_match', 'injury'], true)) {
            return $target->with('error', __('pyramid::ui.detail.reason_invalid'));
        }

        try {
            $this->challenges->transition($challengeId, [
                'status' => 'declined',
                'decline_reason' => $reason,
                'resolved' => now()->format('Y-m-d H:i:s'),
            ], 'admin', ['message' => EventText::DECLINED], (int) Auth::user()->uid);
        } catch (PyramidRuleException $e) {
            return $target->with('error', $e->verdict->message());
        }

        $this->notifier->declined($challengeId);

        return redirect()->to(PyramidLinks::ladder((int) $ladder->id))
            ->with('success', __('pyramid::ui.detail.declined_saved'));
    }

    public function withdraw(int $challengeId): RedirectResponse
    {
        [$ladder, $challenge, $viewer] = $this->involvement($challengeId);

        if ($challenge instanceof RedirectResponse) {
            return $challenge;
        }

        if ((int) $viewer->id !== (int) $challenge->challenger_id) {
            abort(403, __('pyramid::ui.detail.only_challenger_withdraws'));
        }

        try {
            $this->challenges->transition($challengeId, [
                'status' => 'withdrawn',
                'resolved' => now()->format('Y-m-d H:i:s'),
            ], 'admin', ['message' => EventText::WITHDRAWN], (int) Auth::user()->uid);
        } catch (PyramidRuleException $e) {
            return redirect()->to(PyramidLinks::challenge($challengeId))->with('error', $e->verdict->message());
        }

        $this->notifier->withdrawn($challengeId);

        return redirect()->to(PyramidLinks::ladder((int) $ladder->id))
            ->with('success', __('pyramid::ui.detail.withdrawn_saved'));
    }

    /**
     * Challenge, ladder and the viewer's participant row for an action: only the two parties may act (a
     * logged-in outsider gets a 403, never a login prompt), and only while the challenge runs.
     *
     * @return array{0: PyramidLadder, 1: PyramidChallenge|RedirectResponse, 2: PyramidPlayer}
     */
    private function involvement(int $challengeId): array
    {
        $challenge = $this->repo->challenge($challengeId);

        if ($challenge === null) {
            PyramidNotFound::abort(__('pyramid::ui.detail.missing'));
        }

        $ladder = $this->resolveLadder((int) $challenge->ladder_id);
        $viewer = $this->viewerFor($ladder);

        if ($viewer === null || ! in_array((int) $viewer->id, [(int) $challenge->challenger_id, (int) $challenge->opponent_id], true)) {
            abort(403, __('pyramid::ui.detail.not_involved'));
        }

        if (! $challenge->isRunning()) {
            $challenge = redirect()->to(PyramidLinks::challenge($challengeId))
                ->with('error', __('pyramid::check.challenge_closed'));
        }

        return [$ladder, $challenge, $viewer];
    }

    /**
     * @return array{name: string, phone: ?string, tel: ?string, whatsapp: ?string}
     */
    private function contact(PyramidPlayer $player): array
    {
        $phone = filled($player->phone) ? (string) $player->phone : null;
        $whatsapp = $phone === null ? '' : WhatsApp::url($phone);

        return [
            'name' => (string) $player->label,
            'phone' => $phone,
            'tel' => $phone === null ? null : preg_replace('/[^0-9+]/', '', $phone),
            'whatsapp' => $whatsapp === '' ? null : $whatsapp,
        ];
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
            PyramidNotFound::abort(__('pyramid::ui.player.missing'));
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
