<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\MemberHousehold;
use App\Modules\Members\Support\HouseholdService;
use App\Modules\Members\Support\MembersFormat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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

    /** One field of a household due (inline cells); same JSON contract as the member-due endpoint. */
    public function updateField(Request $request, int $householdDue): JsonResponse
    {
        $due = HouseholdDue::query()->with('fiscalYear')->findOrFail($householdDue);
        if ($due->fiscalYear->isClosed()) {
            return response()->json(['message' => $due->fiscalYear->lockedMessage()], 422);
        }

        $fieldRules = [
            'status' => ['required', 'string', Rule::in(HouseholdDue::STATUSES)],
            'teilbetrag' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'kommentar' => ['nullable', 'string', 'max:1000'],
            'bezahlt_am' => ['required', ...MemberDue::paidOnRules()],
        ];
        $field = $request->input('field');
        $field = is_string($field) ? $field : '';

        try {
            $validated = $request->validate([
                'field' => ['required', 'string', Rule::in(array_keys($fieldRules))],
                'value' => $fieldRules[$field] ?? ['prohibited'],
            ], MemberDue::paidOnMessages('value'));
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->validator->errors()->first()], 422);
        }

        if ($validated['field'] === 'bezahlt_am' && $due->status !== 'bezahlt') {
            return response()->json(['message' => __('members::admin.dues.bezahlt_am_requires_paid')], 422);
        }

        $changes = [$validated['field'] => $validated['value']];
        if ($validated['field'] === 'status') {
            $changes['bezahlt_am'] = $validated['value'] === 'bezahlt' ? ($due->bezahlt_am ?? now()->toDateString()) : null;
        }

        // Again with the year row locked (year first): a close since the check above wins.
        $closed = DB::transaction(function () use ($due, $changes): ?FiscalYear {
            $closed = FiscalYear::lockForWrite($due->fiscal_year_id);
            if ($closed === null) {
                $due->update($changes);
            }

            return $closed;
        });
        if ($closed !== null) {
            return response()->json(['message' => $closed->lockedMessage()], 422);
        }

        $response = ['value' => match ($validated['field']) {
            'teilbetrag' => MembersFormat::optionalMoney($due->teilbetrag),
            'kommentar' => $due->kommentar ?: '–',
            'bezahlt_am' => $due->paidOnDisplay(),
            default => (string) $due->{$validated['field']},
        }];
        if (in_array($validated['field'], ['status', 'bezahlt_am'], true)) {
            $response['bezahlt_am'] = $due->bezahlt_am?->toDateString();
            $response['bezahlt_am_display'] = $due->paidOnDisplay();
        }

        return response()->json($response);
    }

    /** Paid in advance up to a later fiscal year: creates or updates the household dues in between. */
    public function markPaidThrough(Request $request, int $householdDue): JsonResponse
    {
        $due = HouseholdDue::query()->with('fiscalYear', 'household')->findOrFail($householdDue);
        if ($due->fiscalYear->isClosed()) {
            return response()->json(['message' => $due->fiscalYear->lockedMessage()], 422);
        }

        try {
            $validated = $request->validate(['fiscal_year_id' => ['required', 'integer', 'exists:fiscal_years,id']]);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->validator->errors()->first()], 422);
        }
        $target = FiscalYear::query()->findOrFail((int) $validated['fiscal_year_id']);
        if ($target->starts_on->lt($due->fiscalYear->starts_on)) {
            return response()->json(['message' => __('members::admin.dues.paid_through_before_own_year')], 422);
        }

        // The due's year and every year up to the target locked first; any closed one refuses all.
        $closed = DB::transaction(function () use ($due, $target): ?FiscalYear {
            $ids = [$due->fiscal_year_id, ...HouseholdService::yearIdsUpTo($due, $target)];
            FiscalYear::lockForWrite($ids);
            $closed = FiscalYear::query()->whereKey($ids)->whereNotNull('abgeschlossen_am')->orderBy('starts_on')->first();
            if ($closed === null) {
                $this->households->payThrough($due, $target, now()->toDateString());
            }

            return $closed;
        });
        if ($closed !== null) {
            return response()->json(['message' => $closed->lockedMessage()], 422);
        }

        return response()->json(['status' => 'bezahlt']);
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
