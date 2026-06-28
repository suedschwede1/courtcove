<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SquareStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A bookable court/square. Real bs_squares schema: opening hours are TIME columns,
 * priority is a float, and there is NO `alias` column - the court alias and labels
 * live in bs_squares_meta (keys like 'alias', 'label.free', 'public_names', ...).
 *
 * @property int $sid
 * @property string $name
 * @property SquareStatus $status
 * @property float $priority Display sort order
 * @property int $capacity Max concurrent players
 * @property int $capacity_heterogenic Allow mixed player counts (0/1)
 * @property int $allow_notes Allow user notes (0/1)
 * @property string $time_start Opening time 'H:i:s'
 * @property string $time_end Closing time 'H:i:s'
 * @property int $time_block Slot size in seconds
 * @property int $time_block_bookable Minimum bookable duration in seconds
 * @property int|null $time_block_bookable_max Max per-user per-day seconds (0/null=unlimited)
 * @property int $min_range_book Min advance booking in seconds
 * @property int|null $range_book Max advance booking in seconds (0/null=unlimited)
 * @property int $max_active_bookings Max concurrent open bookings per user (0=unlimited)
 * @property int|null $range_cancel Cancellation deadline in seconds
 */
class Square extends Model
{
    use HasFactory;

    /** Allowed values for the bs_squares_meta 'capacity-ask-names' dropdown. */
    public const ASK_NAMES_OPTIONS = [
        '', 'optional-names', 'optional-names-email', 'optional-names-phone', 'optional-names-email-phone',
        'required-names', 'required-names-email', 'required-names-phone', 'required-names-email-phone',
    ];

    protected $table = 'bs_squares';

    protected $primaryKey = 'sid';

    public $timestamps = false;

    protected $fillable = [
        'name', 'status', 'priority', 'capacity', 'capacity_heterogenic', 'allow_notes',
        'time_start', 'time_end', 'time_block', 'time_block_bookable', 'time_block_bookable_max',
        'min_range_book', 'range_book', 'max_active_bookings', 'range_cancel',
    ];

    protected $casts = ['status' => SquareStatus::class];

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'sid', 'sid');
    }

    /** @return HasMany<SquareMeta, $this> */
    public function meta(): HasMany
    {
        return $this->hasMany(SquareMeta::class, 'sid', 'sid');
    }

    /** @return HasMany<SquareProduct, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(SquareProduct::class, 'sid', 'sid');
    }

    /** @return HasMany<SquarePricing, $this> */
    public function pricing(): HasMany
    {
        return $this->hasMany(SquarePricing::class, 'sid', 'sid');
    }

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'sid', 'sid');
    }

    /** Read a single meta value by key from bs_squares_meta. */
    public function getMeta(string $key, ?string $default = null): ?string
    {
        if ($this->relationLoaded('meta')) {
            $value = $this->meta->firstWhere('key', $key)?->value;

            return $value !== null ? (string) $value : $default;
        }

        $value = $this->meta()->where('key', $key)->value('value');

        return $value !== null ? (string) $value : $default;
    }

    /** Upsert a single meta value (bs_squares_meta key/value, locale=null); null deletes the row. */
    public function setMeta(string $key, ?string $value): void
    {
        if ($value === null) {
            $this->meta()->where('key', $key)->delete();

            return;
        }

        $row = $this->meta()->where('key', $key)->first();
        if ($row) {
            $row->update(['value' => $value]);
        } else {
            $this->meta()->create(['key' => $key, 'value' => $value, 'locale' => null]);
        }
    }

    /** Court display alias (from meta), without hardcoded fallback. */
    public function getAliasAttribute(): ?string
    {
        return $this->getMeta('alias');
    }

    /** Public-facing court label used across calendar, dialogs, and tooltips. */
    public function getDisplayNameAttribute(): string
    {
        return $this->alias
            ?? config('booking.square_names')[(string) $this->name]
            ?? (string) $this->name;
    }

    /** Whether public users may book this court. */
    public function isBookable(): bool
    {
        return $this->status === SquareStatus::Enabled;
    }

    /** Whether the court is completely unavailable to everyone. */
    public function isDisabled(): bool
    {
        return $this->status === SquareStatus::Disabled;
    }
}
