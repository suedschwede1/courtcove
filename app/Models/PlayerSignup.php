<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member who signed up for a player search.
 *
 * @property int $id
 * @property int $search_id
 * @property int $uid
 * @property int $slot Player position in the booking (2-4)
 */
class PlayerSignup extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'player_exchange_signups';

    protected $fillable = ['search_id', 'uid', 'slot'];

    /** @return BelongsTo<PlayerSearch, $this> */
    public function search(): BelongsTo
    {
        return $this->belongsTo(PlayerSearch::class, 'search_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uid', 'uid');
    }
}
