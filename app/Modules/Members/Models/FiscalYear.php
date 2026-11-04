<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property \Illuminate\Support\Carbon $starts_on
 * @property \Illuminate\Support\Carbon $ends_on
 * @property \Illuminate\Support\Carbon|null $faellig_am
 */
class FiscalYear extends Model
{
    protected $fillable = ['name', 'starts_on', 'ends_on', 'faellig_am'];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'faellig_am' => 'date',
    ];

    /** @return HasMany<FeeCategoryRate, $this> */
    public function feeCategoryRates(): HasMany
    {
        return $this->hasMany(FeeCategoryRate::class);
    }

    /** @return HasMany<MemberDue, $this> */
    public function memberDues(): HasMany
    {
        return $this->hasMany(MemberDue::class);
    }
}
