<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Models;

use Database\Factories\Modules\MixedTournament\Models\MixedMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A row of the club-wide member roster (name auto-complete) on the legacy bs_mixed_members table.
 *
 * Date, datetime and decimal columns are deliberately NOT cast: the old
 * application reads and writes plain strings, and a cast would shift or
 * reformat them.
 *
 * @property int $id
 * @property string $label
 * @property string|null $gender m|f
 * @property string|float|null $itn
 * @property string|null $birth_date Y-m-d
 */
class MixedMember extends Model
{
    use HasFactory;

    protected $table = 'bs_mixed_members';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
        ];
    }

    protected static function newFactory(): MixedMemberFactory
    {
        return MixedMemberFactory::new();
    }
}
