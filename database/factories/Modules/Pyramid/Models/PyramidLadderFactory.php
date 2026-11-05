<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Pyramid\Models;

use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Datetimes are plain "Y-m-d H:i:s" strings, exactly as the legacy app stores them.
 */
class PyramidLadderFactory extends Factory
{
    protected $model = PyramidLadder::class;

    public function definition(): array
    {
        return [
            'title' => 'Testpyramide',
            'season' => 2027,
            'status' => 'running',
            'itn_default' => '10.0',
            'days_to_play' => 7,
            'winner_protected_days' => 3,
            'loser_cooldown_days' => 7,
            'is_default' => 0,
            'created' => now()->format('Y-m-d H:i:s'),
        ];
    }

    public function setup(): static
    {
        return $this->state(['status' => 'setup']);
    }

    public function closed(): static
    {
        return $this->state(['status' => 'closed']);
    }

    public function asDefault(): static
    {
        return $this->state(['is_default' => 1]);
    }

    /**
     * Adds $count active players "Spieler 1".."Spieler n" on positions 1..n.
     */
    public function withRanking(int $count): static
    {
        return $this->afterCreating(function (PyramidLadder $ladder) use ($count): void {
            for ($i = 1; $i <= $count; $i++) {
                PyramidPlayer::factory()->create([
                    'ladder_id' => $ladder->id,
                    'label' => 'Spieler '.$i,
                    'position' => $i,
                    'status' => 'active',
                    'uid' => null,
                    'joined' => now()->format('Y-m-d H:i:s'),
                ]);
            }
        });
    }
}
