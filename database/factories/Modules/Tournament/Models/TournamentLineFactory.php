<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Tournament\Models;

use App\Modules\Tournament\Models\TournamentLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class TournamentLineFactory extends Factory
{
    protected $model = TournamentLine::class;

    private static int $counter = 0;

    public function definition(): array
    {
        return [
            'tid' => 1,
            'phase' => 'KO_A',
            'line' => ++self::$counter,
            'kind' => 'pool',
        ];
    }
}
