<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalYear extends Model
{
    protected $fillable = ['name', 'starts_on', 'ends_on', 'faellig_am'];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'faellig_am' => 'date',
    ];

    public function feeCategoryRates(): HasMany
    {
        return $this->hasMany(FeeCategoryRate::class);
    }

    public function memberDues(): HasMany
    {
        return $this->hasMany(MemberDue::class);
    }
}
