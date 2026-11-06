<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Models\User;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\MemberDue;
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
            'faellig_am' => ['nullable', 'date'],
        ]);

        FiscalYear::create($validated);

        return redirect()->route('members.fiscal-years.index');
    }

    public function edit(Request $request, FiscalYear $fiscalYear): View
    {
        return view('members::fiscal-years.edit', [
            'fiscalYear' => $fiscalYear,
            'openDuesCount' => MemberDue::query()->open()->where('fiscal_year_id', $fiscalYear->id)->count(),
            'canReopen' => $this->isAdmin($request),
            'closedBy' => $fiscalYear->abgeschlossen_von !== null ? User::query()->find($fiscalYear->abgeschlossen_von) : null,
        ]);
    }

    public function update(Request $request, FiscalYear $fiscalYear): RedirectResponse
    {
        if ($fiscalYear->isClosed()) {
            return back()->withErrors(['fiscal_year' => $fiscalYear->lockedMessage()]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:32'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'faellig_am' => ['nullable', 'date'],
        ]);

        $fiscalYear->update($validated);

        return redirect()->route('members.fiscal-years.index');
    }

    /**
     * Closes a year. Open dues do not block, but need the explicit "confirm_open_dues" checkbox
     * (works without JavaScript). Deliberately no member change log entry (club decision).
     * The id is a plain parameter so the route's privilege check runs before the lookup.
     */
    public function close(Request $request, string $fiscalYear): RedirectResponse
    {
        $year = $this->findYear($fiscalYear);

        if ($year->isClosed()) {
            return back()->withErrors(['fiscal_year' => __('members::admin.fiscal_years.already_closed', ['year' => $year->name])]);
        }

        $openDues = MemberDue::query()->open()->where('fiscal_year_id', $year->id)->count();
        if ($openDues > 0 && $request->input('confirm_open_dues') !== '1') {
            return back()->withErrors(['confirm_open_dues' => __('members::admin.fiscal_years.close_confirm_required', ['count' => $openDues])]);
        }

        $year->close((int) $request->user()?->getAuthIdentifier());

        return redirect()->route('members.fiscal-years.edit', $year)
            ->with('status', __('members::admin.fiscal_years.closed_success', ['year' => $year->name]));
    }

    /** Reopens a closed year: administrators only, checked before the id is looked up. */
    public function reopen(Request $request, string $fiscalYear): RedirectResponse
    {
        abort_unless($this->isAdmin($request), 403);

        $year = $this->findYear($fiscalYear);

        if (! $year->isClosed()) {
            return back()->withErrors(['fiscal_year' => __('members::admin.fiscal_years.not_closed', ['year' => $year->name])]);
        }

        $year->reopen();

        return redirect()->route('members.fiscal-years.edit', $year)
            ->with('status', __('members::admin.fiscal_years.reopened_success', ['year' => $year->name]));
    }

    private function findYear(string $id): FiscalYear
    {
        abort_unless(ctype_digit($id), 404);

        return FiscalYear::query()->findOrFail((int) $id);
    }

    /** Reopening is reserved for global administrators (not for assistants with the members privilege). */
    private function isAdmin(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof User && $user->effectiveRole() === 'admin';
    }
}
