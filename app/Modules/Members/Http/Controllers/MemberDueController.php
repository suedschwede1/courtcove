<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MemberDueController
{
    public function index(Request $request): View
    {
        $fiscalYears = FiscalYear::query()->orderBy('starts_on')->get();
        $currentFiscalYear = $fiscalYears->first(
            fn (FiscalYear $fiscalYear) => now()->between($fiscalYear->starts_on, $fiscalYear->ends_on),
        );
        $defaultFiscalYearId = $currentFiscalYear->id ?? $fiscalYears->last()->id ?? 0;
        $selectedFiscalYearId = (int) $request->query('fiscal_year_id', $defaultFiscalYearId);

        $dues = MemberDue::query()
            ->with(['member', 'feeCategory', 'payments'])
            ->where('fiscal_year_id', $selectedFiscalYearId)
            ->get();

        return view('members::dues.index', [
            'fiscalYears' => $fiscalYears,
            'selectedFiscalYearId' => $selectedFiscalYearId,
            'dues' => $dues,
        ]);
    }

    public function create(): View
    {
        return view('members::dues.create', [
            'members' => Member::query()->active()->orderBy('lastname')->orderBy('firstname')->get(),
            'fiscalYears' => FiscalYear::query()->orderBy('starts_on')->get(),
            'feeCategories' => FeeCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'member_id' => [
                'required',
                'exists:members,id',
                Rule::unique('member_dues', 'member_id')
                    ->where('fiscal_year_id', (int) $request->input('fiscal_year_id')),
            ],
            'fiscal_year_id' => ['required', 'exists:fiscal_years,id'],
            'fee_category_id' => ['required', 'exists:fee_categories,id'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
        ]);

        $fiscalYear = FiscalYear::findOrFail($validated['fiscal_year_id']);

        MemberDue::create([
            ...$validated,
            'status' => 'ausstehend',
            'faellig_am' => $fiscalYear->starts_on,
        ]);

        return redirect()->route('members.dues.index', ['fiscal_year_id' => $validated['fiscal_year_id']])
            ->with('status', __('members::admin.dues.created'));
    }

    public function generate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'fiscal_year_id' => ['required', 'exists:fiscal_years,id'],
        ]);
        $fiscalYearId = (int) $validated['fiscal_year_id'];
        $newFiscalYear = FiscalYear::findOrFail($fiscalYearId);

        $existingMemberIds = MemberDue::where('fiscal_year_id', $fiscalYearId)->pluck('member_id');

        $created = 0;

        Member::query()
            ->active()
            ->whereNotIn('id', $existingMemberIds)
            ->each(function (Member $member) use ($fiscalYearId, $newFiscalYear, &$created) {
                // "Last year's category" assumes fiscal years are created in chronological
                // order, so the highest fiscal_year_id is the most recent one.
                $lastDue = $member->dues()->latest('fiscal_year_id')->first();

                if (! $lastDue) {
                    return;
                }

                $rate = FeeCategoryRate::where('fee_category_id', $lastDue->fee_category_id)
                    ->where('fiscal_year_id', $fiscalYearId)
                    ->first();

                if (! $rate) {
                    return;
                }

                MemberDue::create([
                    'member_id' => $member->id,
                    'fiscal_year_id' => $fiscalYearId,
                    'fee_category_id' => $lastDue->fee_category_id,
                    'amount' => $rate->amount,
                    'status' => 'ausstehend',
                    'faellig_am' => $newFiscalYear->starts_on,
                ]);

                $created++;
            });

        return redirect()->route('members.dues.index', ['fiscal_year_id' => $fiscalYearId])
            ->with('status', __('members::admin.dues.generated', ['count' => $created]));
    }

    /** Speichert genau ein Feld eines Beitrags — für die direkt editierbaren Zellen der Liste. */
    public function updateField(Request $request, MemberDue $due): JsonResponse
    {
        $fieldRules = [
            'status' => ['required', 'string', Rule::in(MemberDue::STATUSES)],
            'teilbetrag' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'kommentar' => ['nullable', 'string', 'max:1000'],
            'faellig_am' => ['nullable', 'date'],
        ];

        $field = $request->string('field')->value();

        try {
            $validated = $request->validate([
                'field' => ['required', 'string', Rule::in(array_keys($fieldRules))],
                'value' => $fieldRules[$field] ?? ['prohibited'],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->validator->errors()->first()], 422);
        }

        try {
            $due->update([$validated['field'] => $validated['value']]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => __('members::admin.dues.field_update_failed')], 500);
        }

        return response()->json([
            'value' => match ($validated['field']) {
                'faellig_am' => $due->faellig_am?->format('d.m.Y'),
                'teilbetrag' => $due->teilbetrag !== null ? number_format((float) $due->teilbetrag, 2, ',', '.').' €' : '–',
                'kommentar' => $due->kommentar ?: '–',
                default => (string) $due->{$validated['field']},
            },
        ]);
    }
}
