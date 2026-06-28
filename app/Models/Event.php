<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Special event blocking or limiting court availability.
 *
 * @property int $eid
 * @property int $sid
 * @property string $datetime_start
 * @property string $datetime_end
 * @property int $capacity
 * @property string $status 'enabled'|'disabled'
 */
class Event extends Model
{
    use HasFactory;

    protected $table = 'bs_events';

    protected $primaryKey = 'eid';

    public $timestamps = false;

    protected $fillable = ['sid', 'datetime_start', 'datetime_end', 'capacity', 'status'];

    // Legacy DB stores status as plain string ('enabled'/'disabled') and datetimes as DATETIME columns.
    protected $casts = [
        'datetime_start' => 'datetime',
        'datetime_end' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'eid';
    }

    /** @return BelongsTo<Square, $this> */
    public function square(): BelongsTo
    {
        return $this->belongsTo(Square::class, 'sid', 'sid');
    }

    /** @return HasMany<EventMeta, $this> */
    public function meta(): HasMany
    {
        return $this->hasMany(EventMeta::class, 'eid', 'eid');
    }
}
