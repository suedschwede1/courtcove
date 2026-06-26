<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingStatus;
use App\Enums\BookingStatus;
use App\Enums\Visibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A court booking — parent entity owning reservations and bills.
 *
 * @property int           $bid
 * @property int           $uid
 * @property int           $sid
 * @property BookingStatus $status
 * @property BillingStatus $status_billing
 * @property Visibility    $visibility
 * @property int           $quantity   Number of players
 * @property int           $created    Unix timestamp
 * @property int           $updated    Unix timestamp
 */
class Booking extends Model
{
    use HasFactory;

    protected $table      = 'bs_bookings';
    protected $primaryKey = 'bid';
    public $timestamps    = false;

    protected $fillable = [
        'uid', 'sid', 'status', 'status_billing', 'visibility', 'quantity', 'created', 'updated',
    ];

    protected $casts = [
        'status'         => BookingStatus::class,
        'status_billing' => BillingStatus::class,
        'visibility'     => Visibility::class,
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'uid', 'uid'); }

    /** @return BelongsTo<Square, $this> */
    public function square(): BelongsTo { return $this->belongsTo(Square::class, 'sid', 'sid'); }

    /** @return HasMany<Reservation, $this> */
    public function reservations(): HasMany { return $this->hasMany(Reservation::class, 'bid', 'bid'); }

    /** @return HasMany<BookingBill, $this> */
    public function bills(): HasMany { return $this->hasMany(BookingBill::class, 'bid', 'bid'); }

    /** @return HasMany<BookingMeta, $this> */
    public function meta(): HasMany { return $this->hasMany(BookingMeta::class, 'bid', 'bid'); }
}
