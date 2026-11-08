<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Models\User;
use App\Modules\Members\Models\ClubRole;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberChange;
use App\Modules\Members\Models\MemberHousehold;
use App\Modules\Members\Models\Payment;
use App\Modules\Members\Support\HouseholdService;
use App\Modules\Members\Support\MemberChangeLogger;
use App\Modules\Members\Support\MemberConnectionService;
use App\Modules\Members\Support\MemberConnectionWarnings;
use App\Modules\Members\Support\MemberDepartureAccounts;
use App\Modules\Members\Support\MemberListFilter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MemberController
{
    private const ANONYMIZE_CONFIRMATION = 'ANONYMISIEREN';

    public function index(Request $request): View
    {
        $filter = MemberListFilter::fromRequest($request);

        $fiscalYears = FiscalYear::query()->orderBy('starts_on')->get();
        // The "Beitrag <year>" column shows the due of the current fiscal year (the one containing today, else the latest).
        $currentFiscalYear = $fiscalYears->first(
            fn (FiscalYear $fiscalYear) => now()->between($fiscalYear->starts_on, $fiscalYear->ends_on),
        ) ?? $fiscalYears->last();

        $query = Member::query()->with([
            'feeCategory',
            'dues' => fn ($dues) => $dues->where('fiscal_year_id', $currentFiscalYear->id ?? 0)->withExists('payments'),
            // Family fee: the household (chip) and the household due covering the member in that year (read-only status).
            'household.members',
            'coveringHouseholdDues' => fn ($dues) => $dues->wherePivot('fiscal_year_id', $currentFiscalYear->id ?? 0)->withExists('payments')->with('household.members'),
        ]);
        $filter->apply($query);

        $members = $query->orderBy('lastname')->orderBy('firstname')->orderBy('id')
            ->paginate(25)
            ->appends($filter->query());

        return view('members::members.index', [
            'members' => $members,
            'filter' => $filter,
            'feeCategories' => FeeCategory::query()->perMember()->orderBy('name')->get(),
            'fiscalYears' => $fiscalYears,
            'currentFiscalYear' => $currentFiscalYear,
            'departureOverview' => app(MemberDepartureAccounts::class)->overview(),
        ]);
    }

    public function create(): View
    {
        return view('members::members.create', [
            'nextNumber' => Member::nextNumber(),
            'feeCategories' => FeeCategory::query()->perMember()->orderBy('name')->get(),
            'clubRoles' => ClubRole::query()->orderByDesc('is_board')->orderBy('name')->get(),
            'users' => User::query()->orderBy('alias')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'member_number' => ['required', 'integer', 'min:1', 'unique:members,member_number'],
            'nuliga_id' => ['nullable', 'integer', 'min:1', 'unique:members,nuliga_id'],
            'user_id' => ['nullable', 'integer', 'exists:bs_users,uid', 'unique:members,user_id'],
            'firstname' => ['required', 'string', 'max:128'],
            'lastname' => ['required', 'string', 'max:128'],
            'birthdate' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(Member::GENDERS)],
            'email' => ['nullable', 'email', 'max:128'],
            'phone' => ['nullable', 'string', 'max:64'],
            'street' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:128'],
            'fee_category_id' => ['nullable', FeeCategory::assignableRule()],
            'joined_at' => ['required', 'date'],
        ]);

        DB::transaction(function () use ($request, $validated) {
            $member = Member::create($validated);
            $member->clubRoles()->sync($request->input('club_roles', []));
            $this->logChange($member, 'created', null);
        });

        return redirect()->route('members.index');
    }

    public function edit(Member $member): View
    {
        return view('members::members.edit', [
            'member' => $member,
            'feeCategories' => FeeCategory::query()->perMember()->orderBy('name')->get(),
            'clubRoles' => ClubRole::query()->orderByDesc('is_board')->orderBy('name')->get(),
            'fiscalYears' => FiscalYear::query()->orderBy('starts_on')->get(),
            'dues' => $member->dues()->with('fiscalYear', 'feeCategory', 'payments')->get()->sortByDesc(fn ($due) => $due->fiscalYear->starts_on),
            // Household dues covering the member: rows with a "Haushalt" chip in the dues card (status editable like the list).
            'coveringDues' => $member->coveringHouseholdDues()->with('fiscalYear', 'household.members', 'payments')->withExists('payments')->get()
                ->sortByDesc(fn (HouseholdDue $due) => $due->fiscalYear->starts_on->toDateString())->values(),
            // Payments of the member and of household dues covering the member, cancelled ones included.
            'payments' => Payment::query()
                ->where(fn ($q) => $q->where('member_id', $member->id)
                    ->orWhereHas('householdDues', fn ($h) => $h->whereHas('coveredMembers', fn ($m) => $m->whereKey($member->id))))
                ->with(['dues.fiscalYear', 'householdDues.fiscalYear', 'householdDues.household', 'cancelledByUser'])
                ->orderByDesc('paid_at')->orderByDesc('id')->get(),
            'changes' => MemberChange::where('member_id', $member->id)->with('adminUser')->latest()->get(),
            'users' => User::query()->orderBy('alias')->get(),
            ...$this->householdCardData($member),
            'connectionWarnings' => MemberConnectionWarnings::forMember($member),
            'connectionEndedNotice' => MemberConnectionWarnings::endedNotice($member),
            'departureProtected' => app(MemberDepartureAccounts::class)->isProtectedPending($member),
            'departureReactivatable' => app(MemberDepartureAccounts::class)->isAutoDeactivated($member),
            // Possible Vollzahler: any current member that is not connected and has no partner yet (no category check
            // since the club decision of 2026-11-08); the category is shown next to the name.
            'connectionCandidates' => Member::query()
                ->whereKeyNot($member->id)
                ->whereIn('status', ['aktiv', 'ruhend'])->whereNull('anonymized_at')
                ->whereNull('connected_to_member_id')->whereDoesntHave('anschlussMembers')
                ->with('feeCategory')
                ->orderBy('lastname')->orderBy('firstname')->get(),
        ]);
    }

    /**
     * Data of the card "Haushalt · optional": current households for the select (when the member has none),
     * the member's household and the household due covering the member in the current fiscal year.
     *
     * @return array{households: Collection<int, MemberHousehold>, household: MemberHousehold|null, householdDue: HouseholdDue|null, householdYear: FiscalYear|null}
     */
    private function householdCardData(Member $member): array
    {
        $year = app(HouseholdService::class)->currentFiscalYear();
        $household = $member->household()->with('members')->first();

        return [
            'household' => $household,
            'households' => $household === null
                ? MemberHousehold::query()->current()->with('members')->get()->sortBy(fn (MemberHousehold $h) => mb_strtolower($h->label()))->values()
                : collect(),
            'householdDue' => $year !== null
                ? $member->coveringHouseholdDues()->wherePivot('fiscal_year_id', $year->id)->withExists('payments')->with('household.members')->first()
                : null,
            'householdYear' => $year,
        ];
    }

    public function update(Request $request, Member $member): RedirectResponse
    {
        $validated = $request->validate([
            'member_number' => ['required', 'integer', 'min:1', 'unique:members,member_number,'.$member->id],
            'nuliga_id' => ['nullable', 'integer', 'min:1', 'unique:members,nuliga_id,'.$member->id],
            'user_id' => ['nullable', 'integer', 'exists:bs_users,uid', 'unique:members,user_id,'.$member->id],
            'firstname' => ['required', 'string', 'max:128'],
            'lastname' => ['required', 'string', 'max:128'],
            'birthdate' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(Member::GENDERS)],
            'email' => ['nullable', 'email', 'max:128'],
            'phone' => ['nullable', 'string', 'max:64'],
            'street' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:128'],
            'fee_category_id' => ['nullable', FeeCategory::assignableRule($member->fee_category_id)],
            'joined_at' => ['required', 'date'],
            'left_at' => ['nullable', 'date'],
            'notiz' => ['nullable', 'string', 'max:5000'],
        ]);

        $leftAt = $validated['left_at'] ?? null;
        unset($validated['left_at']);

        $before = $member->toArray();
        $departs = $before['left_at'] === null && $leftAt !== null;

        DB::transaction(function () use ($request, $member, $validated, $before, $leftAt, $departs) {
            if ($departs) {
                app(MemberConnectionService::class)->lockForDeparture($member);
            }
            $member->update($validated);
            $member->clubRoles()->sync($request->input('club_roles', []));
            $member->setLeftAt($leftAt);
            $this->logChange($member, $departs ? 'departed' : 'updated', $before);
            if ($departs) {
                app(HouseholdService::class)->memberDeparted($member);
                // Club decision: the Vollzahler departing ends its Anschluss links (logged, shown on the partner's page).
                app(MemberConnectionService::class)->memberDeparted($member);
            }
            // Departure date reached (today or past): the linked account is blocked; a future date waits for the daily run.
            // Only when the departure itself or the account link changed (a plain save must not re-block a kept account).
            $leftChanged = substr((string) ($before['left_at'] ?? ''), 0, 10) !== ($member->left_at?->toDateString() ?? '');
            if ($departs || ($leftChanged && $leftAt !== null) || ($before['user_id'] ?? null) !== $member->user_id) {
                if ($departs) {
                    app(MemberDepartureAccounts::class)->clearReleased($member);
                }
                app(MemberDepartureAccounts::class)->handle($member);
            }
        });

        return redirect()->route('members.index');
    }

    /** Speichert genau ein Feld eines Mitglieds — für die direkt editierbaren Zellen der Liste. */
    public function updateField(Request $request, Member $member): JsonResponse
    {
        $fieldRules = [
            'firstname' => ['required', 'string', 'max:128'],
            'lastname' => ['required', 'string', 'max:128'],
            'email' => ['nullable', 'email', 'max:128'],
            'phone' => ['nullable', 'string', 'max:64'],
            'fee_category_id' => ['nullable', FeeCategory::assignableRule($member->fee_category_id)],
            'joined_at' => ['required', 'date'],
            'left_at' => ['nullable', 'date'],
            'status' => ['required', 'string', Rule::in(['aktiv', 'ruhend'])],
            // Optional link to a booking-system account; null unlinks (the account itself stays).
            'user_id' => ['nullable', 'integer', 'exists:bs_users,uid', Rule::unique('members', 'user_id')->ignore($member->id)],
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

        $lockedWhenAnonymized = ['firstname', 'lastname', 'email', 'phone'];

        if ($member->anonymized_at !== null && in_array($validated['field'], $lockedWhenAnonymized, true)) {
            return response()->json(['message' => __('members::admin.members.anonymize_field_locked')], 422);
        }

        $before = $member->toArray();
        $departs = $validated['field'] === 'left_at' && $before['left_at'] === null && $validated['value'] !== null;

        try {
            DB::transaction(function () use ($member, $validated, $before, $departs) {
                if ($departs) {
                    app(MemberConnectionService::class)->lockForDeparture($member);
                }
                match ($validated['field']) {
                    'left_at' => $member->setLeftAt($validated['value']),
                    'status' => $member->setStatus($validated['value']),
                    default => $member->update([$validated['field'] => $validated['value']]),
                };
                $this->logChange($member, $departs ? 'departed' : 'updated', $before);
                if ($departs) {
                    app(HouseholdService::class)->memberDeparted($member);
                    app(MemberConnectionService::class)->memberDeparted($member);
                }
                // Departure date reached, or a departed member got an account linked: block the account (see MemberDepartureAccounts).
                if (in_array($validated['field'], ['left_at', 'user_id'], true)) {
                    if ($departs) {
                        app(MemberDepartureAccounts::class)->clearReleased($member);
                    }
                    app(MemberDepartureAccounts::class)->handle($member);
                }
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => __('members::admin.members.field_update_failed')], 500);
        }

        return response()->json([
            'value' => match ($validated['field']) {
                'joined_at' => $member->joined_at->format('d.m.Y'),
                'left_at' => $member->left_at?->format('d.m.Y') ?? '—',
                'fee_category_id' => $member->feeCategory->name ?? '—',
                'email' => $member->email ?? '—',
                'phone' => $member->phone ?? '—',
                'user_id' => $member->user->alias ?? '–',
                default => (string) $member->{$validated['field']},
            },
        ]);
    }

    public function anonymize(Request $request, Member $member): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'confirmation' => ['required', 'string'],
        ]);

        $validator->after(function ($validator) use ($request, $member): void {
            $confirmation = $request->input('confirmation');
            if (! is_string($confirmation) || strtoupper(trim($confirmation)) !== self::ANONYMIZE_CONFIRMATION) {
                $validator->errors()->add(
                    'confirmation',
                    __('members::admin.members.anonymize_confirmation_invalid', [
                        'value' => self::ANONYMIZE_CONFIRMATION,
                    ]),
                );

                return;
            }

            if (! $member->isAnonymizable()) {
                $validator->errors()->add(
                    'confirmation',
                    __('members::admin.members.anonymize_not_eligible'),
                );
            }
        });

        if ($validator->fails()) {
            return redirect()->route('members.edit', $member)->withErrors($validator);
        }

        $before = $member->toArray();

        $accountResult = null;
        DB::transaction(function () use ($member, $before, &$accountResult) {
            // The account is blocked while the link still exists (admin and assistant accounts stay as they are).
            $accountResult = app(MemberDepartureAccounts::class)->handle($member, true);
            // Household link and payer go in the same transaction, so the 'anonymized' snapshot records them.
            app(HouseholdService::class)->detachForAnonymisation($member);
            app(MemberConnectionService::class)->detachForAnonymisation($member);
            $member->update([
                'firstname' => 'Ehemaliges',
                'lastname' => "Mitglied #{$member->member_number}",
                'email' => null,
                'phone' => null,
                'street' => null,
                'zip' => null,
                'city' => null,
                'nuliga_id' => null,
                'user_id' => null,
                'anonymized_at' => now(),
            ]);
            $this->logChange($member, 'anonymized', $before);
        });

        // A protected (admin/assistant) account loses its link here and stays active: say so, no other page can show it afterwards.
        $message = __('members::admin.members.anonymize_success')
            .($accountResult === MemberDepartureAccounts::RESULT_PROTECTED ? ' '.__('members::admin.departure.anonymized_protected') : '');

        return redirect()->route('members.edit', $member)->with('success', $message);
    }

    /** One click after a re-entry: enables the account again, but only if this departure logic blocked it. */
    public function reactivateAccount(Member $member): RedirectResponse
    {
        if (! app(MemberDepartureAccounts::class)->reactivate($member)) {
            return back()->withErrors(['account' => __('members::admin.departure.not_reactivatable')]);
        }

        return back()->with('success', __('members::admin.departure.reactivated'));
    }

    /** @param  array<string, mixed>|null  $before */
    private function logChange(Member $member, string $action, ?array $before): void
    {
        MemberChangeLogger::log($member, $action, $before);
    }
}
