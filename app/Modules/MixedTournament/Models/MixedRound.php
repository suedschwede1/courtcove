<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Models;

use Database\Factories\Modules\MixedTournament\Models\MixedRoundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A round of an event on the legacy bs_mixed_rounds table.
 *
 * Date, datetime and decimal columns are deliberately NOT cast: the old
 * application reads and writes plain strings, and a cast would shift or
 * reformat them.
 *
 * @property int $id
 * @property int $event_id
 * @property int $round_no
 * @property string $group_size_mode 4_only|4_and_5
 * @property string $distribution random|itn|itn_balanced|age|ladies_apart
 * @property string $status proposed|confirmed|completed
 */
class MixedRound extends Model
{
    use HasFactory;

    protected $table = 'bs_mixed_rounds';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'event_id' => 'integer',
            'round_no' => 'integer',
        ];
    }

    protected static function newFactory(): MixedRoundFactory
    {
        return MixedRoundFactory::new();
    }

    /** @return BelongsTo<MixedEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(MixedEvent::class, 'event_id', 'id');
    }

    /** @return HasMany<MixedRoundPlayer, $this> */
    public function players(): HasMany
    {
        return $this->hasMany(MixedRoundPlayer::class, 'round_id', 'id')->orderBy('id');
    }

    /** @return HasMany<MixedMatch, $this> */
    public function matches(): HasMany
    {
        return $this->hasMany(MixedMatch::class, 'round_id', 'id')->orderBy('group_no')->orderBy('match_no')->orderBy('id');
    }

    public function isProposed(): bool
    {
        return $this->status === 'proposed';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
