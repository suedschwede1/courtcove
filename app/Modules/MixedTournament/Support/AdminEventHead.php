<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Support;

use App\Models\User;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedParticipant;
use App\Modules\MixedTournament\Models\MixedRound;
use App\Modules\MixedTournament\Policies\MixedEventPolicy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Data of the shared admin event header (mixed::admin.partials.event-head): counts, the events the viewer
 * administers (switcher) and whether the global actions (set default, new event) are offered.
 */
final class AdminEventHead
{
    /**
     * @param  Collection<int, MixedParticipant>|null  $participants  the event's participants when already loaded
     * @return array{event: MixedEvent, activeCount: int, totalCount: int, roundCount: int, switchEvents: Collection<int, MixedEvent>, isGlobalAdmin: bool}
     */
    public static function data(User $user, MixedEvent $event, ?Collection $participants = null): array
    {
        $participants ??= MixedParticipant::query()->where('event_id', $event->id)->orderBy('id')->get();
        $gate = Gate::forUser($user);
        $switch = MixedEvent::query()->orderBy('id')->get()
            ->filter(static fn (MixedEvent $e): bool => $gate->allows('administer', $e))
            ->values()
            ->toBase();

        return [
            'event' => $event,
            'activeCount' => $participants->where('status', 'active')->count(),
            'totalCount' => $participants->count(),
            'roundCount' => MixedRound::query()->where('event_id', $event->id)->count(),
            'switchEvents' => $switch,
            'isGlobalAdmin' => MixedEventPolicy::isGlobalAdmin($user),
        ];
    }
}
