<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\UserActivated;
use App\Models\User;
use App\Services\QuoteGroups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

final class UserController extends Controller
{
    public function index(Request $request): View
    {
        $searched = $request->hasAny(['q', 'status']);
        $users = collect();

        if ($searched) {
            $query = User::orderBy('alias');

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            } else {
                $query->where('status', '!=', 'deleted');
            }

            if ($request->filled('q')) {
                $q = '%'.$request->string('q')->trim()->value().'%';
                $query->where(fn ($sub) => $sub->where('alias', 'like', $q)->orWhere('email', 'like', $q));
            }

            $users = $query->get();
        }

        return view('admin.users.index', [
            'users'    => $users,
            'searched' => $searched,
            'filters'  => $request->only('q', 'status'),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'privileges' => User::PRIVILEGES,
            'quoteGroups' => QuoteGroups::all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'alias' => ['required', 'string', 'max:128'],
            'email' => ['nullable', 'email', 'max:128', 'unique:bs_users,email'],
            'status' => ['required', 'in:admin,assist,enabled,disabled,blocked,deleted,placeholder'],
            'password' => ['required', 'string', 'min:6'],
            'firstname' => ['nullable', 'string', 'max:128'],
            'lastname' => ['nullable', 'string', 'max:128'],
            'phone' => ['nullable', 'string', 'max:64'],
            'quote_group' => ['nullable', 'string', 'in:'.implode(',', array_keys(QuoteGroups::all()))],
            'privileges' => ['array'],
            'privileges.*' => ['in:'.implode(',', User::PRIVILEGES)],
        ]);

        $user = User::create([
            'alias' => $data['alias'],
            'email' => $data['email'] ?? null,
            'status' => $data['status'],
            'pw' => Hash::make($data['password']),
            'created' => now(),
        ]);

        foreach (['firstname', 'lastname', 'phone', 'quote_group'] as $field) {
            if (! empty($data[$field])) {
                $user->setMeta($field, $data[$field]);
            }
        }
        $user->syncPrivileges($data['privileges'] ?? []);

        if ($data['status'] === 'enabled' && ! empty($data['email'])) {
            Mail::to($data['email'])->queue(new UserActivated($user));
        }

        return redirect()->route('admin.users.index')->with('success', __('booking.messages.user_created'));
    }

    public function edit(User $user): View
    {
        $user->load('meta');

        return view('admin.users.edit', [
            'user' => $user,
            'privileges' => User::PRIVILEGES,
            'quoteGroups' => QuoteGroups::all(),
            'granted' => $user->grantedPrivileges(),
            'profile' => [
                'firstname' => $user->getMeta('firstname'),
                'lastname' => $user->getMeta('lastname'),
                'phone' => $user->getMeta('phone'),
                'quote_group' => $user->getMeta('quote_group'),
            ],
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        // Allow the user's current quote_group even if it's no longer defined
        // in quote_groups.php (renamed/removed group, or the file is gone) —
        // otherwise leaving the field untouched on save would 422 instead of
        // just keeping the stale value.
        $allowedQuoteGroups = array_unique(array_filter(array_merge(
            array_keys(QuoteGroups::all()),
            [$user->getMeta('quote_group')],
        )));

        $data = $request->validate([
            'alias' => ['required', 'string', 'max:128'],
            'email' => ['nullable', 'email', 'max:128', 'unique:bs_users,email,'.$user->uid.',uid'],
            'status' => ['required', 'in:admin,assist,enabled,disabled,blocked,deleted,placeholder'],
            'firstname' => ['nullable', 'string', 'max:128'],
            'lastname' => ['nullable', 'string', 'max:128'],
            'phone' => ['nullable', 'string', 'max:64'],
            'quote_group' => ['nullable', 'string', 'in:'.implode(',', $allowedQuoteGroups)],
            'privileges' => ['array'],
            'privileges.*' => ['in:'.implode(',', User::PRIVILEGES)],
        ]);

        $wasEnabled = $user->status === 'enabled';
        $user->update(['alias' => $data['alias'], 'email' => $data['email'] ?? null, 'status' => $data['status']]);
        foreach (['firstname', 'lastname', 'phone'] as $field) {
            $user->setMeta($field, $data[$field] ?? null);
        }
        // The quote_group field is hidden entirely when no groups are
        // configured and the user has none assigned — don't wipe an
        // existing value just because the field wasn't part of this submit.
        if ($request->has('quote_group')) {
            $user->setMeta('quote_group', $data['quote_group'] ?? null);
        }
        $user->syncPrivileges($data['privileges'] ?? []);

        if (! $wasEnabled && $data['status'] === 'enabled' && ! empty($user->email)) {
            Mail::to($user->email)->queue(new UserActivated($user));
        }

        return redirect()->route('admin.users.index')->with('success', __('booking.messages.user_updated'));
    }

    public function password(Request $request, User $user): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string', 'min:6']]);
        $user->update(['pw' => Hash::make($request->string('password')->value())]);

        return back()->with('success', __('booking.messages.user_password_reset'));
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'uids'   => ['required', 'array', 'min:1'],
            'uids.*' => ['integer'],
            'action' => ['required', 'in:blocked,enabled,disabled'],
        ]);

        $authId = auth()->id();

        User::whereIn('uid', $data['uids'])
            ->where('uid', '!=', $authId)
            ->where('status', '!=', 'admin')
            ->update(['status' => $data['action']]);

        return back()->with('success', count($data['uids']).' Benutzer aktualisiert.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->update(['status' => 'deleted']);

        return redirect()->route('admin.users.index')->with('success', __('booking.messages.user_deleted'));
    }
}
