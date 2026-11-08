<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\UserActivated;
use App\Models\Booking;
use App\Models\LoginHistory;
use App\Models\User;
use App\Models\UserMeta;
use App\Modules\Members\Models\Member;
use App\Services\QuoteGroups;
use App\Services\TrustedDeviceService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class UserController extends Controller
{
    /** Rows per page of the admin user list. */
    public const PER_PAGE = 50;

    /** Query parameters of the list filter, in form order. */
    private const FILTER_KEYS = ['q', 'status', 'role', 'from', 'to', 'no_booking', 'placeholders'];

    public function index(Request $request): View
    {
        $searched = $request->hasAny(self::FILTER_KEYS);
        [$filters, $filterErrors, $dateInputs] = self::listFilters($request);
        $users = collect();

        if ($searched) {
            // The pager links carry the effective filter only (dropped invalid values stay dropped).
            $users = self::listQuery($filters)
                ->paginate(self::PER_PAGE)
                ->appends(array_filter($filters, static fn (string $value): bool => $value !== ''));
        }

        $view = [
            'users' => $users,
            'riskScores' => LoginHistory::riskScores($users->pluck('uid')->all()),
            'searched' => $searched,
            'filters' => $filters,
            'dateInputs' => $dateInputs,
            'placeholdersHidden' => $filters['status'] === '' && $filters['placeholders'] === '',
        ];
        if ($filterErrors->isNotEmpty()) {
            // A GET filter must not redirect (it would land on the same URL again): the errors go to
            // the layout's error banner and under the date fields of this request.
            $view['errors'] = (new ViewErrorBag)->put('default', $filterErrors);
        }

        return view('admin.users.index', $view);
    }

    /**
     * The list filter read from the query string. Invalid values (garbage, array-shaped
     * input, 'bis' before 'von') are dropped and reported, never passed on to the query.
     * "Ohne Buchung" wins over the date range: with it set, von/bis are ignored.
     * Returns the effective filter, one message per dropped field and the values for the
     * date inputs (they keep well-formed dates whose order was wrong, so they can be fixed).
     *
     * @return array{0: array<string, string>, 1: MessageBag, 2: array{from: string, to: string}}
     */
    private static function listFilters(Request $request): array
    {
        $failed = Validator::make($request->only(self::FILTER_KEYS), [
            'q' => ['nullable', 'string', 'max:128'],
            'status' => ['nullable', 'string', Rule::in(User::STATUSES)],
            'role' => ['nullable', 'string', Rule::in(User::ROLES)],
            'from' => ['nullable', 'string', 'date_format:Y-m-d'],
            'to' => ['nullable', 'string', 'date_format:Y-m-d'],
            'no_booking' => ['nullable', 'string', 'in:1'],
            'placeholders' => ['nullable', 'string', 'in:1'],
        ])->errors();

        $errors = new MessageBag;
        $filters = [];
        foreach (self::FILTER_KEYS as $key) {
            $value = $request->input($key);
            if ($failed->has($key)) {
                $errors->add($key, __(in_array($key, ['from', 'to'], true) ? 'booking.admin.users.filter_date_invalid' : 'booking.admin.users.filter_invalid'));
            }
            $filters[$key] = ! $failed->has($key) && is_string($value) ? trim($value) : '';
        }
        $dateInputs = ['from' => $filters['from'], 'to' => $filters['to']];

        if ($filters['no_booking'] !== '') {
            $filters['from'] = $filters['to'] = $dateInputs['from'] = $dateInputs['to'] = '';
        } elseif ($filters['from'] !== '' && $filters['to'] !== '' && $filters['to'] < $filters['from']) {
            $errors->add('to', __('booking.admin.users.filter_date_order'));
            $filters['from'] = $filters['to'] = '';
        }

        return [$filters, $errors, $dateInputs];
    }

    /**
     * The admin user list: one query with the last booking date joined in (the same
     * grouped subquery feeds the column and the date filter, so a plain WHERE on the
     * joined value works) and the booking count as a subselect: a page costs a fixed
     * number of queries.
     *
     * @param  array<string, string>  $filters  Validated, see listFilters()
     * @return Builder<User>
     */
    private static function listQuery(array $filters): Builder
    {
        $query = User::query()
            ->select('bs_users.*')
            ->leftJoinSub(self::lastBookingDates(), 'last_booking', 'last_booking.uid', '=', 'bs_users.uid')
            ->addSelect('last_booking.last_booking_date')
            ->withCount('bookings')
            ->orderBy('bs_users.alias')
            ->orderBy('bs_users.uid');

        if ($filters['status'] !== '') {
            $query->withStatus($filters['status']);
        } else {
            // Placeholders stay hidden unless the status filter or "Platzhalter anzeigen" asks for them.
            $query->whereNotIn('bs_users.status', $filters['placeholders'] !== '' ? ['deleted'] : ['deleted', 'placeholder']);
        }

        if ($filters['role'] !== '') {
            $query->withRole($filters['role']);
        }

        if ($filters['q'] !== '') {
            $q = '%'.$filters['q'].'%';
            $query->where(fn ($sub) => $sub->where('bs_users.alias', 'like', $q)->orWhere('bs_users.email', 'like', $q));
        }

        if ($filters['no_booking'] !== '') {
            $query->whereNull('last_booking.last_booking_date');
        } else {
            if ($filters['from'] !== '') {
                $query->where('last_booking.last_booking_date', '>=', $filters['from']);
            }
            if ($filters['to'] !== '') {
                $query->where('last_booking.last_booking_date', '<=', $filters['to']);
            }
        }

        return $query;
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
            // Members are separate from user accounts; show the optionally linked member.
            'linkedMember' => Member::query()->where('user_id', $user->getKey())->orderBy('member_number')->first(['id', 'member_number']),
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
        $emailBefore = (string) $user->email;
        $rightsBefore = [$user->effectiveRole(), $user->accountStatus(), $user->grantedPrivileges()];
        $user->update(['alias' => $data['alias'], 'email' => $data['email'] ?? null, ...self::statusRoleColumns($data['status'], $role)]);
        // An explicit "blocked" from the form is a manual block: the departure marker no longer applies.
        if ($data['status'] === 'blocked') {
            $user->meta()->whereIn('key', [User::META_DEPARTURE_DEACTIVATED, User::META_DEPARTURE_RELEASED])->delete();
            $user->unsetRelation('meta');
        }
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

        // A change of role, account state or single rights ends the trust in this account's devices.
        $user->unsetRelation('meta');
        $privilegesAfter = $user->grantedPrivileges();
        sort($privilegesAfter);
        $privilegesBefore = $rightsBefore[2];
        sort($privilegesBefore);
        $emailChanged = Str::lower((string) $user->email) !== Str::lower($emailBefore);
        if ($emailChanged || $rightsBefore[0] !== $user->effectiveRole() || $rightsBefore[1] !== $user->accountStatus() || $privilegesBefore !== $privilegesAfter) {
            app(TrustedDeviceService::class)->revokeAll($user, true, $this->ownSessionId($request, $user));
        }

        if (! $wasEnabled && $data['status'] === 'enabled' && ! empty($user->email)) {
            Mail::to($user->email)->queue(new UserActivated($user));
        }

        return redirect()->route('admin.users.index')->with('success', __('booking.messages.user_updated'));
    }

    public function password(Request $request, User $user): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string', 'min:6']]);
        $user->update(['pw' => Hash::make($request->string('password')->value())]);
        app(TrustedDeviceService::class)->revokeAll($user, true, $this->ownSessionId($request, $user));

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

        // Mass updates fire no model events: an account that leaves the blocked state loses its departure marker here.
        if ($data['action'] !== 'blocked') {
            UserMeta::query()->whereIn('uid', $data['uids'])->where('key', User::META_DEPARTURE_DEACTIVATED)->update(['key' => User::META_DEPARTURE_RELEASED]);
        }

        foreach ($data['uids'] as $uid) {
            app(TrustedDeviceService::class)->revokeAll((int) $uid, true);
        }

        return back()->with('success', count($data['uids']).' Benutzer aktualisiert.');
    }

    /** The signed-in admin keeps their own session when they edit themselves. */
    private function ownSessionId(Request $request, User $user): ?string
    {
        return (int) $request->user()?->getKey() === (int) $user->getKey() ? $request->session()->getId() : null;
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $user->update(['status' => 'deleted']);
        app(TrustedDeviceService::class)->revokeAll($user, true, $this->ownSessionId($request, $user));

        return redirect()->route('admin.users.index')->with('success', __('booking.messages.user_deleted'));
    }
}
