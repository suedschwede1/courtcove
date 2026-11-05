<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Models;

use Database\Factories\Modules\MixedTournament\Models\MixedEventGrantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A per-event role grant on the legacy bs_mixed_event_grants table.
 *
 * Date, datetime and decimal columns are deliberately NOT cast: the old
 * application reads and writes plain strings, and a cast would shift or
 * reformat them.
 *
 * @property int $id
 * @property int $event_id
 * @property int $uid
 * @property string $role score|admin
 * @property string $created
 */
class MixedEventGrant extends Model
{
    use HasFactory;

    protected $table = 'bs_mixed_event_grants';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'event_id' => 'integer',
            'uid' => 'integer',
        ];
    }

    protected static function newFactory(): MixedEventGrantFactory
    {
        return MixedEventGrantFactory::new();
    }
}
