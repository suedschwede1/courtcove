<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FeeCategoryController
{
    public function index(Request $request): View
    {
        $fiscalYears = FiscalYear::query()->orderBy('starts_on')->get();
        $categories = FeeCategory::query()->orderBy('name')->get();

        $selectedFiscalYearId = (int) $request->query('fiscal_year_id', $fiscalYears->last()->id ?? 0);

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

        return redirect()->route('admin.members.fee-categories.index');
    }

    public function storeRates(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'fiscal_year_id' => ['required', 'exists:fiscal_years,id'],
            'rates' => ['required', 'array'],
            'rates.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        foreach ($validated['rates'] as $categoryId => $amount) {
            if ($amount === null || $amount === '') {
                continue;
            }

            FeeCategoryRate::updateOrCreate(
                ['fee_category_id' => $categoryId, 'fiscal_year_id' => $validated['fiscal_year_id']],
                ['amount' => $amount]
            );
        }

        return redirect()->route('admin.members.fee-categories.index', ['fiscal_year_id' => $validated['fiscal_year_id']]);
    }
}
