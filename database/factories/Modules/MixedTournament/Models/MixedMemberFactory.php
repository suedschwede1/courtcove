<?php

declare(strict_types=1);

namespace Database\Factories\Modules\MixedTournament\Models;

use App\Modules\MixedTournament\Models\MixedMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Datetimes are plain "Y-m-d H:i:s" strings, exactly as the legacy app stores them.
 */
class MixedMemberFactory extends Factory
{
    protected $model = MixedMember::class;

    public function definition(): array
    {
        static $counter = 0;
        $n = ++$counter;

        return [
            'label' => 'Spieler '.str_pad((string) $n, 2, '0', STR_PAD_LEFT),
            'gender' => null,
            'itn' => null,
            'birth_date' => null,
        ];
    }
}
