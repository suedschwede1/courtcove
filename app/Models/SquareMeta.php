<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $smid @property int $sid @property string $key @property string $value @property string|null $locale */
class SquareMeta extends Model
{
    use HasFactory;

    protected $table = 'bs_squares_meta';

    protected $primaryKey = 'smid';

    public $timestamps = false;

    protected $fillable = ['sid', 'key', 'value', 'locale'];

    /** @return BelongsTo<Square, $this> */
    public function square(): BelongsTo
    {
        return $this->belongsTo(Square::class, 'sid', 'sid');
    }
}
