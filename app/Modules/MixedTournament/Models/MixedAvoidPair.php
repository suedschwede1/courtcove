<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An opponent block (two participants who must not meet) on the legacy bs_mixed_avoid_pairs table.
 *
 * Date, datetime and decimal columns are deliberately NOT cast: the old
 * application reads and writes plain strings, and a cast would shift or
 * reformat them.
 *
 * @property int $id
 * @property int $event_id
 * @property int $participant_a
 * @property int $participant_b
 */
class MixedAvoidPair extends Model
{
    protected $table = 'bs_mixed_avoid_pairs';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'event_id' => 'integer',
            'participant_a' => 'integer',
            'participant_b' => 'integer',
        ];
    }
}
