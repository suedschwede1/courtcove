<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Modules\Members\Models\FiscalYear;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FiscalYearController
{
    public function index(): View
    {
        return view('members::fiscal-years.index', [
            'fiscalYears' => FiscalYear::query()->orderBy('starts_on')->get(),
        ]);
    }

    public function create(): View
    {
        return view('members::fiscal-years.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:32'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
        ]);

        FiscalYear::create($validated);

        return redirect()->route('members.fiscal-years.index');
    }

    public function edit(FiscalYear $fiscalYear): View
    {
        return view('members::fiscal-years.edit', ['fiscalYear' => $fiscalYear]);
    }

    public function update(Request $request, FiscalYear $fiscalYear): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:32'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
        ]);

        $fiscalYear->update($validated);

        return redirect()->route('members.fiscal-years.index');
    }
}
