<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\MemberHousehold;
use App\Modules\Members\Support\HouseholdService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Household dues (family fee): entered by hand, edited inline and paid through on the dues page. The
 * year row is locked first in every write transaction, so a year closed meanwhile refuses the write.
 */
class HouseholdDueController
{
    public function __construct(private readonly HouseholdService $households) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'household_id' => ['required', 'integer', 'exists:member_households,id'],
            'fiscal_year_id' => ['required', 'integer', 'exists:fiscal_years,id', self::openYearRule()],
            'amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
        ]);

        $householdId = (int) $validated['household_id'];
        $yearId = (int) $validated['fiscal_year_id'];

        DB::transaction(function () use ($householdId, $yearId, $validated): void {
            // Year row first: a close since validation still wins.
            FiscalYear::lockOpenOrFail($yearId, 'fiscal_year_id');
            $household = MemberHousehold::query()->with('feeCategory')->lockForUpdate()->findOrFail($householdId);
            $year = FiscalYear::query()->findOrFail($yearId);

            self::assertBillable($household, $year);
            if (HouseholdDue::query()->where('household_id', $household->id)->where('fiscal_year_id', $year->id)->exists()) {
                throw ValidationException::withMessages(['fiscal_year_id' => __('members::admin.dues.household_due_exists', ['year' => $year->name])]);
            }

            $amount = $validated['amount'] ?? FeeCategoryRate::query()
                ->where('fee_category_id', $household->fee_category_id)->where('fiscal_year_id', $year->id)->value('amount');
            if ($amount === null) {
                throw ValidationException::withMessages(['amount' => __('members::admin.dues.no_rate_for_category_year')]);
            }

            $due = HouseholdDue::create([
                'household_id' => $household->id,
                'fiscal_year_id' => $year->id,
                'fee_category_id' => $household->fee_category_id,
                'amount' => $amount,
                'status' => 'ausstehend',
                'faellig_am' => $year->faellig_am ?? $year->starts_on,
            ]);
            $this->households->coverBillable($due);
        });

        return redirect()->route('members.households.show', $householdId)->with('status', __('members::admin.dues.household_due_created'));
    }

    /**
     * A household can get a household due only while it is not dissolved, has an active per-household
     * tariff and at least MIN_BILLABLE_MEMBERS billable members (decision 2).
     *
     * @throws ValidationException
     */
    public static function assertBillable(MemberHousehold $household, FiscalYear $year): void
    {
        $tariff = $household->feeCategory;
        $message = match (true) {
            $household->isDissolved() => __('members::admin.households.errors.dissolved'),
            $tariff === null || ! $tariff->isPerHousehold() => __('members::admin.households.error_no_tariff'),
            $tariff->isDeactivated() => __('members::admin.fee_categories.not_assignable', ['name' => $tariff->name]),
            $household->billableMembers()->count() < MemberHousehold::MIN_BILLABLE_MEMBERS => __('members::admin.households.error_too_small'),
            default => null,
        };
        if ($message !== null) {
            throw ValidationException::withMessages(['household_id' => $message]);
        }
    }

    /** Validation rule: the fiscal year must not be closed (fast path; re-checked under the lock). */
    private static function openYearRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $year = is_scalar($value) && ctype_digit((string) $value) ? FiscalYear::find((int) $value) : null;
            if ($year !== null && $year->isClosed()) {
                $fail($year->lockedMessage());
            }
        };
    }
}
