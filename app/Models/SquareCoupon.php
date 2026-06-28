<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A discount coupon for a court.
 *
 * @property int $scid
 * @property int|null $sid
 * @property string $code
 * @property int $discount_for_booking
 * @property int $discount_for_products
 * @property bool $discount_in_percent
 */
class SquareCoupon extends Model
{
    use HasFactory;

    protected $table = 'bs_squares_coupons';

    protected $primaryKey = 'scid';

    public $timestamps = false;

    protected $fillable = ['sid', 'code', 'date_start', 'date_end', 'discount_for_booking', 'discount_for_products', 'discount_in_percent'];

    /** @return BelongsTo<Square, $this> */
    public function square(): BelongsTo
    {
        return $this->belongsTo(Square::class, 'sid', 'sid');
    }
}
