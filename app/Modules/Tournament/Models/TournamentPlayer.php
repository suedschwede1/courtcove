<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Models;

use Database\Factories\Modules\Tournament\Models\TournamentPlayerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A participant of a championship on the legacy bs_tournament_players table.
 *
 * Decimal columns are deliberately NOT cast (the old application reads plain strings).
 *
 * @property int $tpid
 * @property int $tid
 * @property int|null $uid
 * @property string $label
 * @property string|null $gender m|w
 * @property string|null $itn
 * @property string $itn_rated
 * @property int|null $seed
 * @property string|null $grp
 * @property int|null $grp_pos
 * @property int $points_adjust
 * @property string|null $phone
 * @property int|null $partner_tpid
 * @property string|null $note
 */
class TournamentPlayer extends Model
{
    use HasFactory;

    protected $table = 'bs_tournament_players';

    protected $primaryKey = 'tpid';

    public $timestamps = false;

    protected $guarded = [];

    protected static function newFactory(): TournamentPlayerFactory
    {
        return TournamentPlayerFactory::new();
    }

    /** @return BelongsTo<Tournament, $this> */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class, 'tid', 'tid');
    }
}
