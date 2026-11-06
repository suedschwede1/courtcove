<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use App\Modules\Members\Models\Concerns\HasDueStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * One flat due per household and fiscal year (unique). Which members it covers is stored per year in
 * household_due_member; payments are linked through household_due_payment. Status rules are shared
 * with MemberDue (HasDueStatus).
 *
 * @property int $id
 * @property int $household_id
 * @property int $fiscal_year_id
 * @property int $fee_category_id
 * @property string $amount
 * @property string $status
 * @property string|null $teilbetrag
 * @property string|null $kommentar
 * @property int|null $bezahlt_bis_fiscal_year_id
 * @property Carbon|null $faellig_am
 * @property Carbon|null $bezahlt_am
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MemberHousehold $household
 * @property-read FiscalYear $fiscalYear
 * @property-read FeeCategory $feeCategory
 * @property-read FiscalYear|null $bezahltBisFiscalYear
 * @property-read Collection<int, Member> $coveredMembers
 * @property-read Collection<int, Payment> $payments
 */
class HouseholdDue extends Model
{
    use HasDueStatus;

    public const STATUSES = ['ausstehend', 'bezahlt', 'ueberfaellig'];

    protected $fillable = [
        'household_id', 'fiscal_year_id', 'fee_category_id', 'amount',
        'status', 'teilbetrag', 'kommentar', 'bezahlt_bis_fiscal_year_id', 'faellig_am', 'bezahlt_am',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'teilbetrag' => 'decimal:2',
        'faellig_am' => 'date',
        'bezahlt_am' => 'date',
    ];

    /** The household due covering the member in the fiscal year, if any (at most one, unique in the database). */
    public static function coveringFor(int $memberId, int $fiscalYearId): ?self
    {
        return self::query()
            ->whereIn('id', fn ($q) => $q->select('household_due_id')->from('household_due_member')
                ->where('member_id', $memberId)->where('fiscal_year_id', $fiscalYearId))
            ->with('household.members', 'fiscalYear')
            ->first();
    }

    /** "2026 über Haushalt Familie Muster abgedeckt": the refusal for an individual due of a covered member. */
    public function coveredMessage(): string
    {
        return __('members::admin.dues.covered_by_household', ['year' => $this->fiscalYear->name, 'household' => $this->household->label()]);
    }

    /** @return BelongsTo<MemberHousehold, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(MemberHousehold::class, 'household_id');
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

    /**
     * Members covered by this due; the pivot repeats fiscal_year_id so a member can be covered at most
     * once per fiscal year (unique in the database).
     *
     * @return BelongsToMany<Member, $this>
     */
    public function coveredMembers(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'household_due_member', 'household_due_id', 'member_id')
            ->withPivot('fiscal_year_id')
            ->withTimestamps();
    }

    /** @return BelongsToMany<Payment, $this> */
    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'household_due_payment', 'household_due_id', 'payment_id')
            ->withTimestamps();
    }
}
