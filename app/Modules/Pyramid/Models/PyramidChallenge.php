<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Models;

use Database\Factories\Modules\Pyramid\Models\PyramidChallengeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A challenge between two ladder players on the legacy bs_pyramid_challenges table.
 *
 * Datetime and decimal columns are deliberately NOT cast: the old application
 * reads and writes plain strings, and a cast would shift or reformat them.
 *
 * @property int $id
 * @property int $ladder_id
 * @property int $challenger_id
 * @property int $opponent_id
 * @property int|null $challenger_pos
 * @property int|null $opponent_pos
 * @property string $status open|scheduled|played|declined|withdrawn|forfeited|expired
 * @property string $created
 * @property string|null $deadline
 * @property string|null $scheduled_at
 * @property string|null $score
 * @property int|null $winner_id
 * @property int|null $entered_uid
 * @property string|null $entered
 * @property string|null $decline_reason other_match|injury
 * @property string|null $admin_note
 * @property string|null $resolved
 * @property-read PyramidLadder $ladder
 * @property-read PyramidPlayer $challenger
 * @property-read PyramidPlayer $opponent
 */
class PyramidChallenge extends Model
{
    use HasFactory;

    protected $table = 'bs_pyramid_challenges';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];

    protected static function newFactory(): PyramidChallengeFactory
    {
        return PyramidChallengeFactory::new();
    }

    public function ladder(): BelongsTo
    {
        return $this->belongsTo(PyramidLadder::class, 'ladder_id', 'id');
    }

    public function challenger(): BelongsTo
    {
        return $this->belongsTo(PyramidPlayer::class, 'challenger_id', 'id');
    }

    public function opponent(): BelongsTo
    {
        return $this->belongsTo(PyramidPlayer::class, 'opponent_id', 'id');
    }

    public function isRunning(): bool
    {
        return in_array($this->status, ['open', 'scheduled'], true);
    }
}
