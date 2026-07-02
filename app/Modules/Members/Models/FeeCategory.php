<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeCategory extends Model
{
    protected $fillable = ['name'];

    public function rates(): HasMany
    {
        return $this->hasMany(FeeCategoryRate::class);
    }
}
