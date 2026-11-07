<?php

declare(strict_types=1);

namespace App\Modules\Members\Models;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $billing_unit
 * @property string|null $connection_role
 * @property Carbon|null $deactivated_at
 */
class FeeCategory extends Model
{
    /** 'member': one due per member (default); 'household': one flat due per household and fiscal year. */
    public const BILLING_UNITS = ['member', 'household'];

    // deactivated_at is set only by deactivate()/reactivate(), never mass-assigned.
    /** Roles for the Anschlussmitglied rule: a Vollzahler category may have an Anschlussmitglied with the Anschluss category. */
    public const CONNECTION_ROLES = ['vollzahler', 'anschluss'];

    protected $fillable = ['name', 'billing_unit', 'connection_role'];

    protected $casts = [
        'deactivated_at' => 'datetime',
    ];

    /** @return HasMany<FeeCategoryRate, $this> */
    public function rates(): HasMany
    {
        return $this->hasMany(FeeCategoryRate::class);
    }

    public function isVollzahler(): bool
    {
        return $this->connection_role === 'vollzahler';
    }

    public function isAnschluss(): bool
    {
        return $this->connection_role === 'anschluss';
    }

    /** Whether this category bills households (one flat due per household) instead of members. */
    public function isPerHousehold(): bool
    {
        return $this->billing_unit === 'household';
    }

    /**
     * Categories billed per member (the default; existing categories are all per member).
     *
     * @param  Builder<FeeCategory>  $query
     * @return Builder<FeeCategory>
     */
    public function scopePerMember(Builder $query): Builder
    {
        return $query->where('billing_unit', 'member');
    }

    /**
     * Categories billed per household (family fee).
     *
     * @param  Builder<FeeCategory>  $query
     * @return Builder<FeeCategory>
     */
    public function scopePerHousehold(Builder $query): Builder
    {
        return $query->where('billing_unit', 'household');
    }

    /** A deactivated category keeps its dues, rates and members but cannot be chosen for anything new. */
    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    public function deactivate(): void
    {
        $this->forceFill(['deactivated_at' => now()])->save();
    }

    public function reactivate(): void
    {
        $this->forceFill(['deactivated_at' => null])->save();
    }

    /** Name for selects: "Altkategorie (deaktiviert)" for a deactivated category. */
    public function labelWithState(): string
    {
        return $this->isDeactivated()
            ? __('members::admin.fee_categories.deactivated_option', ['name' => $this->name])
            : $this->name;
    }

    /**
     * @param  Builder<FeeCategory>  $query
     * @return Builder<FeeCategory>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deactivated_at');
    }

    /**
     * Validation rule for choosing a category: it must exist and be active. A deactivated category is
     * accepted only when it equals $keepId (a member keeping the category it already has).
     */
    public static function assignableRule(?int $keepId = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($keepId): void {
            if ($value === null || $value === '') {
                return;
            }

            $category = is_int($value) || (is_string($value) && ctype_digit($value)) ? self::query()->find((int) $value) : null;
            if ($category === null) {
                $fail(__('validation.exists', ['attribute' => $attribute]));

                return;
            }

            if ($category->isDeactivated() && $category->id !== $keepId) {
                $fail(__('members::admin.fee_categories.not_assignable', ['name' => $category->name]));

                return;
            }

            // A flat household fee is never a member's own category or an individual due.
            if ($category->isPerHousehold()) {
                $fail(__('members::admin.fee_categories.not_per_member', ['name' => $category->name]));
            }
        };
    }

    /**
     * Whether switching to $unit would change the meaning of existing data: per member → per household
     * while members or member dues use it; per household → per member while households or household
     * dues use it. Switching to the current unit is never locked.
     */
    public function billingUnitLockedFor(string $unit): bool
    {
        if ($unit === ($this->billing_unit ?? 'member')) {
            return false;
        }

        return $unit === 'household'
            ? Member::query()->where('fee_category_id', $this->id)->exists() || MemberDue::query()->where('fee_category_id', $this->id)->exists()
            : MemberHousehold::query()->where('fee_category_id', $this->id)->exists() || HouseholdDue::query()->where('fee_category_id', $this->id)->exists();
    }
}
