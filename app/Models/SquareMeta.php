<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $smid @property int $sid @property string $meta_key @property string|null $meta_value */
class SquareMeta extends Model
{
    use HasFactory;

    protected $table      = 'bs_squares_meta';
    protected $primaryKey = 'smid';
    public $timestamps    = false;
    protected $fillable   = ['sid', 'meta_key', 'meta_value'];

    /** @return BelongsTo<Square, $this> */
    public function square(): BelongsTo { return $this->belongsTo(Square::class, 'sid', 'sid'); }
}
