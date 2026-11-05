<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Tournament\Models;

use App\Modules\Tournament\Models\TournamentMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

class TournamentMatchFactory extends Factory
{
    protected $model = TournamentMatch::class;

    public function definition(): array
    {
        return [
            'tid' => 1,
            'phase' => 'group',
            'retired' => 'none',
        ];
    }

    public function group(string $grp, int $rnd, int $slot): static
    {
        return $this->state([
            'phase' => 'group',
            'mkey' => $grp.'-'.$slot,
            'grp' => $grp,
            'rnd' => $rnd,
            'slot' => $slot,
        ]);
    }

    /**
     * A finished match; `$sets` is a list of [games a, games b] per set (the third entry is the match tie-break,
     * counted as a set and as one game for its winner).
     *
     * @param  list<array{int, int}>  $sets
     */
    public function played(int $winnerTpid, array $sets): static
    {
        $setsA = 0;
        $setsB = 0;
        $gamesA = 0;
        $gamesB = 0;
        foreach ($sets as $i => [$a, $b]) {
            if ($i < 2) {
                $gamesA += $a;
                $gamesB += $b;
            } elseif ($a > $b) {
                $gamesA++; // as ZF2's ScoreService: the match tie-break counts as one game for its winner
            } elseif ($b > $a) {
                $gamesB++;
            }
            if ($a > $b) {
                $setsA++;
            } elseif ($b > $a) {
                $setsB++;
            }
        }

        $state = [
            'played' => '2027-05-01',
            'score' => implode(' ', array_map(fn (array $s): string => $s[0].':'.$s[1], $sets)),
            'sets_a' => $setsA,
            'sets_b' => $setsB,
            'games_a' => $gamesA,
            'games_b' => $gamesB,
            'winner' => $winnerTpid,
        ];
        foreach (['1', '2'] as $i => $n) {
            if (isset($sets[$i])) {
                $state['s'.$n.'a'] = $sets[$i][0];
                $state['s'.$n.'b'] = $sets[$i][1];
            }
        }
        if (isset($sets[2])) {
            $state['mtba'] = $sets[2][0];
            $state['mtbb'] = $sets[2][1];
        }

        return $this->state($state);
    }

    public function confirmed(int $uid): static
    {
        return $this->state(['confirmed_uid' => $uid, 'confirmed' => '2027-05-02 12:00:00']);
    }
}
