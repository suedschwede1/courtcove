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

    /** Places still free (never negative). */
    public function remaining(): int
    {
        return max(0, $this->needed - $this->signups()->count());
    }
}
