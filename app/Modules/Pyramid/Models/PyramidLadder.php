<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Models;

use Database\Factories\Modules\Pyramid\Models\PyramidLadderFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A challenge ladder (Forderungspyramide) on the legacy bs_pyramid_ladders table.
 *
 * Datetime and decimal columns are deliberately NOT cast: the old application
 * reads and writes plain strings, and a cast would shift or reformat them.
 *
 * @property int $id
 * @property string $title
 * @property int $season
 * @property string|null $category
 * @property string $status setup|running|closed
 * @property string|float $itn_default
 * @property int $days_to_play
 * @property int $winner_protected_days
 * @property int $loser_cooldown_days
 * @property int|bool $is_default
 * @property string|null $rules_version
 * @property string $created
 * @property-read Collection<int, PyramidPlayer> $players
 * @property-read Collection<int, PyramidChallenge> $challenges
 */
class PyramidLadder extends Model
{
    use HasFactory;

    protected $table = 'bs_pyramid_ladders';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];

    protected static function newFactory(): PyramidLadderFactory
    {
        return PyramidLadderFactory::new();
    }

    public function players(): HasMany
    {
        return $this->hasMany(PyramidPlayer::class, 'ladder_id', 'id');
    }

    public function challenges(): HasMany
    {
        return $this->hasMany(PyramidChallenge::class, 'ladder_id', 'id');
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }
}
