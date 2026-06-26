<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $spid @property int $sid @property string $name @property ProductType $type @property int $price @property int $priority */
class SquareProduct extends Model
{
    use HasFactory;

    protected $table      = 'bs_squares_products';
    protected $primaryKey = 'spid';
    public $timestamps    = false;
    protected $fillable   = ['sid', 'name', 'type', 'price', 'priority'];
    protected $casts      = ['type' => ProductType::class];

    /** @return BelongsTo<Square, $this> */
    public function square(): BelongsTo { return $this->belongsTo(Square::class, 'sid', 'sid'); }
}
