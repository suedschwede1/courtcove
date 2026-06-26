<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A court booking - parent entity owning reservations and bills.
 *
 * Real bs_bookings schema uses plain string statuses (no enum casts):
 *   status         = single | subscription | cancelled
 *   status_billing = pending | paid
 *   visibility     = public
 *
 * @property int    $bid
 * @property int    $uid
 * @property int    $sid
 * @property string $status
 * @property string $status_billing
 * @property string $visibility
 * @property int    $quantity   Number of players
 * @property string $created    DATETIME
 */
class Booking extends Model
{
    use HasFactory;

    protected $table      = 'bs_bookings';
    protected $primaryKey = 'bid';
    public $timestamps    = false;

    protected $fillable = [
        'uid', 'sid', 'status', 'status_billing', 'visibility', 'quantity', 'created',
    ];

    /** Active (non-cancelled) booking statuses. */
    public const ACTIVE_STATUSES = ['single', 'subscription'];

    public function getRouteKeyName(): string { return 'bid'; }

    public function isCancelled(): bool { return $this->status === 'cancelled'; }

    public function isSubscription(): bool { return $this->status === 'subscription'; }

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

    /**
     * Legacy player names are stored as a serialized PHP array in booking meta.
     * Normalize them here so the Blade view stays simple and safe.
     *
     * @return list<string>
     */
    public function getPlayerNamesAttribute(): array
    {
        $metaValue = $this->meta->firstWhere('key', 'player-names')?->value;
        if (!is_string($metaValue) || $metaValue === '' || $metaValue === 'N;') {
            return [];
        }

        $parsed = rescue(
            fn() => unserialize($metaValue, ['allowed_classes' => false]),
            [],
            report: false,
        );

        if (!is_array($parsed)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn(mixed $player): ?string => is_array($player) && isset($player['value'])
                ? trim((string) $player['value'])
                : null,
            $parsed,
        )));
    }

    public function getPlayerNamesLabelAttribute(): ?string
    {
        $names = $this->player_names;

        return $names !== [] ? implode(', ', $names) : null;
    }
}
