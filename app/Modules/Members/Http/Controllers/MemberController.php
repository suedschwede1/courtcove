<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Models\User;
use App\Modules\Members\Models\ClubRole;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberChange;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MemberController
{
    private const ANONYMIZE_CONFIRMATION = 'ANONYMISIEREN';

    public function index(Request $request): View
    {
        $query = Member::query()->with(['feeCategory', 'dues.fiscalYear']);

        $statusFilter = $request->string('status')->value();
        if (in_array($statusFilter, Member::STATUSES, true)) {
            $query->where('status', $statusFilter);
        } elseif ($statusFilter === '') {
            $query->active();
        }

        $search = trim($request->string('search')->value());
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('firstname', 'like', "%{$search}%")
                    ->orWhere('lastname', 'like', "%{$search}%");
            });
        }

        if ($request->filled('fee_category')) {
            $query->where('fee_category_id', $request->integer('fee_category'));
        }

        $zahlungFilter = $request->string('zahlung')->value();
        if (in_array($zahlungFilter, ['bezahlt', 'ausstehend', 'ueberfaellig'], true)) {
            $query->whereHas('dues', function ($dueQuery) use ($zahlungFilter) {
                match ($zahlungFilter) {
                    'bezahlt' => $dueQuery->where('status', 'bezahlt'),
                    'ausstehend' => $dueQuery->where('status', 'ausstehend')
                        ->where(fn ($q) => $q->whereNull('faellig_am')->orWhere('faellig_am', '>=', now()->toDateString())),
                    'ueberfaellig' => $dueQuery->where('status', 'ausstehend')
                        ->whereNotNull('faellig_am')->where('faellig_am', '<', now()->toDateString()),
                };
            });
        }

        return view('members::members.index', [
            'members' => $query->orderBy('lastname')->orderBy('firstname')->paginate(25)->withQueryString(),
            'filters' => array_filter(
                $request->only('status', 'search', 'fee_category', 'zahlung'),
                fn ($value) => $value !== null && $value !== '',
            ),
            'feeCategories' => FeeCategory::query()->orderBy('name')->get(),
            'fiscalYears' => FiscalYear::query()->orderBy('starts_on')->get(),
        ]);
    }

    public function create(): View
    {
        return view('members::members.create', [
            'nextNumber' => Member::nextNumber(),
            'feeCategories' => FeeCategory::query()->orderBy('name')->get(),
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
            'fee_category_id' => ['nullable', 'exists:fee_categories,id'],
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
            'feeCategories' => FeeCategory::query()->orderBy('name')->get(),
            'clubRoles' => ClubRole::query()->orderByDesc('is_board')->orderBy('name')->get(),
            'fiscalYears' => \App\Modules\Members\Models\FiscalYear::query()->orderBy('starts_on')->get(),
            'dues' => $member->dues()->with('fiscalYear', 'feeCategory')->get()->sortByDesc(fn ($due) => $due->fiscalYear->starts_on),
            'changes' => MemberChange::where('member_id', $member->id)->with('adminUser')->latest()->get(),
            'users' => User::query()->orderBy('alias')->get(),
        ]);
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
            'fee_category_id' => ['nullable', 'exists:fee_categories,id'],
            'joined_at' => ['required', 'date'],
            'left_at' => ['nullable', 'date'],
            'notiz' => ['nullable', 'string', 'max:5000'],
        ]);

        $leftAt = $validated['left_at'] ?? null;
        unset($validated['left_at']);

        $before = $member->toArray();
        $departs = $before['left_at'] === null && $leftAt !== null;

        DB::transaction(function () use ($request, $member, $validated, $before, $leftAt, $departs) {
            $member->update($validated);
            $member->clubRoles()->sync($request->input('club_roles', []));
            $member->setLeftAt($leftAt);
            $this->logChange($member, $departs ? 'departed' : 'updated', $before);
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
            'fee_category_id' => ['nullable', 'exists:fee_categories,id'],
            'joined_at' => ['required', 'date'],
            'left_at' => ['nullable', 'date'],
            'status' => ['required', 'string', Rule::in(['aktiv', 'ruhend'])],
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
                match ($validated['field']) {
                    'left_at' => $member->setLeftAt($validated['value']),
                    'status' => $member->setStatus($validated['value']),
                    default => $member->update([$validated['field'] => $validated['value']]),
                };
                $this->logChange($member, $departs ? 'departed' : 'updated', $before);
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => __('members::admin.members.field_update_failed')], 500);
        }

        return response()->json([
            'value' => match ($validated['field']) {
                'joined_at' => $member->joined_at->format('d.m.Y'),
                'left_at' => $member->left_at?->format('d.m.Y') ?? '—',
                'fee_category_id' => $member->feeCategory?->name ?? '—',
                'email' => $member->email ?? '—',
                'phone' => $member->phone ?? '—',
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
            if (strtoupper(trim((string) $request->input('confirmation'))) !== self::ANONYMIZE_CONFIRMATION) {
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

        DB::transaction(function () use ($member, $before) {
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

        return redirect()->route('members.edit', $member)
            ->with('success', __('members::admin.members.anonymize_success'));
    }

    private function logChange(Member $member, string $action, ?array $before): void
    {
        MemberChange::create([
            'member_id' => $member->id,
            'admin_user_id' => auth()->id(),
            'action' => $action,
            'before' => $before,
            'after' => $member->fresh()->toArray(),
        ]);
    }
}
