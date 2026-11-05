<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\UserActivated;
use App\Models\Booking;
use App\Models\LoginHistory;
use App\Models\User;
use App\Services\QuoteGroups;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class UserController extends Controller
{
    public function index(Request $request): View
    {
        $searched = $request->hasAny(['q', 'status', 'role']);
        $users = collect();

        if ($searched) {
            $query = User::query()
                ->select('bs_users.*')
                ->leftJoinSub(self::lastBookingDates(), 'last_booking', 'last_booking.uid', '=', 'bs_users.uid')
                ->addSelect('last_booking.last_booking_date')
                ->withCount('bookings')
                ->orderBy('bs_users.alias');

            $status = $request->string('status')->value();
            if (in_array($status, User::STATUSES, true)) {
                $query->withStatus($status);
            } else {
                $query->where('bs_users.status', '!=', 'deleted');
            }

            $role = $request->string('role')->value();
            if (in_array($role, User::ROLES, true)) {
                $query->withRole($role);
            }

            if ($request->filled('q')) {
                $q = '%'.$request->string('q')->trim()->value().'%';
                $query->where(fn ($sub) => $sub->where('bs_users.alias', 'like', $q)->orWhere('bs_users.email', 'like', $q));
            }

            $users = $query->get();
        }

        return view('admin.users.index', [
            'users' => $users,
            'riskScores' => LoginHistory::riskScores($users->pluck('uid')->all()),
            'searched' => $searched,
            'filters' => $request->only('q', 'status', 'role'),
        ]);
    }

    /**
     * Newest reservation date per user over all bookings that are not cancelled
     * (future ones included), as one grouped subquery joined into the list.
     */
    private static function lastBookingDates(): QueryBuilder
    {
        return DB::table('bs_bookings')
            ->join('bs_reservations', 'bs_reservations.bid', '=', 'bs_bookings.bid')
            ->whereIn('bs_bookings.status', Booking::ACTIVE_STATUSES)
            ->groupBy('bs_bookings.uid')
            ->select('bs_bookings.uid')
            ->selectRaw('MAX(bs_reservations.date) AS last_booking_date');
    }

    /**
     * Rules for the separate status and permission fields; a permission other than
     * member is only accepted on an enabled account.
     *
     * @return array<string, array<int, mixed>>
     */
    private static function statusRoleRules(Request $request): array
    {
        return [
            'status' => ['required', Rule::in(User::STATUSES)],
            'role' => ['nullable', Rule::in(User::ROLES), static function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                if (($value ?? 'member') !== 'member' && $request->input('status') !== 'enabled') {
                    $fail(__('booking.admin.users.role_requires_enabled'));
                }
            }],
        ];
    }

    /**
     * Column values for a status/permission pair in the storage chosen by config
     * users.split_role (merged into status, or status plus role column).
     *
     * @return array<string, string>
     */
    private static function statusRoleColumns(string $status, string $role): array
    {
        $columns = ['status' => User::storedStatus($status, $role)];
        if (User::splitsRole()) {
            $columns['role'] = $role;
        }

        return $columns;
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
            ...self::statusRoleRules($request),
            'password' => ['required', 'string', 'min:6'],
            'firstname' => ['nullable', 'string', 'max:128'],
            'lastname' => ['nullable', 'string', 'max:128'],
            'phone' => ['nullable', 'string', 'max:64'],
            'quote_group' => ['nullable', 'string', 'in:'.implode(',', array_keys(QuoteGroups::all()))],
            'privileges' => ['array'],
            'privileges.*' => ['in:'.implode(',', User::PRIVILEGES)],
        ]);

        $role = $data['role'] ?? 'member';
        $user = User::create([
            'alias' => $data['alias'],
            'email' => $data['email'] ?? null,
            ...self::statusRoleColumns($data['status'], $role),
            'pw' => Hash::make($data['password']),
            'created' => now(),
        ]);

        foreach (['firstname', 'lastname', 'phone', 'quote_group'] as $field) {
            if (! empty($data[$field])) {
                $user->setMeta($field, $data[$field]);
            }
        }
        // Single permissions only mean something for assistants; they are dropped otherwise.
        $user->syncPrivileges($role === 'assist' ? ($data['privileges'] ?? []) : []);

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
            'currentStatus' => $user->accountStatus(),
            'currentRole' => $user->effectiveRole(),
            'riskScore' => LoginHistory::riskScore((int) $user->getKey()),
            'profile' => [
                'firstname' => $user->getMeta('firstname'),
                'lastname' => $user->getMeta('lastname'),
                'phone' => $user->getMeta('phone'),
                'quote_group' => $user->getMeta('quote_group'),
            ],
        ]);
    }

    public function loginHistory(User $user): View
    {
        return view('admin.users.login-history', [
            'user' => $user,
            'riskScore' => LoginHistory::riskScore((int) $user->getKey()),
            'history' => LoginHistory::query()
                ->where('user_id', $user->getKey())
                ->latest('created_at')
                ->paginate(25)
                ->withQueryString(),
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
            ...self::statusRoleRules($request),
            'firstname' => ['nullable', 'string', 'max:128'],
            'lastname' => ['nullable', 'string', 'max:128'],
            'phone' => ['nullable', 'string', 'max:64'],
            'quote_group' => ['nullable', 'string', 'in:'.implode(',', $allowedQuoteGroups)],
            'privileges' => ['array'],
            'privileges.*' => ['in:'.implode(',', User::PRIVILEGES)],
        ]);

        $role = $data['role'] ?? 'member';
        $wasEnabled = $user->isEnabled();
        $user->update(['alias' => $data['alias'], 'email' => $data['email'] ?? null, ...self::statusRoleColumns($data['status'], $role)]);
        foreach (['firstname', 'lastname', 'phone'] as $field) {
            $user->setMeta($field, $data[$field] ?? null);
        }
        // The quote_group field is hidden entirely when no groups are
        // configured and the user has none assigned — don't wipe an
        // existing value just because the field wasn't part of this submit.
        if ($request->has('quote_group')) {
            $user->setMeta('quote_group', $data['quote_group'] ?? null);
        }
        $user->syncPrivileges($role === 'assist' ? ($data['privileges'] ?? []) : []);

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
            'uids' => ['required', 'array', 'min:1'],
            'uids.*' => ['integer'],
            'action' => ['required', 'in:blocked,enabled,disabled'],
        ]);

        $authId = auth()->id();

        // Admins (also a blocked one that keeps its role in split storage) are never
        // changed in bulk; enabling skips accounts that are already enabled, so the
        // merged storage does not turn an assistant into a member.
        User::whereIn('uid', $data['uids'])
            ->where('uid', '!=', $authId)
            ->whereNot(fn (Builder $query) => $query->withRole('admin'))
            ->when(User::splitsRole(), fn (Builder $query) => $query->where('role', '!=', 'admin'))
            ->when($data['action'] === 'enabled', fn (Builder $query) => $query->whereNot(fn (Builder $sub) => $sub->active()))
            ->update(['status' => $data['action']]);

        return back()->with('success', count($data['uids']).' Benutzer aktualisiert.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->update(['status' => 'deleted']);

        return redirect()->route('admin.users.index')->with('success', __('booking.messages.user_deleted'));
    }
}
