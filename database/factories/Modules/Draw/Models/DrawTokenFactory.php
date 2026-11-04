<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Draw\Models;

use App\Modules\Draw\Models\DrawToken;
use Illuminate\Database\Eloquent\Factories\Factory;

class DrawTokenFactory extends Factory
{
    protected $model = DrawToken::class;

    public function definition(): array
    {
        return [
            'did' => 1,
            'position' => 1,
            'token' => 'AC-01',
            'slot' => null,
            'claimed_tpid' => null,
            'claimed_uid' => null,
            'claimed' => null,
            'assigned_by' => null,
        ];
    }
}
