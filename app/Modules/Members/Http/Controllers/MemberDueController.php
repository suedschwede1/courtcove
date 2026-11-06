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
    /** Session key of the business year last shown in the dues list. */
    private const YEAR_SESSION_KEY = 'members.dues.fiscal_year_id';

    /**
     * Status filter values in display order: "offen" = every due that is neither paid nor settled by
     * a payment (outstanding and overdue, future years included), "alle" = no status filter, the
     * others match the displayed status.
     */
    private const STATUS_FILTERS = ['offen', 'alle', 'ausstehend', 'ueberfaellig', 'bezahlt'];

    /** Club decision: the dues list opens with the open dues. */
    private const DEFAULT_STATUS_FILTER = 'offen';

    public function index(Request $request): View
    {
        $fiscalYears = FiscalYear::query()->orderBy('starts_on')->get();
        $defaultFiscalYear = FiscalYear::defaultFor($fiscalYears);

        // The year comes from the query string; without one, the year chosen last in this
        // session is kept, so leaving the list (member page, payment) does not reset it.
        // Anything that is not the id of an existing year falls back to the default with a notice
        // (before, such input silently showed an empty list under the oldest year's name).
        $requestedYear = $request->query('fiscal_year_id');
        $selectedFiscalYear = null;
        $yearNotice = null;
        if ($requestedYear !== null) {
            if (is_string($requestedYear) && ctype_digit($requestedYear)) {
                $selectedFiscalYear = $fiscalYears->firstWhere('id', (int) $requestedYear);
            }
            if ($selectedFiscalYear === null && $defaultFiscalYear !== null) {
                $yearNotice = __('members::admin.dues.year_not_found', ['year' => $defaultFiscalYear->name]);
            }
        } else {
            $remembered = $request->session()->get(self::YEAR_SESSION_KEY);
            $selectedFiscalYear = is_int($remembered) ? $fiscalYears->firstWhere('id', $remembered) : null;
        }
        $selectedFiscalYear ??= $defaultFiscalYear;
        if ($selectedFiscalYear !== null) {
            $request->session()->put(self::YEAR_SESSION_KEY, $selectedFiscalYear->id);
        }
        $selectedFiscalYearId = $selectedFiscalYear->id ?? 0;
        $defaultFiscalYearId = $defaultFiscalYear->id ?? 0;

        $duesQuery = MemberDue::query()
            ->with(['member', 'feeCategory', 'payments', 'fiscalYear', 'bezahltBisFiscalYear'])
            ->where('fiscal_year_id', $selectedFiscalYearId);

        $search = $request->query('search');
        $search = is_string($search) ? trim($search) : '';
        if ($search !== '') {
            $duesQuery->whereHas('member', function ($q) use ($search) {
                $q->where('firstname', 'like', "%{$search}%")
                    ->orWhere('lastname', 'like', "%{$search}%");
            });
        }

        // No status parameter (or an unusable one) means the default "offen"; "alle" must be
        // chosen explicitly and then travels in the query string like every other value.
        $statusFilter = $request->query('status');
        $statusFilter = is_string($statusFilter) && in_array($statusFilter, self::STATUS_FILTERS, true)
            ? $statusFilter
            : self::DEFAULT_STATUS_FILTER;
        // A due with a linked payment is settled whatever its stored status says, so it is never
        // "ausstehend" or "überfällig" here (it still shows under "Alle").
        match ($statusFilter) {
            'offen' => $duesQuery->open(),
            'bezahlt' => $duesQuery->where('status', 'bezahlt'),
            'ausstehend' => $duesQuery->where('status', 'ausstehend')->whereDoesntHave('payments')
                ->where(fn ($q) => $q->whereNull('faellig_am')->orWhere('faellig_am', '>=', now()->toDateString())),
            'ueberfaellig' => $duesQuery->overdue()->whereDoesntHave('payments'),
            default => null,
        };

        $outstandingTotal = (clone $duesQuery)->get(['member_dues.*'])
            ->sum(fn (MemberDue $due): float => (float) $due->outstandingAmount());

        $dues = $duesQuery->orderBy('member_id')->paginate(25)->withQueryString();

        $generateFiscalYears = $fiscalYears->filter(
            fn (FiscalYear $fiscalYear) => $fiscalYear->ends_on->toDateString() >= now()->toDateString() && ! $fiscalYear->isClosed(),
        )->values();
        // The generate select follows the listed year when dues can still be generated for it.
        $generateFiscalYearId = $generateFiscalYears->contains('id', $selectedFiscalYearId)
            ? $selectedFiscalYearId
            : $defaultFiscalYearId;

        $yearHasDues = $dues->isNotEmpty() || MemberDue::where('fiscal_year_id', $selectedFiscalYearId)->exists();

        return view('members::dues.index', [
            'fiscalYears' => $fiscalYears,
            'selectedFiscalYearId' => $selectedFiscalYearId,
            'selectedFiscalYear' => $selectedFiscalYear,
            'defaultFiscalYearId' => $defaultFiscalYearId,
            'generateFiscalYearId' => $generateFiscalYearId,
            'generateFiscalYears' => $generateFiscalYears,
            'yearNotice' => $yearNotice,
            'yearHasDues' => $yearHasDues,
            'dues' => $dues,
            'outstandingTotal' => $outstandingTotal,
            'filters' => array_filter(
                ['search' => $search, 'status' => $statusFilter],
                fn ($value) => $value !== '',
            ),            'members' => Member::query()->active()->orderBy('lastname')->orderBy('firstname')->get(),
            // Only active categories can be chosen for a new due.
            'feeCategories' => FeeCategory::query()->active()->orderBy('name')->get(),
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
            'fiscal_year_id' => ['required', 'exists:fiscal_years,id', $this->openYearRule()],
            'fee_category_id' => ['required', FeeCategory::assignableRule()],
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
            'fiscal_year_id' => ['required', 'exists:fiscal_years,id', $this->openYearRule()],
        ]);
        $fiscalYearId = (int) $validated['fiscal_year_id'];
        $newFiscalYear = FiscalYear::findOrFail($fiscalYearId);

        $existingMemberIds = MemberDue::where('fiscal_year_id', $fiscalYearId)->pluck('member_id');

        $created = 0;
        $skippedDeactivated = 0;
        $deactivatedIds = FeeCategory::query()->whereNotNull('deactivated_at')->pluck('id')->map(fn ($id) => (int) $id)->all();

        Member::query()
            ->active()
            ->whereNotIn('id', $existingMemberIds)
            ->each(function (Member $member) use ($fiscalYearId, $newFiscalYear, $deactivatedIds, &$created, &$skippedDeactivated) {
                // "Last year's category" assumes fiscal years are created in chronological
                // order, so the highest fiscal_year_id is the most recent one.
                $lastDue = $member->dues()->latest('fiscal_year_id')->first();

                if (! $lastDue) {
                    return;
                }

                // A deactivated category is not used for new dues; the member is counted and reported.
                if (in_array((int) $lastDue->fee_category_id, $deactivatedIds, true)) {
                    $skippedDeactivated++;

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

        $message = __('members::admin.dues.generated', ['count' => $created]);
        // Generation silently skips categories without a rate; with no rate at all, say why nothing happened.
        if ($created === 0 && ! FeeCategoryRate::where('fiscal_year_id', $fiscalYearId)->exists()) {
            $message .= ' '.__('members::admin.dues.no_rates_for_year', ['year' => $newFiscalYear->name]);
        }
        if ($skippedDeactivated > 0) {
            $message .= ' '.__('members::admin.dues.generate_skipped_deactivated', ['count' => $skippedDeactivated]);
        }

        return redirect()->route('members.dues.index', ['fiscal_year_id' => $fiscalYearId])
            ->with('status', $message);
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

        // A closed year refuses the whole batch (nothing is half-applied).
        $closedDue = $dues->first(fn (MemberDue $due) => $due->fiscalYear->isClosed());
        if ($closedDue !== null) {
            throw ValidationException::withMessages(['due_ids' => $closedDue->fiscalYear->lockedMessage()]);
        }
        if ($targetFiscalYearId !== null) {
            $target = FiscalYear::findOrFail($targetFiscalYearId);
            foreach ($dues as $due) {
                $closedYear = $this->closedYearUpTo($due, $target);
                if ($closedYear !== null) {
                    throw ValidationException::withMessages(['bezahlt_bis_fiscal_year_id' => $closedYear->lockedMessage()]);
                }
            }
        }

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

        // Back to the list as it was (year, status, search, page); the fallback keeps at least the year.
        return redirect()->back(fallback: route('members.dues.index', array_filter([
            'fiscal_year_id' => $validated['fiscal_year_id'] ?? null,
        ])))->with('status', __('members::admin.dues.bulk_marked_paid', ['count' => $count]));
    }

    /** Speichert genau ein Feld eines Beitrags — für die direkt editierbaren Zellen der Liste. */
    public function updateField(Request $request, MemberDue $due): JsonResponse
    {
        if ($due->fiscalYear->isClosed()) {
            return response()->json(['message' => $due->fiscalYear->lockedMessage()], 422);
        }

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
        if ($due->fiscalYear->isClosed()) {
            return response()->json(['message' => $due->fiscalYear->lockedMessage()], 422);
        }

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

        $closedYear = $this->closedYearUpTo($due, FiscalYear::findOrFail($targetFiscalYearId));
        if ($closedYear !== null) {
            return response()->json(['message' => $closedYear->lockedMessage()], 422);
        }

        DB::transaction(fn () => $this->applyPaidThrough($due, $targetFiscalYearId, now()->toDateString()));

        return response()->json(['status' => 'bezahlt']);
    }

    /** The first closed year among the due's own year and the years up to $target ("Bezahlt bis"), if any. */
    private function closedYearUpTo(MemberDue $due, FiscalYear $target): ?FiscalYear
    {
        if ($due->fiscalYear->isClosed()) {
            return $due->fiscalYear;
        }

        return FiscalYear::query()
            ->where('starts_on', '>', $due->fiscalYear->starts_on)
            ->where('starts_on', '<=', $target->starts_on)
            ->whereNotNull('abgeschlossen_am')
            ->orderBy('starts_on')
            ->first();
    }

    /** Validation rule: the given fiscal year must not be closed. */
    private function openYearRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $year = is_scalar($value) && ctype_digit((string) $value) ? FiscalYear::find((int) $value) : null;
            if ($year !== null && $year->isClosed()) {
                $fail($year->lockedMessage());
            }
        };
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
