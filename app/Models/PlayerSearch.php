<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A booking looking for missing players.
 *
 * @property int $id
 * @property int $bid
 * @property int $needed Originally searched places
 * @property Carbon $ends_at
 * @property string $status open|filled|ended|lapsed|cancelled
 */
class PlayerSearch extends Model
{
    public const OPEN = 'open';

    public const FILLED = 'filled';

    public const ENDED = 'ended';

    public const LAPSED = 'lapsed';

    public const CANCELLED = 'cancelled';

    protected $table = 'player_exchange_searches';

    protected $fillable = ['bid', 'needed', 'ends_at', 'status'];

    protected function casts(): array
    {
        return [
            'bid' => 'integer',
            'needed' => 'integer',
            'ends_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'bid', 'bid');
    }

    /** @return HasMany<PlayerSignup, $this> */
    public function signups(): HasMany
    {
        return $this->hasMany(PlayerSignup::class, 'search_id');
    }

    /** Places still free (never negative). Uses the loaded signups when present (no query per row in lists). */
    public function remaining(): int
    {
        $taken = $this->relationLoaded('signups') ? $this->signups->count() : $this->signups()->count();

        return max(0, $this->needed - $taken);
    }

    /** Open, places left and the deadline not reached yet (also before the lapse job ran). */
    public function isSeeking(): bool
    {
        return $this->status === self::OPEN && $this->ends_at->isFuture() && $this->remaining() > 0;
    }
}
