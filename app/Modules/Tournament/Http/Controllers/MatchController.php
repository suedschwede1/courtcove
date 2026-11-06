<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tournament\Http\Requests\SaveMatchRequest;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\Tournament\Services\MatchResultService;
use App\Modules\Tournament\Services\NoCurrentTournament;
use App\Modules\Tournament\Support\AccessContext;
use App\Modules\Tournament\Support\AdminInput;
use App\Modules\Tournament\Support\MatchPresenter;
use App\Modules\Tournament\Support\MatchWriteRule;
use App\Modules\Tournament\Support\TournamentAccess;
use App\Modules\Tournament\Support\TournamentPage;
use App\Modules\TournamentBase\Services\InvalidScoreException;
use App\Modules\TournamentBase\Services\ScoreService;
use App\Modules\TournamentBase\Support\ActionRefused;
use App\Modules\TournamentBase\Support\CourtNames;
use App\Modules\TournamentBase\Support\ModuleFlash;
use App\Modules\TournamentBase\Support\PlayerName;
use App\Modules\TournamentBase\Support\WhatsApp;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Date and result of a match (port of ZF2's MatchController): the entry page and the save, confirm and
 * delete actions.
 *
 * Every id is resolved within the tournament of the request (TournamentAccess::resolveTournament: the
 * admin's selection or the current one), so a match of another tournament answers 404 on every route. The
 * login comes first (route middleware), then the match, then the write permission (MatchWriteRule, the
 * same rule as the match cards), then the lock on a confirmed result, and only then the posted input.
 */
class MatchController extends Controller
{
    private const DESCRIBE_KEYS = ['schedule_only', 'enter_sets', 'set1_invalid', 'set2_invalid', 'mtb_must_be_empty', 'mtb_needed', 'mtb_invalid'];

    public function __construct(
        private readonly TournamentAccess $access,
        private readonly MatchResultService $results,
    ) {}

    public function show(Request $request, TournamentPage $pages, MatchPresenter $presenter, ScoreService $scores, string $tmid): View
    {
        $user = $this->user($request);
        $page = $pages->build($user, 'schedule');
        abort_if($page === null, 404);

        $t = $page['tournament'];
        $access = $page['access'];
        $match = $this->match($t, $tmid)->getAttributes();
        $players = $page['players'];

        $a = $match['tpid_a'] !== null ? ($players[(int) $match['tpid_a']] ?? null) : null;
        $b = $match['tpid_b'] !== null ? ($players[(int) $match['tpid_b']] ?? null) : null;
        $labelA = $a !== null ? (string) $a['label'] : __('tournament::match.player_a');
        $labelB = $b !== null ? (string) $b['label'] : __('tournament::match.player_b');

        $isAdmin = $access->isAdmin();
        $isOwn = MatchWriteRule::isOwn($match, $access);
        $canWrite = MatchWriteRule::canWrite($match, $access, $t);
        $currentUid = (int) $user->uid; // the real uid, also in the test mode (ZF2)

        $input = ['wo' => match ($match['retired']) {
            'wo_a' => 'a',
            'wo_b' => 'b',
            default => '',
        }];
        foreach (SaveMatchRequest::SCORE_FIELDS as $field) {
            $input[$field] = $match[$field] !== null ? (string) (int) $match[$field] : '';
        }

        $winner = $match['winner'] !== null ? (int) $match['winner'] : null;

        // The opponent from the viewer's side, for the WhatsApp link (participants only, as in ZF2).
        $opponent = null;
        if ($isOwn) {
            $opponent = (int) $match['tpid_a'] === $access->tpid ? $b : $a;
        }
        $whatsapp = null;
        if ($page['showContacts'] && $opponent !== null && WhatsApp::url($opponent['phone'] ?? null) !== '') {
            $phaseLabel = null;
            if ($match['grp'] === null) {
                try {
                    $phaseLabel = $page['format']->phase((string) $match['phase'])['label'] ?? null;
                } catch (Throwable) {
                    // unknown phase: the key is shown, as on the other pages
                }
            }
            $whatsapp = [
                'url' => WhatsApp::url((string) $opponent['phone'], $this->whatsappText($match, $phaseLabel, $t, $labelA, $labelB, (string) $opponent['label'])),
                'label' => __($winner ? 'tournament::match.whatsapp_result' : 'tournament::match.whatsapp_date'),
            ];
        }

        // Court tiles: the configured alias as in the old form, otherwise the plain "Platz n".
        $courts = [];
        foreach (['1', '2', '3'] as $court) {
            $alias = CourtNames::short($court);
            $courts[$court] = $alias !== $court ? $alias : CourtNames::label($court);
        }

        return view('tournament::match', $page + [
            'active' => 'schedule',
            'match' => $match,
            'card' => $presenter->present($match, $players, $access, $t),
            'labelA' => $labelA,
            'labelB' => $labelB,
            'canWrite' => $canWrite,
            'isOwn' => $isOwn,
            'isTest' => ! empty($t->is_test),
            'simulation' => $access->simulation,
            'input' => $input,
            'courts' => $courts,
            'whatsapp' => $whatsapp,
            'validator' => $this->validator($scores, $input, (bool) ($match['played'] && $match['played_time']), $labelA, $labelB),
            'mayConfirm' => $canWrite && $winner && ! $match['confirmed_uid'] && ($isAdmin || (int) $match['entered_uid'] !== $currentUid),
            'mayDelete' => $canWrite && $winner && ($isAdmin || ! $match['confirmed_uid']),
            'mayEdit' => $canWrite && ($isAdmin || ! $match['confirmed_uid']),
        ]);
    }

    public function save(SaveMatchRequest $request, string $tmid): RedirectResponse
    {
        return $this->act($request, $tmid, function (TournamentMatch $match, User $user, AccessContext $access) use ($request): void {
            $key = $this->results->save($match, $request->matchInput(), $user, $access->isAdmin());
            ModuleFlash::success(__($key, ['score' => (string) $match->score]));
        });
    }

    public function confirm(Request $request, string $tmid): RedirectResponse
    {
        return $this->act($request, $tmid, function (TournamentMatch $match, User $user, AccessContext $access): void {
            $this->results->confirm($match, $user, $access->isAdmin());
            ModuleFlash::success(__('tournament::match.flash.confirmed'));
        });
    }

    public function delete(Request $request, string $tmid): RedirectResponse
    {
        return $this->act($request, $tmid, function (TournamentMatch $match, User $user, AccessContext $access): void {
            $this->results->delete($match, $access->isAdmin());
            ModuleFlash::success(__('tournament::match.flash.deleted'));
        });
    }

    /** A GET on a POST-only path goes to the match page (ZF2), after resolving the match like every route. */
    public function redirectToMatch(Request $request, string $tmid): RedirectResponse
    {
        $match = $this->match($this->tournament($this->user($request)), $tmid);

        return redirect()->route('tournament.match', ['tmid' => (int) $match->tmid]);
    }

    /**
     * Resolves the match within the request's tournament (404 otherwise), checks the write permission and
     * runs the action; refusals are flashed. Always back to the match page.
     *
     * @param  callable(TournamentMatch, User, AccessContext): void  $action
     */
    private function act(Request $request, string $tmid, callable $action): RedirectResponse
    {
        $user = $this->user($request);
        $t = $this->tournament($user);
        $match = $this->match($t, $tmid);
        $access = $this->access->context($user, $t);
        $back = redirect()->route('tournament.match', ['tmid' => (int) $match->tmid]);

        if (! MatchWriteRule::canWrite($match->getAttributes(), $access, $t)) {
            ModuleFlash::error(__(MatchWriteRule::deniedKey($match->getAttributes(), $access)));

            return $back;
        }

        try {
            $action($match, $user, $access);
        } catch (ActionRefused|InvalidScoreException $e) {
            ModuleFlash::error(__($e->key, $e->replace));
        }

        return $back;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function tournament(User $user): Tournament
    {
        try {
            return $this->access->resolveTournament($user);
        } catch (NoCurrentTournament) {
            abort(404);
        }
    }

    /**
     * The route only checks for digits: an oversized, zero or overflowing id is a 404 here, after the login and
     * the tournament are resolved (never a TypeError).
     */
    private function match(Tournament $t, string $tmid): TournamentMatch
    {
        $id = AdminInput::digits($tmid);
        abort_if($id === 0, 404);

        return TournamentMatch::query()->where('tid', $t->tid)->where('tmid', $id)->firstOrFail();
    }

    /**
     * Texts and initial state of the live validator. The texts are the server's ScoreService::describe()
     * messages, so the browser never words a rule differently from the server.
     *
     * @param  array<string, string>  $input
     * @return array{messages: array<string, string>, valid: array{a: string, b: string}, walkover: array{a: string, b: string}, state: string, text: string, input: array<string, string>}
     */
    private function validator(ScoreService $scores, array $input, bool $hasSchedule, string $labelA, string $labelB): array
    {
        $messages = [];
        foreach (self::DESCRIBE_KEYS as $key) {
            $messages[$key] = __('tournament-base::score.describe.'.$key);
        }

        $valid = [
            'a' => __('tournament::match.validator.valid', ['name' => $labelA]),
            'b' => __('tournament::match.validator.valid', ['name' => $labelB]),
        ];
        // keyed by the side that did NOT play: the opponent wins
        $walkover = [
            'a' => __('tournament::match.validator.walkover', ['name' => $labelB]),
            'b' => __('tournament::match.validator.walkover', ['name' => $labelA]),
        ];

        if ($input['wo'] !== '') {
            $state = 'success';
            $text = $walkover[$input['wo']];
        } else {
            $described = $scores->describe($input, $hasSchedule);
            $state = (string) $described['state'];
            $text = $described['message'] !== null
                ? __((string) $described['message'], $described['replace'] ?? [])
                : $valid[($described['winner'] ?? 'a') === 'b' ? 'b' : 'a'];
        }

        return ['messages' => $messages, 'valid' => $valid, 'walkover' => $walkover, 'state' => $state, 'text' => $text, 'input' => $input];
    }

    /**
     * Prefilled WhatsApp message (ZF2 match view): the result once played, else the date or a date request.
     *
     * @param  array<string, mixed>  $match
     */
    private function whatsappText(array $match, mixed $phaseLabel, Tournament $t, string $labelA, string $labelB, string $opponent): string
    {
        $kind = $match['grp'] !== null ? 'group' : 'ko';
        $replace = [
            'title' => (string) $t->title,
            'group' => (string) $match['grp'],
            'phase' => (string) ($phaseLabel ?? $match['phase']),
            'a' => $labelA,
            'b' => $labelB,
            'score' => (string) $match['score'],
            'name' => PlayerName::first($opponent),
            'date' => $match['played'] ? date('d.m.Y', (int) strtotime((string) $match['played'])) : '',
        ];

        $state = $match['winner'] ? 'result' : ($match['played'] ? 'scheduled' : 'open');

        return __('tournament::match.whatsapp_text.'.$state.'_'.$kind, $replace);
    }
}
