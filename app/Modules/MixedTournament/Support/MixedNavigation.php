<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Support;

use App\Models\User;
use App\Modules\MixedTournament\Domain\EventService;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedEventGrant;
use App\Modules\MixedTournament\Models\MixedRound;
use App\Modules\TournamentBase\Support\TournamentModuleNavigation;
use App\Support\Navigation\AreaNavBuilder;
use App\Support\Navigation\AreaNavItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Sidebar entries of the Mixed tournament (every page needs a login), for the event of the page or else the default
 * event: Aktuelle Runde and Wertung (as in the mockups), Veranstaltungen, the event as a sub-group (the former event
 * tabs from MixedTabs: Teilnehmer, rounds, Neue Runde, Durchmischung, Konfiguration, by role), Info, Verwaltung
 * (global right admin.mixed or an event grant) and the member import (global right). Event abilities go through the
 * event policy, which asks User::can('admin.mixed').
 */
final class MixedNavigation implements TournamentModuleNavigation
{
    public function key(): string
    {
        return 'mixed';
    }

    public function group(): string
    {
        return 'mixed';
    }

    public function label(): string
    {
        return __('tournament-base::common.nav.mixed');
    }

    public function available(): bool
    {
        return Route::has('mixed.index');
    }

    public function matches(Request $request): bool
    {
        return $request->routeIs('mixed.*');
    }

    public function entries(AreaNavBuilder $nav): array
    {
        if ($nav->user === null) {
            return [];
        }

        $default = app(EventService::class)->defaultEvent();
        $event = $this->currentEvent($nav->request) ?? $default;
        if ($event !== null && ! Gate::forUser($nav->user)->allows('view', $event)) {
            $event = null;
        }

        return [
            ...$this->eventShortcuts($nav, $event),
            // The event list exists only without a default event: otherwise /mixed-tournament opens the default event.
            $default !== null ? null : $nav->item('events', __('tournament-base::common.nav.events'), route('mixed.index'), ['mixed.index']),
            $event === null ? null : $this->eventEntry($nav, $event),
            $nav->item('info', __('tournament-base::common.nav.info'), route('mixed.info'), ['mixed.info']),
        ];
    }

    public function settingsEntries(AreaNavBuilder $nav): array
    {
        if ($nav->user === null) {
            return [];
        }

        return [
            $nav->item('admin', __('tournament-base::common.nav.admin'), route('mixed.admin.index'), ['mixed.admin.index'],
                static fn (?User $user): bool => $user !== null && ($user->can('admin.mixed')
                    || MixedEventGrant::query()->where('uid', (int) $user->uid)->whereIn('role', ['admin', 'score'])->exists())),
            $nav->item('import', __('tournament-base::common.nav.import_members'), route('mixed.admin.import-members'), ['mixed.admin.import-members*'], 'admin.mixed'),
        ];
    }

    /**
     * "Aktuelle Runde" (the open round, else the last one) and "Wertung" of the event, as in the mockups.
     *
     * @return list<AreaNavItem|null>
     */
    private function eventShortcuts(AreaNavBuilder $nav, ?MixedEvent $event): array
    {
        if ($event === null) {
            return [];
        }

        $eventId = (int) $event->id;
        $rounds = MixedRound::query()->where('event_id', $eventId)->orderBy('id')->get();
        $target = $rounds->first(static fn (MixedRound $r): bool => ! $r->isCompleted()) ?? $rounds->last();
        $roundId = $target !== null ? (int) $target->id : null;

        return [
            $roundId === null ? null : $nav->item('current-round', __('tournament-base::common.nav.current_round'), route('mixed.round', ['round' => $roundId]),
                fn (Request $r): bool => $r->routeIs('mixed.round', 'mixed.admin.round') && $this->routeRoundId($r) === $roundId),
            // In the admin view an event administrator gets the admin board (as the former tabs), everyone else the player one.
            $nav->item('standings', __('tournament-base::common.nav.standings'),
                route($nav->request->routeIs('mixed.admin.*') && $nav->user !== null && Gate::forUser($nav->user)->allows('administer', $event)
                    ? 'mixed.admin.standings' : 'mixed.standings', ['event' => $eventId]),
                fn (Request $r): bool => $r->routeIs('mixed.standings', 'mixed.admin.standings') && (int) $this->currentEvent($r)?->id === $eventId),
        ];
    }

    /**
     * The event with its other pages as sub-entries (the former event tabs, by role; Wertung is a shortcut above).
     */
    private function eventEntry(AreaNavBuilder $nav, MixedEvent $event): ?AreaNavItem
    {
        $user = $nav->user;
        if ($user === null) {
            return null;
        }

        $adminView = $nav->request->routeIs('mixed.admin.*');
        $canAdmin = Gate::forUser($user)->allows('administer', $event);
        $active = $this->activeTab($nav->request);

        $children = [];
        foreach (MixedTabs::items($event, $active, $adminView, $canAdmin) as $i => $tab) {
            if ($tab['key'] === 'wertung') {
                continue;
            }
            $children[] = $nav->item('event-'.$i, $tab['label'], $tab['href'], $tab['active'],
                badge: $tab['done'] ? __('mixed::common.round_done') : null,
                badgeIcon: $tab['done'] ? 'check' : null);
        }

        // Expanded on every page of this event (also where a shortcut above is the current entry).
        $onEvent = (int) $this->currentEvent($nav->request)?->id === (int) $event->id;

        return $nav->item('event', (string) $event->title, route('mixed.event', ['event' => (int) $event->id]), false,
            children: $children, current: $onEvent);
    }

    private function currentEvent(Request $request): ?MixedEvent
    {
        $event = $request->route('event');
        if ($event instanceof MixedEvent) {
            return $event;
        }
        if (is_numeric($event)) {
            return MixedEvent::query()->find((int) $event);
        }

        $round = $request->route('round');
        if (is_numeric($round)) {
            $round = MixedRound::query()->find((int) $round);
        }

        return $round instanceof MixedRound ? MixedEvent::query()->find((int) $round->event_id) : null;
    }

    private function routeRoundId(Request $request): int
    {
        $round = $request->route('round');

        return $round instanceof MixedRound ? (int) $round->id : (int) $round;
    }

    /** The MixedTabs key of the current page. */
    private function activeTab(Request $request): string
    {
        return match (true) {
            $request->routeIs('mixed.event', 'mixed.admin.event') => 'teilnehmer',
            $request->routeIs('mixed.round', 'mixed.admin.round') => 'round:'.$this->routeRoundId($request),
            $request->routeIs('mixed.admin.new-round') => 'neue-runde',
            $request->routeIs('mixed.durchmischung', 'mixed.admin.durchmischung') => 'durchmischung',
            $request->routeIs('mixed.standings', 'mixed.admin.standings') => 'wertung',
            $request->routeIs('mixed.admin.config') => 'konfiguration',
            default => '',
        };
    }
}
