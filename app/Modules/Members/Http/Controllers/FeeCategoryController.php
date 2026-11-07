<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Beitragskategorien: list of all categories with their rates per fiscal year, the rate matrix for one
 * fiscal year, and an edit page per category (name plus one rate per fiscal year).
 *
 * The category id is a plain route parameter (no implicit model binding), so the route's
 * `can:admin.members` runs before the id is looked up: users without the privilege get 403 for every id.
 */
class FeeCategoryController
{
    /** Largest value of fee_category_rates.amount, a decimal(8,2) column. */
    private const MAX_AMOUNT = '999999.99';

    public function index(Request $request): View
    {
        $fiscalYears = FiscalYear::query()->orderBy('starts_on')->get();
        $categories = FeeCategory::query()->with('rates')->orderBy('name')->get();

        $requestedYear = $request->query('fiscal_year_id');
        $selectedFiscalYearId = is_string($requestedYear) && ctype_digit($requestedYear)
            ? (int) $requestedYear
            : (int) ($fiscalYears->last()->id ?? 0);

        $rates = FeeCategoryRate::query()
            ->where('fiscal_year_id', $selectedFiscalYearId)
            ->get()
            ->keyBy('fee_category_id');

        return view('members::fee-categories.index', [
            'fiscalYears' => $fiscalYears,
            'categories' => $categories,
            'rates' => $rates,
            'selectedFiscalYearId' => $selectedFiscalYearId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'billing_unit' => ['nullable', 'string', Rule::in(FeeCategory::BILLING_UNITS)],
        ]);

        FeeCategory::create(['name' => $validated['name'], 'billing_unit' => $validated['billing_unit'] ?? 'member']);

        return redirect()->route('members.fee-categories.index');
    }

    /** True while a member with this category is connected (as Anschlussmitglied or as Vollzahler of one). */
    private static function usedByConnection(FeeCategory $category): bool
    {
        return Member::query()->where('fee_category_id', $category->id)
            ->where(fn ($q) => $q->whereNotNull('connected_to_member_id')->orWhereHas('anschlussMembers'))
            ->exists();
    }

    public function storeRates(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'fiscal_year_id' => ['required', 'integer', 'exists:fiscal_years,id'],
            'rates' => ['required', 'array', $this->keysExistIn('fee_categories')],
            'rates.*' => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_AMOUNT],
        ]);

        $fiscalYearId = (int) $validated['fiscal_year_id'];

        $fiscalYear = FiscalYear::query()->findOrFail($fiscalYearId);
        if ($fiscalYear->isClosed()) {
            foreach ($validated['rates'] as $categoryId => $amount) {
                if ($this->changesRate((int) $categoryId, $fiscalYearId, $amount)) {
                    throw ValidationException::withMessages(['fiscal_year_id' => $fiscalYear->lockedMessage()]);
                }
            }
        }

        DB::transaction(function () use ($validated, $fiscalYearId): void {
            // Again with the year row locked (year first): a close since validation still wins.
            $closed = FiscalYear::lockForWrite($fiscalYearId);
            if ($closed !== null) {
                foreach ($validated['rates'] as $categoryId => $amount) {
                    if ($this->changesRate((int) $categoryId, $fiscalYearId, $amount)) {
                        throw ValidationException::withMessages(['fiscal_year_id' => $closed->lockedMessage()]);
                    }
                }
            }

            foreach ($validated['rates'] as $categoryId => $amount) {
                $this->saveRate((int) $categoryId, $fiscalYearId, $amount);
            }
        });

        return redirect()->route('members.fee-categories.index', ['fiscal_year_id' => $fiscalYearId]);
    }

    public function edit(string $feeCategory): View
    {
        $category = FeeCategory::query()->findOrFail((int) $feeCategory);

        return view('members::fee-categories.edit', [
            'category' => $category,
            'fiscalYears' => FiscalYear::query()->orderBy('starts_on')->get(),
            'rates' => $category->rates()->get()->keyBy('fiscal_year_id'),
        ]);
    }

    public function update(Request $request, string $feeCategory): RedirectResponse
    {
        $category = FeeCategory::query()->findOrFail((int) $feeCategory);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            // Missing = keep the current unit (older forms and tests post only name and rates).
            'billing_unit' => ['nullable', 'string', Rule::in(FeeCategory::BILLING_UNITS)],
            'connection_role' => ['nullable', 'string', Rule::in(FeeCategory::CONNECTION_ROLES)],
            'rates' => ['nullable', 'array', $this->keysExistIn('fiscal_years')],
            'rates.*' => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_AMOUNT],
        ]);

        $rates = $validated['rates'] ?? [];
        $this->refuseClosedYearRates($category, $rates);
        $unit = $validated['billing_unit'] ?? $category->billing_unit;
        // Missing field = keep the role; an empty value removes it.
        $role = $request->exists('connection_role') ? ($validated['connection_role'] ?? null) : $category->connection_role;

        DB::transaction(function () use ($category, $validated, $rates, $unit, $role): void {
            // Again with the year rows locked (years first): a close since validation still wins.
            FiscalYear::lockForWrite(array_keys($rates));
            $this->refuseClosedYearRates($category, $rates);

            $category = FeeCategory::query()->lockForUpdate()->findOrFail($category->id);
            if ($category->billingUnitLockedFor($unit)) {
                throw ValidationException::withMessages(['billing_unit' => __('members::admin.fee_categories.billing_unit_locked')]);
            }

            if ($role !== $category->connection_role && self::usedByConnection($category)) {
                throw ValidationException::withMessages(['connection_role' => __('members::admin.connection.role_locked')]);
            }

            $category->update(['name' => $validated['name'], 'billing_unit' => $unit, 'connection_role' => $role]);

            foreach ($validated['rates'] ?? [] as $fiscalYearId => $amount) {
                $this->saveRate($category->id, (int) $fiscalYearId, $amount);
            }
        });

        return redirect()->route('members.fee-categories.index')
            ->with('status', __('members::admin.fee_categories.saved'));
    }

    /** Deactivates a category (no deletion): its dues, rates and members stay as they are. */
    public function deactivate(string $feeCategory): RedirectResponse
    {
        $category = FeeCategory::query()->findOrFail((int) $feeCategory);
        $category->deactivate();

        return back(fallback: route('members.fee-categories.index'))
            ->with('status', __('members::admin.fee_categories.deactivated_success', ['name' => $category->name]));
    }

    public function reactivate(string $feeCategory): RedirectResponse
    {
        $category = FeeCategory::query()->findOrFail((int) $feeCategory);
        $category->reactivate();

        return back(fallback: route('members.fee-categories.index'))
            ->with('status', __('members::admin.fee_categories.reactivated_success', ['name' => $category->name]));
    }

    /**
     * Rates of a closed business year are locked; sending back the unchanged stored value is fine.
     *
     * @param  array<int|string, mixed>  $rates  amounts keyed by fiscal year id
     */
    private function refuseClosedYearRates(FeeCategory $category, array $rates): void
    {
        if ($rates === []) {
            return;
        }

        $closedYears = FiscalYear::query()->whereKey(array_map(intval(...), array_keys($rates)))
            ->whereNotNull('abgeschlossen_am')->get()->keyBy('id');
        $lockErrors = [];
        foreach ($rates as $fiscalYearId => $amount) {
            $year = $closedYears->get((int) $fiscalYearId);
            if ($year !== null && $this->changesRate($category->id, (int) $fiscalYearId, $amount)) {
                $lockErrors['rates.'.$fiscalYearId] = $year->lockedMessage();
            }
        }
        if ($lockErrors !== []) {
            throw ValidationException::withMessages($lockErrors);
        }
    }

    /** Whether saving $amount would create or change the stored rate (an empty amount never does). */
    private function changesRate(int $categoryId, int $fiscalYearId, mixed $amount): bool
    {
        if ($amount === null || $amount === '') {
            return false;
        }

        $stored = FeeCategoryRate::query()
            ->where('fee_category_id', $categoryId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->value('amount');

        return $stored === null || (int) round((float) $stored * 100) !== (int) round((float) $amount * 100);
    }

    /** Creates or changes one rate; an empty amount leaves the stored rate as it is. */
    private function saveRate(int $categoryId, int $fiscalYearId, mixed $amount): void
    {
        if ($amount === null || $amount === '') {
            return;
        }

        FeeCategoryRate::updateOrCreate(
            ['fee_category_id' => $categoryId, 'fiscal_year_id' => $fiscalYearId],
            ['amount' => $amount]
        );
    }

    /**
     * Rule for an array keyed by ids: every key must be the positive integer id of a row in $table.
     */
    private function keysExistIn(string $table): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($table): void {
            if (! is_array($value) || $value === []) {
                return;
            }

            $ids = [];
            foreach (array_keys($value) as $key) {
                if (! is_int($key) && ! ctype_digit($key)) {
                    $fail(__('validation.exists', ['attribute' => $attribute]));

                    return;
                }
                $ids[(int) $key] = true;
            }

            if (DB::table($table)->whereIn('id', array_keys($ids))->count() !== count($ids)) {
                $fail(__('validation.exists', ['attribute' => $attribute]));
            }
        };
    }
}
