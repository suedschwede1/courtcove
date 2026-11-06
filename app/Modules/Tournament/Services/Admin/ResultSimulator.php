<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services\Admin;

use App\Models\User;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\Tournament\Services\TournamentManager;
use App\Modules\TournamentBase\Services\InvalidScoreException;
use App\Modules\TournamentBase\Services\ScoreService;
use App\Modules\TournamentBase\Support\ActionRefused;
use Random\Randomizer;

/**
 * "Zufallsergebnisse eintragen" (port of the old AdminController::simulateResultsAction and randomScore).
 *
 * Test tournaments only: in a real tournament the players enter their results themselves, otherwise
 * entered_uid would name the tournament management. Fills every open group match whose two players are
 * known with a random, rule-conform result, entered AND confirmed by the admin (an unconfirmed group phase
 * would never count as complete). Matches with a winner are skipped. Every random number is drawn from the
 * given Randomizer, in ZF2's order (score, then day, time and court). As in ZF2 each match is one plain
 * update without a transaction; a rejected score is collected as a problem, not fatal.
 */
final class ResultSimulator
{
    public function __construct(
        private readonly TournamentManager $m,
        private readonly ScoreService $scores,
    ) {}

    /**
     * @return array{filled:int, skipped:int, problems: list<string>}
     *
     * @throws ActionRefused for a real tournament
     */
    public function simulate(Tournament $t, Randomizer $r, User $by): array
    {
        if (empty($t->is_test)) {
            throw new ActionRefused('tournament::admin.simulate_results.real');
        }

        $tid = (int) $t->tid;
        $this->m->forget($tid);

        $filled = 0;
        $skipped = 0;
        $problems = [];

        foreach ($this->m->groupMatches($tid) as $match) {
            if ($match['winner'] || ! $match['tpid_a'] || ! $match['tpid_b']) {
                $skipped++;

                continue;
            }

            $input = $this->randomScore($r);

            // evaluate() throws on invalid values; without this catch a single broken random result would
            // stop the run halfway with part of the matches written.
            try {
                $result = $this->scores->evaluate($input);
            } catch (InvalidScoreException $e) {
                // randomScore() produced something against the rules: this has to show, not pass silently.
                $problems[] = (string) __('tournament::admin.simulate_results.problem', [
                    'mkey' => (string) $match['mkey'],
                    'message' => (string) __($e->key, $e->replace),
                    'input' => (string) json_encode($input),
                ]);

                continue;
            }

            if (empty($result['winner_side'])) {
                $problems[] = (string) __('tournament::admin.simulate_results.no_winner', ['mkey' => (string) $match['mkey']]);

                continue;
            }

            $winnerTpid = ($result['winner_side'] === 'a') ? (int) $match['tpid_a'] : (int) $match['tpid_b'];

            // A date in the past, so the match counts as played.
            $playedAt = time() - $r->getInt(1, 14) * 86400;
            $now = now()->format('Y-m-d H:i:s');

            TournamentMatch::query()->where('tid', $tid)->where('tmid', (int) $match['tmid'])->update([
                'played' => date('Y-m-d', $playedAt),
                'played_time' => sprintf('%02u:%02u:00', $r->getInt(9, 20), $r->getInt(0, 1) * 30),
                'court' => (string) $r->getInt(1, 3),
                's1a' => $result['s1a'], 's1b' => $result['s1b'],
                's2a' => $result['s2a'], 's2b' => $result['s2b'],
                'mtba' => $result['mtba'], 'mtbb' => $result['mtbb'],
                'sets_a' => $result['sets_a'], 'sets_b' => $result['sets_b'],
                'games_a' => $result['games_a'], 'games_b' => $result['games_b'],
                'score' => $result['score'],
                'winner' => $winnerTpid,
                'entered_uid' => (int) $by->uid,
                'entered' => $now,
                'confirmed_uid' => (int) $by->uid,
                'confirmed' => $now,
            ]);

            $filled++;
        }

        $this->m->forget($tid);

        return ['filled' => $filled, 'skipped' => $skipped, 'problems' => $problems];
    }

    /**
     * A plausible best-of-three result with a match tie-break. Deliberately mixed: clean two-set wins, close
     * tie-break sets and match tie-breaks, so the cross-group comparisons get tested too.
     *
     * @return array{s1a:int, s1b:int, s2a:int, s2b:int, mtba:int|null, mtbb:int|null}
     */
    private function randomScore(Randomizer $r): array
    {
        $set = function (bool $winnerFirst) use ($r): array {
            // 7:5, 7:6 and 6:x - the three realistic outcomes
            $variants = [[6, 0], [6, 1], [6, 2], [6, 3], [6, 4], [7, 5], [7, 6]];

            $pick = $variants[$r->getInt(0, count($variants) - 1)];

            return $winnerFirst ? $pick : [$pick[1], $pick[0]];
        };

        $aWins = (bool) $r->getInt(0, 1);

        // A third of the matches goes over the match tie-break.
        if ($r->getInt(1, 3) === 1) {
            $first = $set($aWins);
            $second = $set(! $aWins);

            // Match tie-break by the rules: 10 against at most 8, or beyond 10 with EXACTLY two points apart.
            if ($r->getInt(0, 1)) {
                $mtbWinner = 10;
                $mtbLoser = $r->getInt(0, 8);
            } else {
                $mtbLoser = $r->getInt(9, 12);
                $mtbWinner = $mtbLoser + 2;
            }

            return [
                's1a' => $first[0], 's1b' => $first[1],
                's2a' => $second[0], 's2b' => $second[1],
                'mtba' => $aWins ? $mtbWinner : $mtbLoser,
                'mtbb' => $aWins ? $mtbLoser : $mtbWinner,
            ];
        }

        $first = $set($aWins);
        $second = $set($aWins);

        return [
            's1a' => $first[0], 's1b' => $first[1],
            's2a' => $second[0], 's2b' => $second[1],
            'mtba' => null, 'mtbb' => null,
        ];
    }
}
