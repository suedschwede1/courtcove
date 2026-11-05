<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A player slot of a round on the legacy bs_mixed_round_players table. There is NO unique key: an extra-group filler has two rows (one per group), a Mexicano sitter has one row with group_no NULL.
 *
 * Date, datetime and decimal columns are deliberately NOT cast: the old
 * application reads and writes plain strings, and a cast would shift or
 * reformat them.
 *
 * @property int $id
 * @property int $round_id
 * @property int $participant_id
 * @property int|null $group_no
 */
class MixedRoundPlayer extends Model
{
    protected $table = 'bs_mixed_round_players';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'round_id' => 'integer',
            'participant_id' => 'integer',
            'group_no' => 'integer',
        ];
    }
}
