<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Support\MembersFormat;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $duesQuery = MemberDue::query()
            ->with(['member', 'feeCategory', 'payments', 'fiscalYear', 'bezahltBisFiscalYear'])
            ->where('fiscal_year_id', $selectedFiscalYearId);

        $search = trim($request->string('search')->value());
        if ($search !== '') {
            $duesQuery->whereHas('member', function ($q) use ($search) {
                $q->where('firstname', 'like', "%{$search}%")
                    ->orWhere('lastname', 'like', "%{$search}%");
            });
        }

        $statusFilter = $request->string('status')->value();
        if (in_array($statusFilter, MemberDue::STATUSES, true)) {
            match ($statusFilter) {
                'bezahlt' => $duesQuery->where('status', 'bezahlt'),
                'ausstehend' => $duesQuery->where('status', 'ausstehend')
                    ->where(fn ($q) => $q->whereNull('faellig_am')->orWhere('faellig_am', '>=', now()->toDateString())),
                'ueberfaellig' => $duesQuery->overdue(),
            };
        }

        $dues = $duesQuery->orderBy('member_id')->paginate(25)->withQueryString();

        $generateFiscalYears = $fiscalYears->filter(
            fn (FiscalYear $fiscalYear) => $fiscalYear->ends_on->toDateString() >= now()->toDateString(),
        )->values();

        return view('members::dues.index', [
            'fiscalYears' => $fiscalYears,
            'selectedFiscalYearId' => $selectedFiscalYearId,
            'defaultFiscalYearId' => $defaultFiscalYearId,
            'generateFiscalYears' => $generateFiscalYears,
            'dues' => $dues,
            'filters' => array_filter(
                $request->only('search', 'status'),
                fn ($value) => $value !== null && $value !== '',
            ),
            'members' => Member::query()->active()->orderBy('lastname')->orderBy('firstname')->get(),
            'feeCategories' => FeeCategory::query()->orderBy('name')->get(),
            'feeCategoryRates' => FeeCategoryRate::where('fiscal_year_id', $selectedFiscalYearId)->pluck('amount', 'fee_category_id'),
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
            'amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'teilbetrag' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'kommentar' => ['nullable', 'string', 'max:1000'],
        ]);

        $fiscalYear = FiscalYear::findOrFail($validated['fiscal_year_id']);

        if (($validated['amount'] ?? null) === null) {
            $rate = FeeCategoryRate::where('fee_category_id', $validated['fee_category_id'])
                ->where('fiscal_year_id', $validated['fiscal_year_id'])
                ->first();

            if (! $rate) {
                throw ValidationException::withMessages([
                    'amount' => __('members::admin.dues.no_rate_for_category_year'),
                ]);
            }

            $validated['amount'] = $rate->amount;
        }

        MemberDue::create([
            ...$validated,
            'status' => 'ausstehend',
            'faellig_am' => $fiscalYear->faellig_am ?? $fiscalYear->starts_on,
        ]);

        return redirect()->back(fallback: route('members.dues.index', ['fiscal_year_id' => $validated['fiscal_year_id']]))
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
                    'faellig_am' => $newFiscalYear->faellig_am ?? $newFiscalYear->starts_on,
                ]);

                $created++;
            });

        return redirect()->route('members.dues.index', ['fiscal_year_id' => $fiscalYearId])
            ->with('status', __('members::admin.dues.generated', ['count' => $created]));
    }

    /** Markiert alle ausgewählten Beitragszeilen in einem Schritt als bezahlt, optional bis zu einem gewählten Fiskaljahr im Voraus. */
    public function bulkMarkPaid(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'due_ids' => ['required', 'array', 'min:1'],
            'due_ids.*' => ['integer', 'exists:member_dues,id'],
            'bezahlt_bis_fiscal_year_id' => ['nullable', 'exists:fiscal_years,id'],
            'fiscal_year_id' => ['nullable', 'exists:fiscal_years,id'],
            'bezahlt_am' => ['nullable', ...MemberDue::paidOnRules()],
        ], MemberDue::paidOnMessages('bezahlt_am'));

        $targetFiscalYearId = $validated['bezahlt_bis_fiscal_year_id'] ?? null;
        $paidOn = $validated['bezahlt_am'] ?? now()->toDateString();
        $dues = MemberDue::with('fiscalYear')->whereIn('id', $validated['due_ids'])->get();
        $count = 0;

        DB::transaction(function () use ($dues, $targetFiscalYearId, $paidOn, &$count): void {
            foreach ($dues as $due) {
                if ($targetFiscalYearId === null) {
                    $due->update(['status' => 'bezahlt', 'bezahlt_am' => $due->bezahlt_am ?? $paidOn]);
                    $count++;

                    continue;
                }

                $target = FiscalYear::find($targetFiscalYearId);

                // Skip rows whose own fiscal year is already after the chosen "bezahlt bis"
                // year instead of failing the whole batch — the other selected rows are
                // still valid.
                if ($target === null || $target->starts_on->lt($due->fiscalYear->starts_on)) {
                    continue;
                }

                $this->applyPaidThrough($due, (int) $targetFiscalYearId, $paidOn);
                $count++;
            }
        });

        return redirect()->route('members.dues.index', array_filter([
            'fiscal_year_id' => $validated['fiscal_year_id'] ?? null,
        ]))->with('status', __('members::admin.dues.bulk_marked_paid', ['count' => $count]));
    }

    /** Speichert genau ein Feld eines Beitrags — für die direkt editierbaren Zellen der Liste. */
    public function updateField(Request $request, MemberDue $due): JsonResponse
    {
        $fieldRules = [
            'status' => ['required', 'string', Rule::in(MemberDue::STATUSES)],
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

        // "Bezahlt am" belongs to paid dues only; an open due gets its date by being marked paid.
        if ($validated['field'] === 'bezahlt_am' && $due->status !== 'bezahlt') {
            return response()->json(['message' => __('members::admin.dues.bezahlt_am_requires_paid')], 422);
        }

        $changes = [$validated['field'] => $validated['value']];
        if ($validated['field'] === 'status') {
            // Marking paid by hand defaults the date to today (an existing date is kept);
            // any other status means "not paid", so the date goes away.
            $changes['bezahlt_am'] = $validated['value'] === 'bezahlt'
                ? ($due->bezahlt_am ?? now()->toDateString())
                : null;
        }

        try {
            $due->update($changes);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => __('members::admin.dues.field_update_failed')], 500);
        }

        $response = [
            'value' => match ($validated['field']) {
                'teilbetrag' => MembersFormat::optionalMoney($due->teilbetrag),
                'kommentar' => $due->kommentar ?: '–',
                'bezahlt_am' => $due->paidOnDisplay(),
                default => (string) $due->{$validated['field']},
            },
        ];

        if (in_array($validated['field'], ['status', 'bezahlt_am'], true)) {
            $response['bezahlt_am'] = $due->bezahlt_am?->toDateString();
            $response['bezahlt_am_display'] = $due->paidOnDisplay();
        }

        return response()->json($response);
    }

    /** Markiert eine Beitragszeile als im Voraus bezahlt bis zu einem späteren Fiskaljahr und legt/aktualisiert dazwischenliegende Zeilen entsprechend. */
    public function markPaidThrough(Request $request, MemberDue $due): JsonResponse
    {
        $validated = $request->validate([
            'fiscal_year_id' => [
                'required',
                'exists:fiscal_years,id',
                function (string $attribute, mixed $value, callable $fail) use ($due): void {
                    $target = FiscalYear::find($value);
                    if ($target !== null && $target->starts_on->lt($due->fiscalYear->starts_on)) {
                        $fail(__('members::admin.dues.paid_through_before_own_year'));
                    }
                },
            ],
        ]);

        $targetFiscalYearId = (int) $validated['fiscal_year_id'];

        DB::transaction(fn () => $this->applyPaidThrough($due, $targetFiscalYearId, now()->toDateString()));

        return response()->json(['status' => 'bezahlt']);
    }

    /** Markiert eine Beitragszeile und alle dazwischenliegenden Fiskaljahre bis zum Zieljahr als bezahlt. Muss innerhalb einer Transaktion aufgerufen werden. */
    private function applyPaidThrough(MemberDue $due, int $targetFiscalYearId, string $paidOn): void
    {
        $target = FiscalYear::findOrFail($targetFiscalYearId);

        $intervening = FiscalYear::query()
            ->where('starts_on', '>', $due->fiscalYear->starts_on)
            ->where('starts_on', '<=', $target->starts_on)
            ->orderBy('starts_on')
            ->get();

        foreach ($intervening as $fiscalYear) {
            $existing = MemberDue::where('member_id', $due->member_id)
                ->where('fiscal_year_id', $fiscalYear->id)
                ->first();

            if ($existing !== null) {
                // Only status/bezahlt_bis_fiscal_year_id change on an existing row — its own
                // amount/fee_category_id are left alone (per spec).
                $existing->update([
                    'status' => 'bezahlt',
                    'bezahlt_bis_fiscal_year_id' => $targetFiscalYearId,
                    'bezahlt_am' => $existing->bezahlt_am ?? $paidOn,
                ]);

                continue;
            }

            MemberDue::create([
                'member_id' => $due->member_id,
                'fiscal_year_id' => $fiscalYear->id,
                'fee_category_id' => $due->fee_category_id,
                'amount' => $due->amount,
                'status' => 'bezahlt',
                'bezahlt_bis_fiscal_year_id' => $targetFiscalYearId,
                'faellig_am' => $fiscalYear->faellig_am ?? $fiscalYear->starts_on,
                'bezahlt_am' => $paidOn,
            ]);
        }

        $due->update([
            'status' => 'bezahlt',
            'bezahlt_bis_fiscal_year_id' => $targetFiscalYearId,
            'bezahlt_am' => $due->bezahlt_am ?? $paidOn,
        ]);
    }
}
