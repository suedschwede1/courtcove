<?php

declare(strict_types=1);

namespace Database\Factories\Modules\MixedTournament\Models;

use App\Modules\MixedTournament\Models\MixedParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Datetimes are plain "Y-m-d H:i:s" strings, exactly as the legacy app stores them.
 */
class MixedParticipantFactory extends Factory
{
    protected $model = MixedParticipant::class;

    public function definition(): array
    {
        static $counter = 0;
        $n = ++$counter;

        return [
            'event_id' => 1,
            'uid' => null,
            'label' => 'Spieler '.str_pad((string) $n, 2, '0', STR_PAD_LEFT),
            'gender' => null,
            'itn' => null,
            'age' => null,
            'status' => 'active',
            'no_extra_match' => 0,
        ];
    }
}
