<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Pyramid\Http\Controllers\Concerns\ResolvesPyramidViewer;
use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Services\LadderAdminService;
use App\Modules\Pyramid\Services\PyramidRepository;
use App\Modules\Pyramid\Support\PyramidLinks;
use App\Modules\Pyramid\Support\PyramidRuleException;
use App\Modules\Pyramid\Support\Verdict;
use App\Modules\TournamentBase\Support\MemberSearch;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

/**
 * Administration overview: ladders by season, overdue challenges and the form for a new ladder.
 *
 * The routes sit behind the login middleware; the global right is checked here through the Gate
 * (a grant holder administers single ladders, but not this global page).
 */
class LadderAdminController extends Controller
{
    use ResolvesPyramidViewer;

    public function __construct(
        private readonly PyramidRepository $repo,
        private readonly LadderAdminService $admin,
        private readonly MemberSearch $memberSearch,
    ) {}

    public function index(): View
    {
        $this->requireGlobalAdmin();

        $bySeason = [];

        foreach ($this->repo->ladders() as $ladder) {
            $bySeason[(int) $ladder->season][] = [
                'ladder' => $ladder,
                'count' => $this->repo->countActive((int) $ladder->id),
            ];
        }

        return view('pyramid::admin.index', [
            'bySeason' => $bySeason,
            'overdue' => $this->repo->overdueChallenges(CarbonImmutable::now()->format('Y-m-d')),
            'year' => (int) CarbonImmutable::now()->format('Y'),
        ]);
    }

    /**
     * A GET (e.g. a reloaded form target) just returns to the overview, like the old action.
     */
    public function create(Request $request): RedirectResponse
    {
        $this->requireGlobalAdmin();

        $overview = redirect()->to(PyramidLinks::adminIndex());

        if (! $request->isMethod('POST')) {
            return $overview;
        }

        $year = (int) CarbonImmutable::now()->format('Y');
        $season = (int) $request->input('season', $year);

        try {
            $ladder = $this->admin->createLadder(
                (string) $request->input('title', ''),
                $season > 0 ? $season : $year,
                $request->input('category') === null ? null : (string) $request->input('category'),
            );
        } catch (PyramidRuleException $e) {
            return $overview->with('error', $e->verdict->message());
        }

        return redirect()->to(PyramidLinks::adminLadder((int) $ladder->id));
    }

    /**
     * The dashboard of one ladder. Search, status filter and page run through query parameters; the member
     * list for the "add participant" form is NOT part of the page, the picker asks for matches on demand.
     */
    public function show(Request $request, int $ladderId): View
    {
        $ladder = $this->administeredLadder($ladderId);

        $ranking = $this->repo->ranking($ladderId);
        $waiting = $this->repo->waitingList($ladderId);
        $open = $this->repo->openChallenges($ladderId);

        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $filtered = $ranking->filter(static fn ($player): bool => ($search === '' || mb_stripos((string) $player->label, $search) !== false)
            && ($status === '' || $player->status === $status))->values();

        $perPage = 10;
        $pages = max(1, (int) ceil($filtered->count() / $perPage));
        $page = min($pages, max(1, (int) $request->query('page', 1)));

        $players = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => PyramidLinks::adminLadder($ladderId)],
        );
        $players->appends(array_filter(['q' => $search, 'status' => $status], static fn (string $value): bool => $value !== ''));

        $ladders = $this->repo->ladders();

        return view('pyramid::admin.ladder', [
            'ladder' => $ladder,
            'ladders' => $ladders,
            'counts' => $ladders->mapWithKeys(fn (PyramidLadder $each): array => [(int) $each->id => $this->repo->countActive((int) $each->id)])->all(),
            'otherLadders' => $ladders->filter(static fn (PyramidLadder $other): bool => (int) $other->id !== $ladderId)->values(),
            'ranking' => $ranking,
            'players' => $players,
            'page' => $page,
            'pages' => $pages,
            'total' => $filtered->count(),
            'search' => $search,
            'status' => $status,
            'waiting' => $waiting,
            'openChallenges' => $open,
            'overdue' => $this->overdue($open),
            'resolved' => $this->repo->resolvedChallenges($ladderId, 10),
            'problems' => array_map(static fn (Verdict $problem): string => $problem->message(), $this->admin->diagnose($ladder)),
            'labels' => $this->repo->labels($ladderId),
            'suggestedDeadline' => CarbonImmutable::now()->addDays(7)->format('Y-m-d'),
            'globalAdmin' => $this->canAdmin(),
        ]);
    }

    /**
     * Member lookup for the picker. The result carries e-mail addresses, so it is only for administrators of
     * this ladder and never cached.
     */
    public function members(Request $request, int $ladderId): JsonResponse
    {
        $this->administeredLadder($ladderId);

        return response()
            ->json($this->memberSearch->search((string) $request->query('q', '')))
            ->header('Cache-Control', 'no-store, private');
    }

    public function seed(int $ladderId): RedirectResponse
    {
        $ladder = $this->administeredLadder($ladderId);
        $back = redirect()->to(PyramidLinks::adminLadder((int) $ladder->id));

        try {
            $count = $this->admin->seed($ladder, $this->actorUid());
        } catch (PyramidRuleException $e) {
            return $back->with('error', $e->verdict->message());
        }

        return $back->with('success', __('pyramid::admin.flash.seeded', ['count' => $count]));
    }

    public function addPlayer(Request $request, int $ladderId): RedirectResponse
    {
        $ladder = $this->administeredLadder($ladderId);
        $back = redirect()->to(PyramidLinks::adminLadder((int) $ladder->id));

        try {
            $player = $this->admin->addPlayer(
                $ladder,
                $request->only(['label', 'uid', 'phone', 'email', 'itn', 'gender', 'age']),
            );
        } catch (PyramidRuleException $e) {
            return $back->with('error', $e->verdict->message());
        }

        return $back->with('success', __('pyramid::admin.flash.player_added', ['label' => $player->label]));
    }

    public function copyPlayers(Request $request, int $ladderId): RedirectResponse
    {
        $ladder = $this->administeredLadder($ladderId);
        $back = redirect()->to(PyramidLinks::adminLadder((int) $ladder->id));

        $source = $this->repo->ladder((int) $request->input('sourceLadderId'));

        // Copying reads another roster (names, phones): only from a ladder the user administers too.
        // Anything else is reported like a missing source, which does not reveal that the ladder exists.
        if ($source !== null && ! $this->canAdmin($source)) {
            $source = null;
        }

        try {
            $count = $this->admin->copyPlayers($ladder, $source);
        } catch (PyramidRuleException $e) {
            return $back->with('error', $e->verdict->message());
        }

        return $back->with('success', __('pyramid::admin.flash.copied', ['count' => $count, 'title' => $source->title]));
    }

    public function normalize(int $ladderId): RedirectResponse
    {
        $ladder = $this->administeredLadder($ladderId);

        $this->admin->normalize($ladder, $this->actorUid());

        return redirect()->to(PyramidLinks::adminLadder((int) $ladder->id))
            ->with('success', __('pyramid::admin.flash.normalized'));
    }

    public function config(Request $request, int $ladderId): RedirectResponse
    {
        $ladder = $this->administeredLadder($ladderId);
        $back = redirect()->to(PyramidLinks::adminLadder((int) $ladder->id));

        try {
            $this->admin->configure($ladder, $request->only([
                'title', 'category', 'days_to_play', 'winner_protected_days', 'loser_cooldown_days', 'is_default',
            ]), $this->canAdmin());
        } catch (PyramidRuleException $e) {
            return $back->with('error', $e->verdict->message());
        }

        return $back->with('success', __('pyramid::admin.flash.config_saved'));
    }

    public function close(int $ladderId): RedirectResponse
    {
        $ladder = $this->administeredLadder($ladderId);

        $back = redirect()->to(PyramidLinks::adminLadder((int) $ladder->id));

        if (! $this->admin->close($ladder, $this->actorUid())) {
            return $back->with('info', __('pyramid::admin.flash.already_closed'));
        }

        return $back->with('success', __('pyramid::admin.flash.closed'));
    }

    /**
     * Club decision (ZF2 let every ladder admin do this): only global Pyramid admins grant and revoke ladder
     * rights. A grant holder administers the ladder but cannot hand the right on. The ladder comes from the URL;
     * the target user number is free input, like the old form.
     */
    public function grant(Request $request, int $ladderId): RedirectResponse
    {
        $this->requireGlobalAdmin();

        $ladder = $this->administeredLadder($ladderId);
        $back = redirect()->to(PyramidLinks::adminLadder((int) $ladder->id));
        $uid = (int) $request->input('uid', 0);

        if ($uid <= 0) {
            return $back->with('error', __('pyramid::admin.grant_uid_required'));
        }

        if (! User::query()->whereKey($uid)->exists()) {
            return $back->with('error', __('pyramid::admin.grant_uid_unknown'));
        }

        $this->repo->addGrant((int) $ladder->id, $uid);

        return $back->with('success', __('pyramid::admin.flash.granted'));
    }

    public function revoke(Request $request, int $ladderId): RedirectResponse
    {
        $this->requireGlobalAdmin();

        $ladder = $this->administeredLadder($ladderId);

        $this->repo->removeGrant((int) $ladder->id, (int) $request->input('uid', 0));

        return redirect()->to(PyramidLinks::adminLadder((int) $ladder->id))
            ->with('success', __('pyramid::admin.flash.revoked'));
    }

    private function actorUid(): int
    {
        return (int) Auth::user()->uid;
    }

    private function administeredLadder(int $ladderId): PyramidLadder
    {
        $ladder = $this->repo->ladder($ladderId);

        if ($ladder === null) {
            abort(404, __('pyramid::ui.no_ladder'));
        }

        // Through the Gate (policy): a grant holder reaches exactly the ladders granted to them.
        abort_unless($this->canAdmin($ladder), 403, __('pyramid::admin.forbidden'));

        return $ladder;
    }

    /**
     * Running challenges whose deadline day lies before today (ZF2: string comparison of the date part).
     *
     * @param  iterable<PyramidChallenge>  $challenges
     * @return list<PyramidChallenge>
     */
    private function overdue(iterable $challenges): array
    {
        $today = CarbonImmutable::now()->format('Y-m-d');
        $overdue = [];

        foreach ($challenges as $challenge) {
            if ($challenge->deadline && substr((string) $challenge->deadline, 0, 10) < $today) {
                $overdue[] = $challenge;
            }
        }

        return $overdue;
    }

    private function requireGlobalAdmin(): void
    {
        abort_unless($this->canAdmin(), 403, __('pyramid::admin.forbidden'));
    }
}
