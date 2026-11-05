<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Models;

use Database\Factories\Modules\Tournament\Models\TournamentLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line of a bracket (seed, bye or pool slot) on the legacy bs_tournament_lines table.
 *
 * @property int $tlid
 * @property int $tid
 * @property string $phase
 * @property int $line
 * @property string $kind seed|bye|pool
 * @property int|null $seed_no
 * @property string|null $pool
 * @property string|null $placeholder
 * @property int|null $tpid
 */
class TournamentLine extends Model
{
    use HasFactory;

    protected $table = 'bs_tournament_lines';

    protected $primaryKey = 'tlid';

    public $timestamps = false;

    protected $guarded = [];

    protected static function newFactory(): TournamentLineFactory
    {
        return TournamentLineFactory::new();
    }

    /** @return BelongsTo<Tournament, $this> */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class, 'tid', 'tid');
    }
}
