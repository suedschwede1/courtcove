<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Models;

use Database\Factories\Modules\Tournament\Models\TournamentMatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A match of a championship on the legacy bs_tournament_matches table.
 *
 * Date, time and datetime columns are deliberately NOT cast (the old application reads
 * and writes plain strings).
 *
 * @property int $tmid
 * @property int $tid
 * @property string $phase
 * @property string|null $mkey
 * @property string|null $grp
 * @property int|null $rnd
 * @property int|null $slot
 * @property int|null $tpid_a
 * @property int|null $tpid_b
 * @property string|null $deadline
 * @property string|null $played
 * @property string|null $played_time
 * @property string|null $court 1|2|3
 * @property string|null $score
 * @property int|null $s1a
 * @property int|null $s1b
 * @property int|null $s2a
 * @property int|null $s2b
 * @property int|null $mtba
 * @property int|null $mtbb
 * @property int|null $sets_a
 * @property int|null $sets_b
 * @property int|null $games_a
 * @property int|null $games_b
 * @property string $retired none|ret_a|ret_b|wo_a|wo_b
 * @property int|null $winner
 * @property int|null $entered_uid
 * @property string|null $entered
 * @property int|null $confirmed_uid
 * @property string|null $confirmed
 */
class TournamentMatch extends Model
{
    use HasFactory;

    protected $table = 'bs_tournament_matches';

    protected $primaryKey = 'tmid';

    public $timestamps = false;

    protected $guarded = [];

    protected static function newFactory(): TournamentMatchFactory
    {
        return TournamentMatchFactory::new();
    }

    /** @return BelongsTo<Tournament, $this> */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class, 'tid', 'tid');
    }

    public function isPlayed(): bool
    {
        return $this->winner !== null;
    }
}
