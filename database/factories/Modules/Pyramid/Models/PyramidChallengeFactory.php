<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Pyramid\Models;

use App\Modules\Pyramid\Models\PyramidChallenge;
use Illuminate\Database\Eloquent\Factories\Factory;

class PyramidChallengeFactory extends Factory
{
    protected $model = PyramidChallenge::class;

    public function definition(): array
    {
        return [
            'ladder_id' => 1,
            'challenger_id' => 1,
            'opponent_id' => 2,
            'status' => 'open',
            'created' => now()->format('Y-m-d H:i:s'),
            'deadline' => now()->addDays(7)->format('Y-m-d'),
        ];
    }
}
