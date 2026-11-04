<?php

declare(strict_types=1);

namespace App\Modules\Draw\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A participant who may pick a token of a draw (legacy bs_draw_eligible).
 *
 * @property int $deid
 * @property int $did
 * @property int $tpid
 * @property-read Draw $draw
 */
class DrawEligible extends Model
{
    protected $table = 'bs_draw_eligible';

    protected $primaryKey = 'deid';

    public $timestamps = false;

    protected $guarded = [];

    public function draw(): BelongsTo
    {
        return $this->belongsTo(Draw::class, 'did', 'did');
    }
}
