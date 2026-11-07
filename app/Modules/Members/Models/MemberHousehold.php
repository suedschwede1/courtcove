<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A household (family fee): members billed together with one flat household due per fiscal year
 * when it has a household fee category and at least MIN_BILLABLE_MEMBERS billable members.
 * Households are dissolved, never deleted. payer_member_id and dissolved_at are written only by
 * the household service (forceFill), never mass-assigned.
 *
 * @property int $id
 * @property string|null $name
 * @property int|null $fee_category_id
 * @property int|null $payer_member_id
 * @property Carbon|null $dissolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Member> $members
 * @property-read Member|null $payer
 * @property-read FeeCategory|null $feeCategory
 * @property-read Collection<int, HouseholdDue> $dues
 */
class MemberHousehold extends Model
{
    /** Member statuses that count towards the minimum and are covered: aktiv and ruhend ("unterstützend"). */
    public const BILLABLE_STATUSES = ['aktiv', 'ruhend'];

    /** A household due is created only from this many billable members on. */
    public const MIN_BILLABLE_MEMBERS = 2;

    protected $fillable = ['name', 'fee_category_id'];

    protected $casts = [
        'dissolved_at' => 'datetime',
    ];

    /** @return HasMany<Member, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class, 'household_id');
    }

    /**
     * Members that count for the household due: aktiv or ruhend and not anonymised.
     *
     * @return HasMany<Member, $this>
     */
    public function billableMembers(): HasMany
    {
        return $this->members()
            ->whereIn('status', self::BILLABLE_STATUSES)
            ->whereNull('anonymized_at');
    }

    /** @return BelongsTo<Member, $this> */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'payer_member_id');
    }

    /** @return BelongsTo<FeeCategory, $this> */
    public function feeCategory(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class);
    }

    /** @return HasMany<HouseholdDue, $this> */
    public function dues(): HasMany
    {
        return $this->hasMany(HouseholdDue::class, 'household_id');
    }

    /**
     * Households that are not dissolved.
     *
     * @param  Builder<MemberHousehold>  $query
     * @return Builder<MemberHousehold>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('dissolved_at');
    }

    public function isDissolved(): bool
    {
        return $this->dissolved_at !== null;
    }

    /**
     * Display name: the given name, else the members' unique last names sorted and joined with
     * " / " ("Beispiel / Muster"), else "Haushalt #12". Without a name no extra personal data is stored.
     */
    public function label(): string
    {
        if ($this->name !== null && $this->name !== '') {
            return $this->name;
        }

        $lastNames = $this->members
            ->pluck('lastname')
            ->filter(fn (mixed $name): bool => is_string($name) && trim($name) !== '')
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        return $lastNames !== []
            ? implode(' / ', $lastNames)
            : __('members::admin.households.unnamed', ['id' => $this->id]);
    }
}
