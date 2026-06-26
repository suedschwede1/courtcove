<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SquareStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A bookable court/square.
 *
 * @property int          $sid
 * @property string       $name
 * @property string|null  $alias                   Human-readable court name (e.g. "Court A")
 * @property SquareStatus $status
 * @property int          $capacity                Max concurrent players
 * @property int          $capacity_heterogenic    Allow mixed player counts (0/1)
 * @property int          $time_start              Day start in seconds from midnight
 * @property int          $time_end                Day end in seconds from midnight
 * @property int          $time_block              Slot size in seconds
 * @property int          $time_block_bookable     Minimum bookable duration in seconds
 * @property int          $time_block_bookable_max Max per-user per-day seconds (0=unlimited)
 * @property int          $min_range_book          Min advance booking in seconds (0=allow past)
 * @property int          $range_book              Max advance booking in seconds (0=unlimited)
 * @property int          $range_cancel            Cancellation deadline in seconds
 * @property int          $priority                Display sort order
 */
class Square extends Model
{
    use HasFactory;

    protected $table      = 'bs_squares';
    protected $primaryKey = 'sid';
    public $timestamps    = false;

    protected $fillable = [
        'name', 'alias', 'status', 'capacity', 'capacity_heterogenic',
        'time_start', 'time_end', 'time_block', 'time_block_bookable',
        'time_block_bookable_max', 'min_range_book', 'range_book', 'range_cancel', 'priority',
    ];

    protected $casts = ['status' => SquareStatus::class];

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany { return $this->hasMany(Booking::class, 'sid', 'sid'); }

    /** @return HasMany<SquareMeta, $this> */
    public function meta(): HasMany { return $this->hasMany(SquareMeta::class, 'sid', 'sid'); }

    /** @return HasMany<SquareProduct, $this> */
    public function products(): HasMany { return $this->hasMany(SquareProduct::class, 'sid', 'sid'); }

    /** @return HasMany<SquarePricing, $this> */
    public function pricing(): HasMany { return $this->hasMany(SquarePricing::class, 'sid', 'sid'); }

    /** @return HasMany<Event, $this> */
    public function events(): HasMany { return $this->hasMany(Event::class, 'sid', 'sid'); }

    /** Whether public users may book this court. */
    public function isBookable(): bool { return $this->status === SquareStatus::Enabled; }

    /** Whether the court is completely unavailable to everyone. */
    public function isDisabled(): bool { return $this->status === SquareStatus::Disabled; }
}
