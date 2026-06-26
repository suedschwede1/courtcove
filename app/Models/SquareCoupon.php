<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @property int $scid @property int $sid @property string $code @property CouponType $type @property int $value @property string $status */
class SquareCoupon extends Model
{
    use HasFactory;

    protected $table      = 'bs_squares_coupons';
    protected $primaryKey = 'scid';
    public $timestamps    = false;
    protected $fillable   = ['sid', 'code', 'type', 'value', 'valid_from', 'valid_until', 'usage_max', 'usage_count', 'status'];
    protected $casts      = ['type' => CouponType::class];
}
