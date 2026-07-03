<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Modules\Members\Models\Member;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MemberController
{
    public function index(Request $request): View
    {
        $query = Member::query();

        if ($request->filled('status') && $request->string('status')->value() === 'departed') {
            $query->whereNotNull('left_at');
        } else {
            $query->active();
        }

        $members = $query->orderBy('lastname')->orderBy('firstname')->get();

        return view('members::members.index', [
            'members' => $members,
            'filters' => $request->only('status'),
        ]);
    }

    public function create(): View
    {
        return view('members::members.create', ['nextNumber' => Member::nextNumber()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'member_number' => ['required', 'integer', 'min:1', 'unique:members,member_number'],
            'firstname' => ['required', 'string', 'max:128'],
            'lastname' => ['required', 'string', 'max:128'],
            'birthdate' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:128'],
            'phone' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:255'],
            'joined_at' => ['required', 'date'],
        ]);

        Member::create($validated);

        return redirect()->route('members.index');
    }

    public function edit(Member $member): View
    {
        return view('members::members.edit', ['member' => $member]);
    }

    public function update(Request $request, Member $member): RedirectResponse
    {
        $validated = $request->validate([
            'member_number' => ['required', 'integer', 'min:1', 'unique:members,member_number,'.$member->id],
            'firstname' => ['required', 'string', 'max:128'],
            'lastname' => ['required', 'string', 'max:128'],
            'birthdate' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:128'],
            'phone' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:255'],
            'joined_at' => ['required', 'date'],
        ]);

        $member->update($validated);

        return redirect()->route('members.index');
    }

    public function destroy(Member $member): RedirectResponse
    {
        $member->update(['left_at' => $member->left_at ?? now()->toDateString()]);

        return redirect()->route('members.index');
    }
}
