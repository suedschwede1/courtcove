<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberChange;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MemberController
{
    public function index(Request $request): View
    {
        $query = Member::query()->with('feeCategory');

        if ($request->filled('status') && $request->string('status')->value() === 'departed') {
            $query->whereNotNull('left_at');
        } else {
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

        return view('members::members.index', [
            'members' => $query->orderBy('lastname')->orderBy('firstname')->get(),
            'filters' => array_filter(
                $request->only('status', 'search', 'fee_category'),
                fn ($value) => $value !== null && $value !== '',
            ),
            'feeCategories' => FeeCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('members::members.create', [
            'nextNumber' => Member::nextNumber(),
            'feeCategories' => FeeCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'member_number' => ['required', 'integer', 'min:1', 'unique:members,member_number'],
            'nuliga_id' => ['nullable', 'integer', 'min:1', 'unique:members,nuliga_id'],
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

        DB::transaction(function () use ($validated) {
            $member = Member::create($validated);
            $this->logChange($member, 'created', null);
        });

        return redirect()->route('members.index');
    }

    public function edit(Member $member): View
    {
        return view('members::members.edit', [
            'member' => $member,
            'feeCategories' => FeeCategory::query()->orderBy('name')->get(),
            'changes' => MemberChange::where('member_id', $member->id)->with('adminUser')->latest()->get(),
        ]);
    }

    public function update(Request $request, Member $member): RedirectResponse
    {
        $validated = $request->validate([
            'member_number' => ['required', 'integer', 'min:1', 'unique:members,member_number,'.$member->id],
            'nuliga_id' => ['nullable', 'integer', 'min:1', 'unique:members,nuliga_id,'.$member->id],
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

        $before = $member->toArray();

        DB::transaction(function () use ($member, $validated, $before) {
            $member->update($validated);
            $this->logChange($member, 'updated', $before);
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
            'fee_category_id' => ['nullable', 'exists:fee_categories,id'],
            'joined_at' => ['required', 'date'],
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

        $before = $member->toArray();

        try {
            DB::transaction(function () use ($member, $validated, $before) {
                $member->update([$validated['field'] => $validated['value']]);
                $this->logChange($member, 'updated', $before);
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => __('members::admin.members.field_update_failed')], 500);
        }

        return response()->json([
            'value' => match ($validated['field']) {
                'joined_at' => $member->joined_at->format('d.m.Y'),
                'fee_category_id' => $member->feeCategory?->name ?? '—',
                'email' => $member->email ?? '—',
                default => (string) $member->{$validated['field']},
            },
        ]);
    }

    public function destroy(Member $member): RedirectResponse
    {
        $before = $member->toArray();

        DB::transaction(function () use ($member, $before) {
            $member->update(['left_at' => $member->left_at ?? now()->toDateString()]);
            $this->logChange($member, 'departed', $before);
        });

        return redirect()->route('members.index');
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
