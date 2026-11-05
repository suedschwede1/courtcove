<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Models;

use Database\Factories\Modules\Pyramid\Models\PyramidPlayerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A ladder participant on the legacy bs_pyramid_players table.
 *
 * Datetime and decimal columns are deliberately NOT cast: the old application
 * reads and writes plain strings, and a cast would shift or reformat them.
 *
 * @property int $id
 * @property int $ladder_id
 * @property int|null $uid
 * @property string $label
 * @property string|float|null $itn
 * @property string|float $itn_rated
 * @property int|null $position
 * @property string $status waiting|entering|active|paused|removed
 * @property string|null $gender m|f
 * @property int|null $age
 * @property string|null $phone
 * @property string|null $email
 * @property string $joined
 * @property string|null $note
 * @property-read PyramidLadder $ladder
 */
class PyramidPlayer extends Model
{
    use HasFactory;

    protected $table = 'bs_pyramid_players';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];

    protected static function newFactory(): PyramidPlayerFactory
    {
        return PyramidPlayerFactory::new();
    }

    public function ladder(): BelongsTo
    {
        return $this->belongsTo(PyramidLadder::class, 'ladder_id', 'id');
    }
}
