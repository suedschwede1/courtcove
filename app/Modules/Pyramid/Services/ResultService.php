<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Services;

use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use App\Modules\Pyramid\Support\EventText;
use App\Modules\Pyramid\Support\PyramidRuleException;
use App\Modules\Pyramid\Support\Verdict;
use App\Modules\TournamentBase\Services\InvalidScoreException;
use App\Modules\TournamentBase\Services\ScoreService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Records a challenge result and moves the ranking (port of the old ResultService).
 *
 * Two points are not negotiable:
 *
 * 1. Positions are computed from the CURRENT ranking, not from the snapshots stored on the challenge.
 *    Rule 3.8: results entered in the meantime do not touch the match, but it may be played for another place.
 * 2. The new assignment comes from RuleService, never from SQL, so the rule lives in one place.
 */
final class ResultService
{
    public function __construct(
        private readonly PyramidRepository $repo,
        private readonly RuleService $rules,
        private readonly ScoreService $scores,
    ) {}

    /**
     * @param  array<string, mixed>  $input  s1a, s1b, s2a, s2b and optional mtba, mtbb; side a is the challenger
     * @return array<string, mixed> the ScoreService result
     *
     * @throws InvalidScoreException before anything is locked or written
     * @throws PyramidRuleException
     */
    public function record(PyramidLadder $ladder, PyramidChallenge $challenge, array $input, ?int $actorUid): array
    {
        $evaluated = $this->evaluate($input);

        return DB::transaction(function () use ($ladder, $challenge, $evaluated, $actorUid): array {
            [$ladder, $challenge, $challenger, $opponent] = $this->lockAndFetch($ladder, $challenge);

            $challengerWon = $evaluated['winner_side'] === 'a';
            $winnerId = $challengerWon ? (int) $challenger->id : (int) $opponent->id;

            $this->applyPositions($ladder, $challenger, $opponent, $challengerWon);

            $now = CarbonImmutable::now()->format('Y-m-d H:i:s');

            PyramidChallenge::query()->whereKey($challenge->id)->update([
                'status' => 'played',
                'score' => $evaluated['score'],
                'winner_id' => $winnerId,
                'entered_uid' => $actorUid,
                'entered' => $now,
                'resolved' => $now,
            ]);

            $this->repo->addEvent((int) $ladder->id, 'result', [
                'winner' => $winnerId,
                'loser' => $challengerWon ? (int) $opponent->id : (int) $challenger->id,
                'score' => $evaluated['score'],
            ], (int) $challenge->id, $actorUid);

            return $evaluated;
        });
    }

    /**
     * The score check of record() without touching anything; the live check of the result form asks the same question.
     *
     * @param  array<string, mixed>  $input  s1a, s1b, s2a, s2b and optional mtba, mtbb; side a is the challenger
     * @return array<string, mixed> the ScoreService result (score, winner_side, ...)
     *
     * @throws InvalidScoreException
     */
    public function evaluate(array $input): array
    {
        return $this->scores->evaluate($input);
    }

    /**
     * Rule 5.1 - refusal without good reason or no-show.
     *
     * No score, but the same effect on the positions as a played match, hence the same branches as record().
     * The score stays NULL on purpose: an invented 6:0 6:0 would be a false claim in the history. winner_id is set,
     * otherwise the block windows could not tell the winner.
     *
     * @param  bool  $challengerWon  who showed up - the admin decides, the system cannot know
     * @param  string  $note  reason, mandatory in the UI
     *
     * @throws PyramidRuleException
     */
    public function recordForfeit(PyramidLadder $ladder, PyramidChallenge $challenge, bool $challengerWon, string $note, ?int $actorUid): void
    {
        DB::transaction(function () use ($ladder, $challenge, $challengerWon, $note, $actorUid): void {
            [$ladder, $challenge, $challenger, $opponent] = $this->lockAndFetch($ladder, $challenge);

            $winnerId = $challengerWon ? (int) $challenger->id : (int) $opponent->id;

            $this->applyPositions($ladder, $challenger, $opponent, $challengerWon);

            $now = CarbonImmutable::now()->format('Y-m-d H:i:s');

            PyramidChallenge::query()->whereKey($challenge->id)->update([
                'status' => 'forfeited',
                'winner_id' => $winnerId,
                'admin_note' => $note,
                'entered_uid' => $actorUid,
                'entered' => $now,
                'resolved' => $now,
            ]);

            $this->repo->addEvent((int) $ladder->id, 'result', [
                'winner' => $winnerId,
                'loser' => $challengerWon ? (int) $opponent->id : (int) $challenger->id,
                'score' => EventText::FORFEIT_SCORE,
            ], (int) $challenge->id, $actorUid);
        });
    }

    /**
     * Locks ladder row, all ladder players (ORDER BY id) and the challenge row, in that order, and returns the CURRENT
     * rows. Only after that may anything be checked or moved: without the re-check of the challenge status two
     * callers that both still hold an 'open' looking row would both get through and shift the ranking twice.
     *
     * @return array{0: PyramidLadder, 1: PyramidChallenge, 2: PyramidPlayer, 3: PyramidPlayer}
     *
     * @throws PyramidRuleException
     */
    private function lockAndFetch(PyramidLadder $ladder, PyramidChallenge $challenge): array
    {
        PyramidLadder::query()->whereKey($ladder->id)->lockForUpdate()->first();
        $fresh = $this->repo->ladder((int) $ladder->id);

        if (! $fresh || $fresh->status !== 'running') {
            throw new PyramidRuleException(new Verdict('pyramid::check.ladder_closed'));
        }

        PyramidPlayer::query()
            ->where('ladder_id', $fresh->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);

        PyramidChallenge::query()->whereKey($challenge->id)->lockForUpdate()->first();
        $current = $this->repo->challenge((int) $challenge->id);

        if (! $current || ! $current->isRunning()) {
            throw new PyramidRuleException(new Verdict('pyramid::check.challenge_closed'));
        }

        $challenger = $this->repo->player((int) $current->challenger_id);
        $opponent = $this->repo->player((int) $current->opponent_id);

        if (! $challenger || ! $opponent) {
            throw new PyramidRuleException(new Verdict('pyramid::check.challenge_closed'));
        }

        return [$fresh, $current, $challenger, $opponent];
    }

    /**
     * Rule 4.2 (challenger ranked), rule 6.1 (challenger entering). A lost challenge of a ranked player changes
     * nothing (rule 4.3), so there is no branch for it.
     */
    private function applyPositions(PyramidLadder $ladder, PyramidPlayer $challenger, PyramidPlayer $opponent, bool $challengerWon): void
    {
        if ($challenger->position === null) {
            $this->applyEntry($ladder, $challenger, $opponent, $challengerWon);
        } elseif ($challengerWon) {
            $current = $this->currentPositions((int) $ladder->id);

            $this->persistPositions(
                $current,
                $this->rules->shiftForWin($current, $challenger->position, $opponent->position)
            );
        }
    }

    private function applyEntry(PyramidLadder $ladder, PyramidPlayer $challenger, PyramidPlayer $opponent, bool $challengerWon): void
    {
        if (! $challengerWon) {
            $this->repo->appendToRanking((int) $challenger->id);

            return;
        }

        $target = (int) $opponent->position;
        $current = $this->currentPositions((int) $ladder->id);

        $this->persistPositions($current, $this->rules->shiftForEntry($current, $target));

        PyramidPlayer::query()->whereKey($challenger->id)->update(['position' => $target, 'status' => 'active']);
    }

    /**
     * @return array<int, int> playerId => position, active and paused players
     */
    private function currentPositions(int $ladderId): array
    {
        $positions = [];

        foreach ($this->repo->ranking($ladderId) as $player) {
            $positions[(int) $player->id] = (int) $player->position;
        }

        return $positions;
    }

    /**
     * Writes only the changed rows and clears them to NULL first.
     *
     * The detour is needed: UNIQUE (ladder_id, position) is checked row by row in MySQL, so a direct shift would
     * collide with the successor on the first UPDATE. NULL is allowed any number of times in a unique index and the
     * transaction hides the intermediate state.
     *
     * @param  array<int, int|string>  $before
     * @param  array<int, int|string>  $after
     */
    private function persistPositions(array $before, array $after): void
    {
        $changed = [];

        foreach ($after as $id => $position) {
            if (! isset($before[$id]) || (int) $before[$id] !== (int) $position) {
                $changed[(int) $id] = (int) $position;
            }
        }

        if ($changed === []) {
            return;
        }

        foreach (array_keys($changed) as $id) {
            PyramidPlayer::query()->whereKey($id)->update(['position' => null]);
        }

        foreach ($changed as $id => $position) {
            PyramidPlayer::query()->whereKey($id)->update(['position' => $position]);
        }
    }
}
