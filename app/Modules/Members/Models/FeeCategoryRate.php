<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $fee_category_id
 * @property int $fiscal_year_id
 * @property string $amount
 */
class FeeCategoryRate extends Model
{
    protected $fillable = ['fee_category_id', 'fiscal_year_id', 'amount'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /** @return BelongsTo<FeeCategory, $this> */
    public function feeCategory(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class);
    }

    /** @return BelongsTo<FiscalYear, $this> */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }
}
