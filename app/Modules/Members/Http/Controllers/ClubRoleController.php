<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Modules\Members\Models\ClubRole;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClubRoleController
{
    public function index(): View
    {
        return view('members::functions.index', [
            'clubRoles' => ClubRole::query()->orderByDesc('is_board')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('members::functions.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'is_board' => ['nullable', 'boolean'],
            'tasks' => ['nullable', 'string'],
        ]);

        ClubRole::create([
            'name' => $validated['name'],
            'is_board' => $request->boolean('is_board'),
            'tasks' => $validated['tasks'] ?? null,
        ]);

        return redirect()->route('members.functions.index');
    }

    public function edit(ClubRole $clubRole): View
    {
        return view('members::functions.edit', ['clubRole' => $clubRole]);
    }

    public function update(Request $request, ClubRole $clubRole): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'is_board' => ['nullable', 'boolean'],
            'tasks' => ['nullable', 'string'],
        ]);

        $clubRole->update([
            'name' => $validated['name'],
            'is_board' => $request->boolean('is_board'),
            'tasks' => $validated['tasks'] ?? null,
        ]);

        return redirect()->route('members.functions.index');
    }

    public function destroy(ClubRole $clubRole): RedirectResponse
    {
        $clubRole->members()->detach();
        $clubRole->delete();

        return redirect()->route('members.functions.index');
    }
}
