<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One time-slot reservation within a booking. Subscriptions have multiple.
 *
 * @property int $rid
 * @property int $bid
 * @property int $date       Unix timestamp (midnight)
 * @property int $time_start Seconds from midnight
 * @property int $time_end   Seconds from midnight
 */
class Reservation extends Model
{
    use HasFactory;

    protected $table      = 'bs_reservations';
    protected $primaryKey = 'rid';
    public $timestamps    = false;
    protected $fillable   = ['bid', 'date', 'time_start', 'time_end'];

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class, 'bid', 'bid'); }

    /** @return HasMany<ReservationMeta, $this> */
    public function meta(): HasMany { return $this->hasMany(ReservationMeta::class, 'rid', 'rid'); }
}
