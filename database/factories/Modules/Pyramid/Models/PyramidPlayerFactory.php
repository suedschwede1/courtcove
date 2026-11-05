<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Pyramid\Models;

use App\Models\User;
use App\Modules\Pyramid\Models\PyramidPlayer;
use Illuminate\Database\Eloquent\Factories\Factory;

class PyramidPlayerFactory extends Factory
{
    protected $model = PyramidPlayer::class;

    public function definition(): array
    {
        return [
            'ladder_id' => 1,
            'uid' => null,
            'label' => 'Spieler',
            'itn' => null,
            'itn_rated' => '10.0',
            'position' => null,
            'status' => 'active',
            'joined' => now()->format('Y-m-d H:i:s'),
        ];
    }

    public function waiting(): static
    {
        return $this->state(['status' => 'waiting']);
    }

    public function entering(): static
    {
        return $this->state(['status' => 'entering']);
    }

    public function paused(): static
    {
        return $this->state(['status' => 'paused']);
    }

    public function forUser(User $user): static
    {
        return $this->state(['uid' => $user->uid, 'label' => $user->alias]);
    }
}
