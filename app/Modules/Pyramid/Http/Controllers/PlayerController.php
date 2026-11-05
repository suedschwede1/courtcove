<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pyramid\Http\Controllers\Concerns\ResolvesPyramidViewer;
use App\Modules\Pyramid\Services\ChallengeService;
use App\Modules\Pyramid\Services\PyramidRepository;
use Illuminate\Contracts\View\View;

/**
 * Public player profile: standing, record, match history and the one way into a challenge.
 */
class PlayerController extends Controller
{
    use ResolvesPyramidViewer;

    public function __construct(
        private readonly PyramidRepository $repo,
        private readonly ChallengeService $challenges,
    ) {}

    public function show(int $playerId): View
    {
        $player = $this->repo->player($playerId);

        if ($player === null) {
            abort(404, __('pyramid::ui.player.missing'));
        }

        $ladder = $this->resolveLadder((int) $player->ladder_id);
        $viewer = $this->viewerFor($ladder);

        // false = no button at all (guest, not a participant, or looking at oneself);
        // null = challenging is possible; Verdict = the reason why not (disabled button).
        $verdict = false;

        if ($viewer !== null && (int) $viewer->id !== (int) $player->id) {
            $verdict = $this->challenges->check($ladder, $viewer, $player);
        }

        $labels = $this->repo->labels((int) $ladder->id);
        $record = $this->repo->matchRecord((int) $player->id);
        $history = [];

        foreach ($this->repo->matchHistory((int) $player->id, 50) as $match) {
            $isWinner = (int) $match->winner_id === (int) $player->id;

            $opponentId = (int) $match->challenger_id === (int) $player->id ? (int) $match->opponent_id : (int) $match->challenger_id;
            // strtotime() instead of a parse that throws: a broken stored date renders as a dash.
            $resolvedAt = strtotime((string) $match->resolved);

            $history[] = [
                'date' => $resolvedAt ? date('d.m.Y', $resolvedAt) : '–',
                'opponent' => $labels[$opponentId] ?? __('pyramid::ui.unknown'),
                'score' => $match->score ?: __('pyramid::ui.feed.forfeit_score'),
                'won' => $isWinner,
            ];
        }

        return view('pyramid::player', [
            'ladder' => $ladder,
            'ladders' => $this->repo->ladders(),
            'player' => $player,
            'viewer' => $viewer,
            'challengeVerdict' => $verdict,
            'history' => $history,
            'won' => $record['won'],
            'lost' => $record['lost'],
            // Phone numbers belong to the administration only; this page has no challenge context.
            'canAdmin' => $this->canAdmin($ladder),
        ]);
    }
}
