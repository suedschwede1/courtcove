<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        ]);

        FeeCategory::create($validated);

        return redirect()->route('members.fee-categories.index');
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
            'rates' => ['nullable', 'array', $this->keysExistIn('fiscal_years')],
            'rates.*' => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_AMOUNT],
        ]);

        // Rates of a closed business year are locked; sending back the unchanged stored value is fine.
        $closedYears = FiscalYear::query()->whereNotNull('abgeschlossen_am')->get()->keyBy('id');
        $lockErrors = [];
        foreach ($validated['rates'] ?? [] as $fiscalYearId => $amount) {
            $year = $closedYears->get((int) $fiscalYearId);
            if ($year !== null && $this->changesRate($category->id, (int) $fiscalYearId, $amount)) {
                $lockErrors['rates.'.$fiscalYearId] = $year->lockedMessage();
            }
        }
        if ($lockErrors !== []) {
            throw ValidationException::withMessages($lockErrors);
        }

        DB::transaction(function () use ($category, $validated): void {
            $category->update(['name' => $validated['name']]);

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
