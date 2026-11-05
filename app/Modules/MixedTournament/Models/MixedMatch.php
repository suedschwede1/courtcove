<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Models;

use Database\Factories\Modules\MixedTournament\Models\MixedMatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A match of a round on the legacy bs_mixed_matches table.
 *
 * Date, datetime and decimal columns are deliberately NOT cast: the old
 * application reads and writes plain strings, and a cast would shift or
 * reformat them.
 *
 * @property int $id
 * @property int $round_id
 * @property int $group_no
 * @property int $match_no
 * @property int $team_a_p1
 * @property int $team_a_p2
 * @property int $team_b_p1
 * @property int $team_b_p2
 * @property int|null $games_a
 * @property int|null $games_b
 * @property bool $is_extra
 */
class MixedMatch extends Model
{
    use HasFactory;

    protected $table = 'bs_mixed_matches';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'round_id' => 'integer',
            'group_no' => 'integer',
            'match_no' => 'integer',
            'team_a_p1' => 'integer',
            'team_a_p2' => 'integer',
            'team_b_p1' => 'integer',
            'team_b_p2' => 'integer',
            'games_a' => 'integer',
            'games_b' => 'integer',
            'is_extra' => 'boolean',
        ];
    }

    protected static function newFactory(): MixedMatchFactory
    {
        return MixedMatchFactory::new();
    }
}
