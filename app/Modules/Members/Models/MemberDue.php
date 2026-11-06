<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $member_id
 * @property int $fiscal_year_id
 * @property int $fee_category_id
 * @property string $amount
 * @property string $status
 * @property string|null $teilbetrag
 * @property string|null $kommentar
 * @property int|null $bezahlt_bis_fiscal_year_id
 * @property Carbon|null $faellig_am
 * @property-read FiscalYear $fiscalYear
 */
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

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<FiscalYear, $this> */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /** @return BelongsTo<FeeCategory, $this> */
    public function feeCategory(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class);
    }

    /** @return BelongsTo<FiscalYear, $this> */
    public function bezahltBisFiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'bezahlt_bis_fiscal_year_id');
    }

    /** @return BelongsToMany<Payment, $this> */
    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'payment_dues');
    }

    public function isPaid(): bool
    {
        return $this->status === 'bezahlt' || $this->payments()->exists();
    }

    /**
     * @param  Builder<MemberDue>  $query
     * @return Builder<MemberDue>
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('status', 'bezahlt')->orWhereHas('payments');
        });
    }

    /**
     * @param  Builder<MemberDue>  $query
     * @return Builder<MemberDue>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', '!=', 'bezahlt')->whereDoesntHave('payments');
    }

    /**
     * @param  Builder<MemberDue>  $query
     * @return Builder<MemberDue>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('status', 'ueberfaellig')
                ->orWhere(function (Builder $query): void {
                    $query->where('status', 'ausstehend')
                        ->whereNotNull('faellig_am')
                        ->where('faellig_am', '<', now()->toDateString());
                });
        });
    }

    /** Offener Rest: Betrag minus Teilbetrag, nie negativ; 0 bei Status "bezahlt". */
    public function outstandingAmount(): string
    {
        if ($this->status === 'bezahlt') {
            return '0.00';
        }

        return number_format(max(0.0, (float) $this->amount - (float) ($this->teilbetrag ?? 0)), 2, '.', '');
    }

    /** Der angezeigte Status: "ausstehend" mit verstrichenem Fälligkeitsdatum gilt automatisch als "überfällig". */
    public function displayStatus(): string
    {
        if ($this->status === 'ausstehend' && $this->faellig_am !== null && $this->faellig_am->endOfDay()->isPast()) {
            return 'ueberfaellig';
        }

        return $this->status;
    }
}
