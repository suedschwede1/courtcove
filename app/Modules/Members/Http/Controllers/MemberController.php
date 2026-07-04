<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\Member;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'editing' => $request->integer('edit') ?: null,
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

        Member::create($validated);

        return redirect()->route('members.index');
    }

    public function edit(Member $member): View
    {
        return view('members::members.edit', [
            'member' => $member,
            'feeCategories' => FeeCategory::query()->orderBy('name')->get(),
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

        $member->update($validated);

        $filters = array_filter(
            (array) $request->input('filters', []),
            fn ($value) => $value !== null && $value !== '',
        );

        return redirect()->route('members.index', $filters);
    }

    public function destroy(Member $member): RedirectResponse
    {
        $member->update(['left_at' => $member->left_at ?? now()->toDateString()]);

        return redirect()->route('members.index');
    }
}
