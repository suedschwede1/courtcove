<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Draw\Concerns;

use App\Models\User;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawEligible;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentPlayer;

/**
 * A championship (format small-two-groups: groups A and B of three, one head each) before the group draw:
 * seeds 1 and 2 are the heads A1 and B1, seeds 3 to 6 are in the pot and each has a fictional user.
 * The token draw has four tokens AC-01..AC-04 for the slots A2, B2, A3, B3, is executed (slots set,
 * hidden) and in the picking phase, with the seeds 3 to 6 eligible.
 */
trait BuildsTokenDraw
{
    protected Tournament $tournament;

    protected Draw $draw;

    /** @var array<int, int> seed => tpid */
    protected array $tpid = [];

    /** @var array<int, User> seed => user (seeds 1 to 6) */
    protected array $users = [];

    /** @param  array<string, mixed>  $tournamentAttributes */
    protected function tokenDraw(array $tournamentAttributes = [], bool $withTid = true): Draw
    {
        $format = json_decode((string) file_get_contents(base_path('tests/Fixtures/tournament/formats/small-two-groups.json')), true);
        $this->tournament = Tournament::factory()->active()->withFormat($format)->create($tournamentAttributes);
        $this->tpid = [];
        $this->users = [];

        for ($seed = 1; $seed <= 6; $seed++) {
            $this->users[$seed] = User::factory()->create(['status' => 'enabled', 'email' => sprintf('spieler%02d@example.test', $seed)]);
            $player = TournamentPlayer::factory()->create([
                'tid' => $this->tournament->tid,
                'uid' => $this->users[$seed]->uid,
                'label' => sprintf('Demo Spieler %02d', $seed),
                'seed' => $seed,
                'grp' => $seed <= 2 ? ($seed === 1 ? 'A' : 'B') : null,
                'grp_pos' => $seed <= 2 ? 1 : null,
            ]);
            $this->tpid[$seed] = (int) $player->tpid;
        }

        $this->draw = Draw::factory()
            ->token(4, 'AC', ['A2', 'B2', 'A3', 'B3'])
            ->executed()
            ->create(['tid' => $withTid ? $this->tournament->tid : null, 'title' => 'Demo-Meisterschaft - Gruppenauslosung']);

        for ($seed = 3; $seed <= 6; $seed++) {
            DrawEligible::query()->create(['did' => $this->draw->did, 'tpid' => $this->tpid[$seed]]);
        }

        return $this->draw;
    }

    protected function tokenWithSlot(string $slot, ?Draw $draw = null): DrawToken
    {
        return DrawToken::query()->where('did', ($draw ?? $this->draw)->did)->where('slot', $slot)->firstOrFail();
    }

    protected function partners(int $seedA, int $seedB): void
    {
        TournamentPlayer::query()->whereKey($this->tpid[$seedA])->update(['partner_tpid' => $this->tpid[$seedB]]);
        TournamentPlayer::query()->whereKey($this->tpid[$seedB])->update(['partner_tpid' => $this->tpid[$seedA]]);
        $this->app->forgetScopedInstances();
    }

    /** Claims a token directly (as a pick), bypassing the services. */
    protected function claimDirectly(DrawToken $token, int $seed): void
    {
        DrawToken::query()->whereKey($token->dtid)->update([
            'claimed_tpid' => $this->tpid[$seed],
            'claimed_uid' => $this->users[$seed]->uid,
            'claimed' => now()->format('Y-m-d H:i:s'),
            'assigned_by' => 'pick',
        ]);
    }
}
