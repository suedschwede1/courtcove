<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

use App\Models\User;
use App\Models\UserMeta;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberChange;
use App\Services\TrustedDeviceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Departure of a member ends the linked booking account: the account gets the status "blocked" (the same
 * state an admin sets by hand), so the existing hooks do the rest at once: WhatsApp consents are revoked
 * (ConsentService::userUpdated), sign-in is refused, the player-exchange mail and WhatsApp recipients drop it,
 * and trusted devices and sessions end (TrustedDeviceService::revokeAll).
 *
 * Rules (decided 2026-11-08):
 *  - Due when the member is "ausgetreten" and the departure date is today or past; a future date waits (the
 *    daily run `members:deactivate-left-accounts --since=14` and every save re-evaluate; idempotent).
 *  - Never locked out automatically: accounts holding a RAW admin or assistant role or the members right
 *    (User::requiresTrustedDevice, independent of admin mode). That covers the last active admin too, as every
 *    admin account is protected. They show as a warning for the administration instead.
 *  - Only enabled accounts are touched; blocked, waiting, deleted and placeholder accounts stay as they are.
 *  - The account carries a marker (user meta User::META_DEPARTURE_DEACTIVATED = member id) so that "reactivate
 *    after re-entry" is only offered for accounts this service blocked. The marker disappears whenever the
 *    account leaves the blocked state (User model hook), so a later manual block is never mistaken for it.
 *  - No automatic reactivation: re-entry only shows a hint with a one-click "Konto wieder aktivieren".
 */
final class MemberDepartureAccounts
{
    /** Protocol actions (column member_changes.action is 16 characters wide). */
    public const ACTION_OFF = 'account_off';

    public const ACTION_ON = 'account_on';

    public const RESULT_DEACTIVATED = 'deactivated';

    public const RESULT_NOT_DUE = 'not_due';

    public const RESULT_NO_ACCOUNT = 'no_account';

    public const RESULT_INACTIVE = 'inactive';

    public const RESULT_PROTECTED = 'protected';

    /** Days of the "recently deactivated" figure on the dashboard. */
    public const RECENT_DAYS = 30;

    /**
     * Blocks the account of a departed member (see class rules). $ignoreDate skips the date check for the
     * anonymisation path (the member is departed by then); the member must still be "ausgetreten".
     *
     * @return self::RESULT_*
     */
    public function handle(Member $member, bool $ignoreDate = false): string
    {
        return DB::transaction(function () use ($member, $ignoreDate): string {
            $locked = Member::query()->lockForUpdate()->find($member->id);
            if ($locked === null || $locked->user_id === null) {
                return self::RESULT_NO_ACCOUNT;
            }
            if (! $this->isDeparted($locked, $ignoreDate)) {
                return self::RESULT_NOT_DUE;
            }

            $user = User::query()->lockForUpdate()->find($locked->user_id);
            if ($user === null) {
                return self::RESULT_NO_ACCOUNT;
            }
            if (! $user->isEnabled()) {
                return self::RESULT_INACTIVE;
            }
            // An admin lifted an earlier block by hand: leave the account alone until the member departs anew.
            if ($user->getMeta(User::META_DEPARTURE_RELEASED) === (string) $locked->id) {
                return self::RESULT_INACTIVE;
            }
            if ($this->isProtected($user)) {
                return self::RESULT_PROTECTED;
            }

            $before = MemberChangeLogger::snapshot($locked);
            $user->update(['status' => 'blocked']);
            $user->setMeta(User::META_DEPARTURE_DEACTIVATED, (string) $locked->id);
            app(TrustedDeviceService::class)->revokeAll($user, true);
            MemberChangeLogger::log($locked, self::ACTION_OFF, $before, ['account_user_id' => (int) $user->getKey()]);

            return self::RESULT_DEACTIVATED;
        });
    }

    /**
     * Daily and one-off run over every departed member whose account is still enabled. $sinceDays limits it to
     * departures of the last N days (the scheduled run; older ones are left to the manual run), null = all.
     * A dry run only classifies. Ids are member ids.
     *
     * @return array{deactivated: list<int>, would: list<int>, protected: list<int>, failed: list<int>}
     */
    public function run(bool $dryRun, ?int $sinceDays): array
    {
        $report = ['deactivated' => [], 'would' => [], 'protected' => [], 'failed' => []];

        $this->dueQuery($sinceDays)->orderBy('id')->chunkById(100, function (Collection $members) use ($dryRun, &$report): void {
            foreach ($members as $member) {
                if ($dryRun) {
                    if ($this->isProtected($member->user)) {
                        $report['protected'][] = $member->id;
                    } else {
                        $report['would'][] = $member->id;
                    }

                    continue;
                }

                try {
                    $result = $this->handle($member);
                } catch (\Throwable $e) {
                    report($e);
                    $report['failed'][] = $member->id;

                    continue;
                }
                match ($result) {
                    self::RESULT_DEACTIVATED => $report['deactivated'][] = $member->id,
                    self::RESULT_PROTECTED => $report['protected'][] = $member->id,
                    default => null,
                };
            }
        });

        return $report;
    }

    /**
     * Data for the pages: protected accounts of departed members (warning), re-entered members whose account this
     * service blocked (one-click reactivation), the number of departed members whose account is still enabled
     * without protection (not handled yet) and the recently deactivated count.
     *
     * @return array{protected: Collection<int, Member>, reactivatable: Collection<int, Member>, pending: int, recent: int}
     */
    public function overview(): array
    {
        $due = $this->dueQuery(null)->with('user')->orderBy('lastname')->orderBy('firstname')->get();
        $protected = $due->filter(fn (Member $member): bool => $this->isProtected($member->user))->values();

        $markers = UserMeta::query()->where('key', User::META_DEPARTURE_DEACTIVATED)->pluck('value', 'uid');
        $reactivatable = $markers->isEmpty() ? collect() : Member::query()
            ->whereIn('status', ['aktiv', 'ruhend'])
            ->whereIn('user_id', $markers->keys())
            ->whereHas('user', fn (Builder $user) => $user->where($user->qualifyColumn('status'), 'blocked'))
            ->with('user')
            ->orderBy('lastname')->orderBy('firstname')
            ->get()
            ->filter(fn (Member $member): bool => (string) $markers->get($member->user_id) === (string) $member->id)
            ->values();

        return [
            'protected' => $protected,
            'reactivatable' => $reactivatable,
            'pending' => $due->count() - $protected->count(),
            'recent' => MemberChange::query()
                ->where('action', self::ACTION_OFF)
                ->where('created_at', '>=', now()->subDays(self::RECENT_DAYS))
                ->count(),
        ];
    }

    /** The account was blocked by this service for this member and is still blocked (any member status). */
    public function isDeactivatedByDeparture(Member $member): bool
    {
        if ($member->user_id === null) {
            return false;
        }
        $user = User::query()->find($member->user_id);

        return $user !== null
            && $user->accountStatus() === 'blocked'
            && $user->getMeta(User::META_DEPARTURE_DEACTIVATED) === (string) $member->id;
    }

    /** Re-entered member (aktiv or ruhend) whose account this service blocked: reactivation is offered. */
    public function isAutoDeactivated(Member $member): bool
    {
        return in_array($member->status, ['aktiv', 'ruhend'], true) && $this->isDeactivatedByDeparture($member);
    }

    /** Whether a departed member's account is still enabled although protected (page warning). */
    public function isProtectedPending(Member $member): bool
    {
        if ($member->user_id === null || ! $this->isDeparted($member, false)) {
            return false;
        }
        $user = User::query()->find($member->user_id);

        return $user !== null && $user->isEnabled() && $this->isProtected($user);
    }

    /** One-click reactivation after re-entry; false (nothing changed) unless isAutoDeactivated(). */
    public function reactivate(Member $member): bool
    {
        return DB::transaction(function () use ($member): bool {
            $locked = Member::query()->lockForUpdate()->find($member->id);
            if ($locked === null || ! $this->isAutoDeactivated($locked)) {
                return false;
            }

            $before = MemberChangeLogger::snapshot($locked);
            $user = User::query()->lockForUpdate()->findOrFail($locked->user_id);
            $user->update(['status' => 'enabled']);
            $user->setMeta(User::META_DEPARTURE_DEACTIVATED, null);
            $user->setMeta(User::META_DEPARTURE_RELEASED, null);
            MemberChangeLogger::log($locked, self::ACTION_ON, $before, ['account_user_id' => (int) $user->getKey()]);

            return true;
        });
    }

    /** A new departure (date set where there was none) ends the "lifted by hand" exemption of an earlier one. */
    public function clearReleased(Member $member): void
    {
        if ($member->user_id !== null) {
            UserMeta::query()->where('uid', $member->user_id)->where('key', User::META_DEPARTURE_RELEASED)->delete();
        }
    }

    /**
     * Raw admin, assistant or members right: such an account is never blocked automatically. A role column that
     * is set to anything but member also protects (defensive, in case users.split_role is off over split data).
     */
    public function isProtected(User $user): bool
    {
        $user->unsetRelation('meta');

        return $user->requiresTrustedDevice() || ! in_array((string) ($user->getAttributes()['role'] ?? 'member'), ['member', ''], true);
    }

    private function isDeparted(Member $member, bool $ignoreDate): bool
    {
        if ($member->status !== 'ausgetreten') {
            return false;
        }
        if ($ignoreDate) {
            return true;
        }

        return $member->left_at !== null && $member->left_at->toDateString() <= now()->toDateString();
    }

    /**
     * Departed members (date reached) with a linked account that is still enabled.
     *
     * @return Builder<Member>
     */
    private function dueQuery(?int $sinceDays): Builder
    {
        return Member::query()
            ->where('status', 'ausgetreten')
            ->whereNull('anonymized_at')
            ->whereNotNull('user_id')
            ->whereDate('left_at', '<=', now()->toDateString())
            ->when($sinceDays !== null, fn (Builder $q) => $q->whereDate('left_at', '>=', now()->subDays($sinceDays)->toDateString()))
            ->whereHas('user', fn (Builder $user) => $user->scopes(['active'])
                ->whereDoesntHave('meta', fn (Builder $meta) => $meta->where('key', User::META_DEPARTURE_RELEASED)->whereColumn('bs_users_meta.value', 'members.id')))
            ->with('user');
    }
}
