<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Tournament\Models;

use App\Models\User;
use App\Modules\Tournament\Models\TournamentPlayer;
use Illuminate\Database\Eloquent\Factories\Factory;

class TournamentPlayerFactory extends Factory
{
    protected $model = TournamentPlayer::class;

    private static int $counter = 0;

    public function definition(): array
    {
        return [
            'tid' => 1,
            'uid' => null,
            'label' => sprintf('Demo Spieler %02d', ++self::$counter % 100),
            'itn_rated' => 10.0,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(['uid' => $user->uid]);
    }

    public function seeded(int $seed): static
    {
        return $this->state(['seed' => $seed]);
    }

    public function inGroup(string $grp, int $pos): static
    {
        return $this->state(['grp' => $grp, 'grp_pos' => $pos]);
    }
}
