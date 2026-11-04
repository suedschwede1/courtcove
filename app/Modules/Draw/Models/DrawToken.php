<?php

declare(strict_types=1);

namespace App\Modules\Draw\Models;

use Database\Factories\Modules\Draw\Models\DrawTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pickable token of a token draw (legacy bs_draw_tokens). Datetime columns stay
 * raw strings, see Draw.
 *
 * @property int $dtid
 * @property int $did
 * @property int $position
 * @property string $token
 * @property string|null $slot
 * @property int|null $claimed_tpid
 * @property int|null $claimed_uid
 * @property string|null $claimed
 * @property string|null $assigned_by pick|fallback
 * @property-read Draw $draw
 */
class DrawToken extends Model
{
    use HasFactory;

    protected $table = 'bs_draw_tokens';

    protected $primaryKey = 'dtid';

    public $timestamps = false;

    protected $guarded = [];

    protected static function newFactory(): DrawTokenFactory
    {
        return DrawTokenFactory::new();
    }

    public function draw(): BelongsTo
    {
        return $this->belongsTo(Draw::class, 'did', 'did');
    }
}
