<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Models;

use Database\Factories\Modules\MixedTournament\Models\MixedEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A tournament event on the legacy bs_mixed_events table.
 *
 * Date, datetime and decimal columns are deliberately NOT cast: the old
 * application reads and writes plain strings, and a cast would shift or
 * reformat them.
 *
 * @property int $id
 * @property string $title
 * @property string $event_date Y-m-d
 * @property string $status draft|running|done
 * @property bool $is_default
 * @property bool $is_test
 * @property int $court_count
 * @property string $mode americano|mexicano
 * @property string $created
 */
class MixedEvent extends Model
{
    use HasFactory;

    protected $table = 'bs_mixed_events';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'is_default' => 'boolean',
            'is_test' => 'boolean',
            'court_count' => 'integer',
        ];
    }

    protected static function newFactory(): MixedEventFactory
    {
        return MixedEventFactory::new();
    }

    /** @return HasMany<MixedParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(MixedParticipant::class, 'event_id', 'id')->orderBy('id');
    }

    /** @return HasMany<MixedRound, $this> */
    public function rounds(): HasMany
    {
        return $this->hasMany(MixedRound::class, 'event_id', 'id')->orderBy('id');
    }

    /** @return HasMany<MixedAvoidPair, $this> */
    public function avoidPairs(): HasMany
    {
        return $this->hasMany(MixedAvoidPair::class, 'event_id', 'id')->orderBy('id');
    }

    /** @return HasMany<MixedEventGrant, $this> */
    public function grants(): HasMany
    {
        return $this->hasMany(MixedEventGrant::class, 'event_id', 'id')->orderBy('id');
    }

    public function isMexicano(): bool
    {
        return $this->mode === 'mexicano';
    }
}
