<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Policies;

use App\Models\User;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedEventGrant;
use App\Modules\MixedTournament\Support\MixedRole;

/**
 * The single place of the role rule (old PermissionService::roleFor()).
 *
 * Order: no user -> none; global admin.mixed -> admin; test event -> admin
 * (this event only, never global); grant row admin/score; otherwise read.
 * Unknown grant roles fail closed to read.
 *
 * Reach it only through the Gate (Gate::forUser($user)->allows('score', $event),
 * authorize(), @can). User::can() is the privilege check and never hits this policy.
 * Create-event, set-default, test mode, grants and the member import need the
 * GLOBAL right (isGlobalAdmin()), not the event-admin ability.
 */
final class MixedEventPolicy
{
    public function role(?User $user, ?MixedEvent $event = null): MixedRole
    {
        if ($user === null) {
            return MixedRole::None;
        }

        if (self::isGlobalAdmin($user)) {
            return MixedRole::Admin;
        }

        if ($event !== null) {
            if ($event->is_test) {
                return MixedRole::Admin;
            }

            $grant = MixedEventGrant::query()
                ->where('event_id', (int) $event->id)
                ->where('uid', (int) $user->uid)
                ->orderBy('id')
                ->first();

            if ($grant !== null) {
                if ($grant->role === 'admin') {
                    return MixedRole::Admin;
                }
                if ($grant->role === 'score') {
                    return MixedRole::Score;
                }
            }
        }

        return MixedRole::Read;
    }

    public function view(User $user, MixedEvent $event): bool
    {
        return $this->role($user, $event)->atLeast(MixedRole::Read);
    }

    public function score(User $user, MixedEvent $event): bool
    {
        return $this->role($user, $event)->atLeast(MixedRole::Score);
    }

    public function administer(User $user, MixedEvent $event): bool
    {
        return $this->role($user, $event) === MixedRole::Admin;
    }

    /** The global admin.mixed privilege (status admin, or assist with the flag). */
    public static function isGlobalAdmin(?User $user): bool
    {
        return $user?->can('admin.mixed') === true;
    }
}
