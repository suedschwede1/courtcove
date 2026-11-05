<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pyramid\Http\Controllers\Concerns\ResolvesPyramidViewer;
use App\Modules\Pyramid\Services\ChallengeService;
use App\Modules\Pyramid\Services\PyramidRepository;
use App\Modules\Pyramid\Services\RuleService;
use Illuminate\Contracts\View\View;
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
            'blocked' => $this->challenges->annotate($ladder, $viewer, $ranking),
            'openChallenges' => $this->repo->openChallenges((int) $ladder->id),
            'waiting' => $waiting,
            'feed' => $this->repo->feed((int) $ladder->id),
            'canAdmin' => $this->canAdmin($ladder),
        ]);
    }
}
