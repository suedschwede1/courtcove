<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Models\User;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\MemberDue;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
        $validated = $this->validateYear($request, null);

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

        $validated = $this->validateYear($request, $fiscalYear);

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

    /**
     * Year form rules. Dates are plain Y-m-d strings (the date inputs), so array or malformed
     * input is a validation error and never reaches a date comparison. The end may equal the
     * start but not lie before it, and the range must not overlap any other year (closed years
     * included); the edited year itself is ignored.
     *
     * @return array{name: string, starts_on: string, ends_on: string, faellig_am?: string|null}
     */
    private function validateYear(Request $request, ?FiscalYear $current): array
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:32'],
            'starts_on' => ['bail', 'required', 'string', 'date_format:Y-m-d'],
            'ends_on' => ['bail', 'required', 'string', 'date_format:Y-m-d'],
            'faellig_am' => ['nullable', 'date'],
        ]);

        $validator->after(function (\Illuminate\Validation\Validator $validator) use ($current): void {
            if ($validator->errors()->hasAny(['starts_on', 'ends_on'])) {
                return;
            }

            $start = (string) $validator->getValue('starts_on');
            $end = (string) $validator->getValue('ends_on');

            if ($end < $start) {
                $validator->errors()->add('ends_on', __('members::admin.fiscal_years.ends_before_start'));

                return;
            }

            $other = FiscalYear::firstOverlapping($start, $end, $current?->id);
            if ($other !== null) {
                $validator->errors()->add('starts_on', __('members::admin.fiscal_years.overlap', [
                    'year' => $other->name,
                    'starts_on' => $other->starts_on->isoFormat('L'),
                    'ends_on' => $other->ends_on->isoFormat('L'),
                ]));
            }
        });

        /** @var array{name: string, starts_on: string, ends_on: string, faellig_am?: string|null} */
        return $validator->validate();
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
