<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Optional add-on product for a court (e.g. floodlight, ball machine).
 *
 * @property int    $spid
 * @property int    $sid
 * @property int    $priority
 * @property string $name
 * @property string $options
 * @property int    $price
 * @property int    $rate
 * @property bool   $gross
 */
class SquareProduct extends Model
{
    use HasFactory;

    protected $table      = 'bs_squares_products';
    protected $primaryKey = 'spid';
    public $timestamps    = false;
    protected $fillable   = ['sid', 'priority', 'date_start', 'date_end', 'name', 'description', 'options', 'price', 'rate', 'gross', 'locale'];

    /** @return BelongsTo<Square, $this> */
    public function square(): BelongsTo { return $this->belongsTo(Square::class, 'sid', 'sid'); }
}
