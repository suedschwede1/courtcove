<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pricing rule for a court within a date/time window.
 *
 * @property int $spid
 * @property int $sid
 * @property int $priority
 * @property string $date_start
 * @property string $date_end
 * @property int $price
 * @property int $rate
 */
class SquarePricing extends Model
{
    use HasFactory;

    protected $table = 'bs_squares_pricing';

    protected $primaryKey = 'spid';

    public $timestamps = false;

    protected $fillable = [
        'sid', 'priority', 'date_start', 'date_end', 'day_start', 'day_end',
        'time_start', 'time_end', 'price', 'rate', 'gross', 'per_time_block', 'per_quantity',
    ];

    /** @return BelongsTo<Square, $this> */
    public function square(): BelongsTo
    {
        return $this->belongsTo(Square::class, 'sid', 'sid');
    }
}
