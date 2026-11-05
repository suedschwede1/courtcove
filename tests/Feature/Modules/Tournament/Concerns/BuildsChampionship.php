<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament\Concerns;

use App\Models\User;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\Tournament\Services\GenerationService;

/**
 * A small generated championship for the page tests: format small-two-groups (groups A and B with three
 * players each, then a 4-line KO), six fictional players "Demo Spieler 01..06" with seed = number.
 * Group A: seeds 1, 4, 5; group B: seeds 2, 3, 6.
 */
trait BuildsChampionship
{
    protected Tournament $tournament;

    /** @var array<int, int> seed => tpid */
    protected array $tpid = [];

    /**
     * @param  array<int, User>  $users  seed => user who plays that seed
     * @param  array<string, mixed>  $attributes
     */
    protected function championship(array $users = [], array $attributes = []): Tournament
    {
        $format = json_decode((string) file_get_contents(base_path('tests/Fixtures/tournament/formats/small-two-groups.json')), true);
        $this->tournament = Tournament::factory()->active()->withFormat($format)->create($attributes);
        $this->tpid = [];

        $layout = [1 => ['A', 1], 2 => ['B', 1], 3 => ['B', 2], 4 => ['A', 2], 5 => ['A', 3], 6 => ['B', 3]];
        foreach ($layout as $seed => [$group, $pos]) {
            $player = TournamentPlayer::factory()->create([
                'tid' => $this->tournament->tid,
                'uid' => isset($users[$seed]) ? $users[$seed]->uid : null,
                'label' => sprintf('Demo Spieler %02d', $seed),
                'seed' => $seed,
                'grp' => $group,
                'grp_pos' => $pos,
                'itn_rated' => 4.0 + $seed / 10,
            ]);
            $this->tpid[$seed] = (int) $player->tpid;
        }

        $this->app->make(GenerationService::class)->generate((int) $this->tournament->tid);
        $this->app->forgetScopedInstances();

        return $this->tournament;
    }

    protected function matchRow(string $mkey, ?Tournament $t = null): TournamentMatch
    {
        return TournamentMatch::query()->where('tid', ($t ?? $this->tournament)->tid)->where('mkey', $mkey)->firstOrFail();
    }

    /** Scores a match 6:4 6:3 for side a (or b). */
    protected function play(string $mkey, string $winnerSide = 'a', array $extra = []): TournamentMatch
    {
        $m = $this->matchRow($mkey);
        $a = $winnerSide === 'a';
        $m->update($extra + [
            'played' => '2027-05-03', 'played_time' => '17:00:00',
            'score' => $a ? '6:4 6:3' : '4:6 3:6',
            's1a' => $a ? 6 : 4, 's1b' => $a ? 4 : 6, 's2a' => $a ? 6 : 3, 's2b' => $a ? 3 : 6,
            'sets_a' => $a ? 2 : 0, 'sets_b' => $a ? 0 : 2, 'games_a' => $a ? 12 : 7, 'games_b' => $a ? 7 : 12,
            'winner' => $a ? $m->tpid_a : $m->tpid_b,
        ]);
        $this->app->forgetScopedInstances();

        return $m;
    }

    protected function schedule(string $mkey, string $date = '2027-05-10', string $time = '18:00:00', ?string $court = null): TournamentMatch
    {
        $m = $this->matchRow($mkey);
        $m->update(['played' => $date, 'played_time' => $time, 'court' => $court]);
        $this->app->forgetScopedInstances();

        return $m;
    }

    protected function member(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['status' => 'enabled']);
    }

    protected function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }
}
