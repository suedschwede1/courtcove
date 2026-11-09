<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Support\BulkDuePayer;
use App\Modules\Members\Support\DuesGenerationReport;
use App\Modules\Members\Support\DuesGenerator;
use App\Modules\Members\Support\HouseholdService;
use App\Modules\Members\Support\MembersFormat;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
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

        $search = $request->query('search');
        // Capped like the bulk payment's validation, so "Alle der Filterung" never fails on a long search.
        $search = is_string($search) ? mb_substr(trim($search), 0, 100) : '';

        // No status parameter (or an unusable one) means the default "offen"; "alle" must be
        // chosen explicitly and then travels in the query string like every other value.
        $statusFilter = $request->query('status');
        $statusFilter = is_string($statusFilter) && in_array($statusFilter, self::STATUS_FILTERS, true)
            ? $statusFilter
            : self::DEFAULT_STATUS_FILTER;

        [$duesQuery, $householdQuery] = $this->filteredQueries($selectedFiscalYearId, $statusFilter, $search);

        // "Alle der aktuellen Filterung": what a bulk payment of the whole filter would pay (open rows of an open
        // year), for the confirmation dialog; the server resolves the filter again on submit.
        $payableMember = (clone $duesQuery)->open()->get(['member_dues.id', 'member_dues.amount']);
        $payableHousehold = (clone $householdQuery)->open()->get(['household_dues.id', 'household_dues.amount']);
        $yearOpen = $selectedFiscalYear !== null && ! $selectedFiscalYear->isClosed();
        $payableCount = $yearOpen ? $payableMember->count() + $payableHousehold->count() : 0;
        $payableSum = $yearOpen ? (float) $payableMember->sum('amount') + (float) $payableHousehold->sum('amount') : 0.0;

        $duesQuery->with(['member', 'feeCategory', 'payments', 'fiscalYear', 'bezahltBisFiscalYear']);
        $householdQuery->with(['household.members', 'coveredMembers' => fn ($q) => $q->orderBy('firstname'), 'payments', 'fiscalYear']);

        $outstandingTotal = (clone $duesQuery)->get(['member_dues.*'])
            ->sum(fn (MemberDue $due): float => (float) $due->outstandingAmount());

        $householdDues = $householdQuery->orderBy('id')->get()
            ->sortBy(fn (HouseholdDue $due) => mb_strtolower($due->household->label()), SORT_NATURAL)->values();
        if ($householdDues->isNotEmpty()) {
            $outstandingTotal += $householdDues->sum(fn (HouseholdDue $due): float => (float) $due->outstandingAmount());
        }

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
            'duesReport' => DuesGenerationReport::fromArray($request->session()->get('dues_report')),
            'yearHasDues' => $yearHasDues,
            'dues' => $dues,
            'outstandingTotal' => $outstandingTotal,
            'payableCount' => $payableCount,
            'payableSum' => number_format($payableSum, 2, '.', ''),
            'bulkPayReport' => $request->session()->get('bulk_pay_report'),
            'householdDues' => $householdDues,
            'filters' => array_filter(
                ['search' => $search, 'status' => $statusFilter],
                fn ($value) => $value !== '',
            ),            'members' => Member::query()->active()->orderBy('lastname')->orderBy('firstname')->get(),
            // Only active categories can be chosen for a new due.
            'feeCategories' => FeeCategory::query()->active()->perMember()->orderBy('name')->get(),
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
                // Never an own due on top of household coverage in the same year (no double billing).
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    $yearId = $request->input('fiscal_year_id');
                    if (is_scalar($value) && ctype_digit((string) $value) && is_scalar($yearId) && ctype_digit((string) $yearId)) {
                        $covering = HouseholdDue::coveringFor((int) $value, (int) $yearId);
                        if ($covering !== null) {
                            $fail($covering->coveredMessage());
                        }
                    }
                },
            ],
            'fiscal_year_id' => ['required', 'exists:fiscal_years,id', $this->openYearRule()],
            'fee_category_id' => ['required', FeeCategory::assignableRule()],
            'amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'teilbetrag' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'kommentar' => ['nullable', 'string', 'max:1000'],
        ]);

        $fiscalYear = FiscalYear::findOrFail($validated['fiscal_year_id']);

        DB::transaction(function () use ($validated, $fiscalYear): void {
            // Again with the year row locked (year first): a close since validation still wins.
            FiscalYear::lockOpenOrFail($fiscalYear->id, 'fiscal_year_id');
            $covering = HouseholdDue::coveringFor((int) $validated['member_id'], $fiscalYear->id);
            if ($covering !== null) {
                throw ValidationException::withMessages(['member_id' => $covering->coveredMessage()]);
            }

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
        });

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

        // DuesGenerator runs in one transaction with the year row locked first: a close since validation
        // refuses the whole run, and a close requested meanwhile waits until the dues are committed.
        $report = app(DuesGenerator::class)->generate($newFiscalYear, $request->boolean('preview'));

        $message = __('members::admin.dues.generated', ['count' => $report->total()]);
        // With no rate at all, say why nothing happened.
        if ($report->total() === 0 && ! FeeCategoryRate::where('fiscal_year_id', $fiscalYearId)->exists()) {
            $message .= ' '.__('members::admin.dues.no_rates_for_year', ['year' => $newFiscalYear->name]);
        }
        $skippedDeactivated = count($report->skipped['deactivated_category']);
        if ($skippedDeactivated > 0) {
            $message .= ' '.__('members::admin.dues.generate_skipped_deactivated', ['count' => $skippedDeactivated]);
        }

        return redirect()->route('members.dues.index', ['fiscal_year_id' => $fiscalYearId])
            ->with('status', $message)
            ->with('dues_report', $report->toArray())
            ->with('dues_report_preview', $request->boolean('preview'));
    }

    /**
     * The dues list filter (year, status, search) for member dues and household dues, without eager loads.
     * Shared by the list and by the bulk payment, which resolves "all of the current filter" again on the server.
     *
     * @return array{0: Builder<MemberDue>, 1: Builder<HouseholdDue>}
     */
    private function filteredQueries(int $fiscalYearId, string $statusFilter, string $search): array
    {
        $duesQuery = MemberDue::query()->where('member_dues.fiscal_year_id', $fiscalYearId);
        if ($search !== '') {
            $duesQuery->whereHas('member', function ($q) use ($search) {
                $q->where('firstname', 'like', "%{$search}%")
                    ->orWhere('lastname', 'like', "%{$search}%");
            });
        }
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

        // Household dues of the year: few rows, not paginated, same search (household name or a covered
        // member) and status filter; each counts once.
        $householdQuery = HouseholdDue::query()->where('household_dues.fiscal_year_id', $fiscalYearId);
        if ($search !== '') {
            $householdQuery->where(function ($q) use ($search): void {
                $q->whereHas('household', fn ($h) => $h->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('coveredMembers', fn ($m) => $m->where('firstname', 'like', "%{$search}%")->orWhere('lastname', 'like', "%{$search}%"))
                    ->orWhereHas('household.members', fn ($m) => $m->where('lastname', 'like', "%{$search}%"));
            });
        }
        match ($statusFilter) {
            'offen' => $householdQuery->open(),
            'bezahlt' => $householdQuery->where('status', 'bezahlt'),
            'ausstehend' => $householdQuery->where('status', 'ausstehend')->whereDoesntHave('payments')
                ->where(fn ($q) => $q->whereNull('faellig_am')->orWhere('faellig_am', '>=', now()->toDateString())),
            'ueberfaellig' => $householdQuery->overdue()->whereDoesntHave('payments'),
            default => null,
        };

        return [$duesQuery, $householdQuery];
    }

    /**
     * Bulk "Als bezahlt markieren": one real payment per due (see BulkDuePayer), for the ticked rows of the listed
     * year (mode "selected") or for every row of the current filter (mode "filter", resolved here again; client
     * ids are ignored). More than BulkDuePayer::LIMIT rows are refused. The result (paid, skipped with reasons)
     * comes back as flash and as a report shown above the list.
     */
    public function bulkPay(Request $request, BulkDuePayer $payer): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'string', Rule::in(['selected', 'filter'])],
            'fiscal_year_id' => ['required', 'integer', 'exists:fiscal_years,id'],
            'due_ids' => ['nullable', 'array', 'max:'.BulkDuePayer::LIMIT],
            'due_ids.*' => ['integer'],
            'household_due_ids' => ['nullable', 'array', 'max:'.BulkDuePayer::LIMIT],
            'household_due_ids.*' => ['integer'],
            'status' => ['nullable', 'string', Rule::in(self::STATUS_FILTERS)],
            'search' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', ...MemberDue::paidOnRules()],
            'note' => ['nullable', 'string', 'max:255'],
        ], MemberDue::paidOnMessages('paid_at'));

        $fiscalYearId = (int) $validated['fiscal_year_id'];
        if ($validated['mode'] === 'filter') {
            [$duesQuery, $householdQuery] = $this->filteredQueries($fiscalYearId, $validated['status'] ?? self::DEFAULT_STATUS_FILTER, trim((string) ($validated['search'] ?? '')));
            // Only rows that can still be paid: "Alle der Filterung" means the open dues of the filter (as counted in
            // the list), so paid rows of a status "alle"/"bezahlt" filter neither use up the limit nor flood the report.
            $duesQuery->open();
            $householdQuery->open();
            $memberDueIds = $duesQuery->orderBy('member_dues.member_id')->pluck('member_dues.id')->map(intval(...))->all();
            $householdDueIds = $householdQuery->orderBy('household_dues.id')->pluck('household_dues.id')->map(intval(...))->all();
        } else {
            $memberDueIds = array_map(intval(...), $validated['due_ids'] ?? []);
            $householdDueIds = array_map(intval(...), $validated['household_due_ids'] ?? []);
        }

        $total = count(array_unique($memberDueIds)) + count(array_unique($householdDueIds));
        if ($total === 0) {
            throw ValidationException::withMessages(['bulk' => __('members::admin.dues.bulk_nothing_selected')]);
        }
        if ($total > BulkDuePayer::LIMIT) {
            throw ValidationException::withMessages(['bulk' => __('members::admin.dues.bulk_too_many', ['count' => $total, 'limit' => BulkDuePayer::LIMIT])]);
        }

        // Up to LIMIT short transactions: give a shorter web time limit room, never shorten an unlimited one.
        $limit = (int) ini_get('max_execution_time');
        if ($limit > 0 && $limit < 120 && function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $result = $payer->pay($fiscalYearId, $memberDueIds, $householdDueIds, $validated['paid_at'] ?? now()->toDateString(), $validated['note'] ?? null);

        return redirect()->back(fallback: route('members.dues.index', ['fiscal_year_id' => $fiscalYearId]))
            ->with('status', __('members::admin.dues.bulk_pay_result', ['paid' => $result['paid'], 'skipped' => count($result['skipped'])]))
            ->with('bulk_pay_report', $result);
    }

    /** Markiert alle ausgewählten Beitragszeilen (Mitglieds- und Haushaltsbeiträge) in einem Schritt als bezahlt, optional bis zu einem gewählten Fiskaljahr im Voraus. */
    public function bulkMarkPaid(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'due_ids' => ['required_without:household_due_ids', 'array', 'min:1'],
            'due_ids.*' => ['integer', 'exists:member_dues,id'],
            'household_due_ids' => ['nullable', 'array', 'min:1'],
            'household_due_ids.*' => ['integer', 'exists:household_dues,id'],
            'bezahlt_bis_fiscal_year_id' => ['nullable', 'exists:fiscal_years,id'],
            'fiscal_year_id' => ['nullable', 'exists:fiscal_years,id'],
            'bezahlt_am' => ['nullable', ...MemberDue::paidOnRules()],
        ], MemberDue::paidOnMessages('bezahlt_am'));

        $targetFiscalYearId = $validated['bezahlt_bis_fiscal_year_id'] ?? null;
        $paidOn = $validated['bezahlt_am'] ?? now()->toDateString();
        $dues = MemberDue::with('fiscalYear')->whereIn('id', $validated['due_ids'] ?? [])->get();
        $householdDues = HouseholdDue::with('fiscalYear', 'household')->whereIn('id', $validated['household_due_ids'] ?? [])->get();
        $count = 0;

        // A closed year refuses the whole batch (nothing is half-applied).
        $closedDue = $dues->first(fn (MemberDue $due) => $due->fiscalYear->isClosed())
            ?? $householdDues->first(fn (HouseholdDue $due) => $due->fiscalYear->isClosed());
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
            foreach ($householdDues as $due) {
                $closedYear = FiscalYear::query()->whereKey(HouseholdService::yearIdsUpTo($due, $target))->whereNotNull('abgeschlossen_am')->orderBy('starts_on')->first();
                if ($closedYear !== null) {
                    throw ValidationException::withMessages(['bezahlt_bis_fiscal_year_id' => $closedYear->lockedMessage()]);
                }
            }
        }

        DB::transaction(function () use ($dues, $householdDues, $targetFiscalYearId, $paidOn, &$count): void {
            // Both checks again with every touched year row locked (years first), so a year
            // closed since validation refuses the whole batch.
            $ownYearIds = $dues->pluck('fiscal_year_id')->merge($householdDues->pluck('fiscal_year_id'))
                ->map(fn ($id): int => (int) $id)->unique()->values()->all();
            $rangeYearIds = [];
            $target = $targetFiscalYearId !== null ? FiscalYear::findOrFail($targetFiscalYearId) : null;
            if ($target !== null) {
                foreach ($dues as $due) {
                    $rangeYearIds = [...$rangeYearIds, ...$this->yearIdsUpTo($due, $target)];
                }
                foreach ($householdDues as $due) {
                    $rangeYearIds = [...$rangeYearIds, ...HouseholdService::yearIdsUpTo($due, $target)];
                }
            }
            FiscalYear::lockForWrite([...$ownYearIds, ...$rangeYearIds]);
            $closed = FiscalYear::query()->whereKey([...$ownYearIds, ...$rangeYearIds])->whereNotNull('abgeschlossen_am')
                ->orderBy('starts_on')->get();
            $closedOwn = $closed->first(fn (FiscalYear $year): bool => in_array($year->id, $ownYearIds, true));
            if ($closedOwn !== null) {
                throw ValidationException::withMessages(['due_ids' => $closedOwn->lockedMessage()]);
            }
            if ($closed->isNotEmpty()) {
                throw ValidationException::withMessages(['bezahlt_bis_fiscal_year_id' => $closed->first()->lockedMessage()]);
            }

            foreach ($dues as $due) {
                if ($target === null) {
                    $due->update(['status' => 'bezahlt', 'bezahlt_am' => $due->bezahlt_am ?? $paidOn]);
                    $count++;

                    continue;
                }

                // Skip rows whose own fiscal year is already after the chosen "bezahlt bis"
                // year instead of failing the whole batch — the other selected rows are
                // still valid.
                if ($target->starts_on->lt($due->fiscalYear->starts_on)) {
                    continue;
                }

                $this->applyPaidThrough($due, (int) $targetFiscalYearId, $paidOn);
                $count++;
            }

            foreach ($householdDues as $due) {
                if ($target === null) {
                    $due->update(['status' => 'bezahlt', 'bezahlt_am' => $due->bezahlt_am ?? $paidOn]);
                    $count++;

                    continue;
                }
                if ($target->starts_on->lt($due->fiscalYear->starts_on)) {
                    continue;
                }

                app(HouseholdService::class)->payThrough($due, $target, $paidOn);
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

        $closed = null;
        try {
            // Again with the year row locked (year first): a close since the check above wins.
            DB::transaction(function () use ($due, $changes, &$closed): void {
                $closed = FiscalYear::lockForWrite($due->fiscal_year_id);
                if ($closed === null) {
                    $due->update($changes);
                }
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => __('members::admin.dues.field_update_failed')], 500);
        }
        if ($closed !== null) {
            return response()->json(['message' => $closed->lockedMessage()], 422);
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

        // A linked payment is never touched by a status change: say so when a payment-linked due is set to not paid.
        if ($validated['field'] === 'status' && $validated['value'] !== 'bezahlt') {
            $hint = $due->linkedPaymentText('members::admin.dues.reset_payment_hint');
            if ($hint !== null) {
                $response['warning'] = $hint;
            }
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

        // Again with the due's year and every year up to the target locked (years first).
        $closedYear = DB::transaction(function () use ($due, $targetFiscalYearId): ?FiscalYear {
            $ids = [$due->fiscal_year_id, ...$this->yearIdsUpTo($due, FiscalYear::findOrFail($targetFiscalYearId))];
            FiscalYear::lockForWrite($ids);
            $closed = FiscalYear::query()->whereKey($ids)->whereNotNull('abgeschlossen_am')->orderBy('starts_on')->first();
            if ($closed === null) {
                $this->applyPaidThrough($due, $targetFiscalYearId, now()->toDateString());
            }

            return $closed;
        });
        if ($closedYear !== null) {
            return response()->json(['message' => $closedYear->lockedMessage()], 422);
        }

        return response()->json(['status' => 'bezahlt']);
    }

    /**
     * Ids of the years after the due's own year up to and including $target ("Bezahlt bis").
     *
     * @return list<int>
     */
    private function yearIdsUpTo(MemberDue $due, FiscalYear $target): array
    {
        return FiscalYear::query()
            ->where('starts_on', '>', $due->fiscalYear->starts_on)
            ->where('starts_on', '<=', $target->starts_on)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
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
            // A year in which a household due covers the member is paid through the household: no own due there.
            if (HouseholdDue::coveringFor($due->member_id, $fiscalYear->id) !== null) {
                continue;
            }

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
