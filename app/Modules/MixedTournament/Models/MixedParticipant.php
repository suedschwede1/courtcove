<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Models;

use Database\Factories\Modules\MixedTournament\Models\MixedParticipantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A participant of one event on the legacy bs_mixed_participants table.
 *
 * Date, datetime and decimal columns are deliberately NOT cast: the old
 * application reads and writes plain strings, and a cast would shift or
 * reformat them.
 *
 * @property int $id
 * @property int $event_id
 * @property int|null $uid
 * @property string $label
 * @property string|null $gender m|f
 * @property string|float|null $itn
 * @property int|null $age
 * @property string $status active|paused
 * @property bool $no_extra_match
 */
class MixedParticipant extends Model
{
    use HasFactory;

    protected $table = 'bs_mixed_participants';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'event_id' => 'integer',
            'uid' => 'integer',
            'age' => 'integer',
            'no_extra_match' => 'boolean',
        ];
    }

    protected static function newFactory(): MixedParticipantFactory
    {
        return MixedParticipantFactory::new();
    }

    public function itnFloat(): ?float
    {
        return $this->itn === null ? null : (float) $this->itn;
    }
}
