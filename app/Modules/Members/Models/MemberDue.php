<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MemberDue extends Model
{
    public const STATUSES = ['ausstehend', 'bezahlt', 'ueberfaellig'];

    protected $fillable = [
        'member_id', 'fiscal_year_id', 'fee_category_id', 'amount',
        'status', 'teilbetrag', 'kommentar', 'bezahlt_bis_fiscal_year_id', 'faellig_am',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'teilbetrag' => 'decimal:2',
        'faellig_am' => 'date',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function feeCategory(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class);
    }

    public function bezahltBisFiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'bezahlt_bis_fiscal_year_id');
    }

    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'payment_dues');
    }

    public function isPaid(): bool
    {
        return $this->payments()->exists();
    }

    /** Der angezeigte Status: "ausstehend" mit verstrichenem Fälligkeitsdatum gilt automatisch als "überfällig". */
    public function displayStatus(): string
    {
        if ($this->status === 'ausstehend' && $this->faellig_am !== null && $this->faellig_am->isPast()) {
            return 'ueberfaellig';
        }

        return $this->status;
    }
}
