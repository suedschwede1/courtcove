<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pyramid\Http\Controllers\Concerns\ResolvesPyramidViewer;
use App\Modules\Pyramid\Services\ChallengeService;
use App\Modules\Pyramid\Services\PyramidRepository;
use App\Modules\Pyramid\Services\RuleService;
use App\Modules\Pyramid\Support\PyramidLinks;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

/**
 * Public area of the ladder: readable without login, writing actions check the viewer themselves.
 */
class LadderController extends Controller
{
    use ResolvesPyramidViewer;

    public function __construct(
        private readonly PyramidRepository $repo,
        private readonly RuleService $rules,
        private readonly ChallengeService $challenges,
    ) {}

    public function index(?int $ladderId = null): View
    {
        $ladder = $this->resolveLadder($ladderId);
        $ranking = $this->repo->ranking((int) $ladder->id);
        $waiting = $this->repo->waitingList((int) $ladder->id);
        $viewer = $this->viewerFor($ladder);

        return view('pyramid::ladder', [
            'ladder' => $ladder,
            'ladders' => $this->repo->ladders(),
            'rows' => $this->rules->pyramidRows($ranking),
            'ranking' => $ranking,
            'labels' => $ranking->concat($waiting)->mapWithKeys(
                static fn ($player): array => [(int) $player->id => (string) $player->label]
            )->all(),
            'viewer' => $viewer,
            'isLoggedIn' => Auth::check(),
            'blocked' => $this->challenges->annotate($ladder, $viewer, $ranking, null, Auth::check()),
            'openChallenges' => $this->repo->openChallenges((int) $ladder->id),
            'waiting' => $waiting,
            'feed' => $this->repo->feed((int) $ladder->id),
            'canAdmin' => $this->canAdmin($ladder),
        ]);
    }

    /**
     * The rules of the default ladder; the day counts are the ladder's own settings.
     */
    public function rules(): View
    {
        $ladder = $this->resolveLadder();

        return view('pyramid::rules', [
            'ladder' => $ladder,
            'ladders' => $this->repo->ladders(),
            'canAdmin' => $this->canAdmin($ladder),
        ]);
    }

    /**
     * All challenges of the default ladder, filtered by participant name and status in PHP like ZF2.
     */
    public function challenges(Request $request): View
    {
        $ladder = $this->resolveLadder();
        $labels = $this->repo->labels((int) $ladder->id);
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $rows = $this->repo->allChallenges((int) $ladder->id)
            ->filter(function ($challenge) use ($labels, $search, $status): bool {
                if ($status !== '' && $challenge->status !== $status) {
                    return false;
                }

                if ($search === '') {
                    return true;
                }

                return mb_stripos($labels[(int) $challenge->challenger_id] ?? '', $search) !== false
                    || mb_stripos($labels[(int) $challenge->opponent_id] ?? '', $search) !== false;
            })
            ->values();

        $perPage = 100;
        $page = max(1, LengthAwarePaginator::resolveCurrentPage());
        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => PyramidLinks::challenges()],
        );
        $paginator->appends(array_filter(['q' => $search, 'status' => $status], static fn (string $value): bool => $value !== ''));

        return view('pyramid::challenges', [
            'ladder' => $ladder,
            'ladders' => $this->repo->ladders(),
            'canAdmin' => $this->canAdmin($ladder),
            'challenges' => $paginator,
            'labels' => $labels,
            'search' => $search,
            'status' => $status,
        ]);
    }
}
