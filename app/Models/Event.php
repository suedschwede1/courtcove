<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Special event blocking or limiting court availability.
 *
 * @property int         $eid
 * @property int         $sid
 * @property int         $datetime_start
 * @property int         $datetime_end
 * @property int         $capacity
 * @property EventStatus $status
 */
class Event extends Model
{
    use HasFactory;

    protected $table      = 'bs_events';
    protected $primaryKey = 'eid';
    public $timestamps    = false;
    protected $fillable   = ['sid', 'datetime_start', 'datetime_end', 'capacity', 'status'];
    protected $casts      = ['status' => EventStatus::class];

    /** @return BelongsTo<Square, $this> */
    public function square(): BelongsTo { return $this->belongsTo(Square::class, 'sid', 'sid'); }

    /** @return HasMany<EventMeta, $this> */
    public function meta(): HasMany { return $this->hasMany(EventMeta::class, 'eid', 'eid'); }
}
