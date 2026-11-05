<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 */
class FeeCategory extends Model
{
    protected $fillable = ['name'];

    /** @return HasMany<FeeCategoryRate, $this> */
    public function rates(): HasMany
    {
        return $this->hasMany(FeeCategoryRate::class);
    }
}
