<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\MixedTournament\Domain\EventService;
use App\Modules\MixedTournament\Domain\RoundService;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedParticipant;
use App\Modules\MixedTournament\Models\MixedRound;
use App\Modules\MixedTournament\Policies\MixedEventPolicy;
use App\Modules\MixedTournament\Services\HistoryService;
use App\Modules\MixedTournament\Support\MixingMatrix;
use App\Modules\MixedTournament\Support\RoundViewData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Player side of the Mixed tournament (old IndexController): every logged-in user reads every event.
 */
class PlayerController extends Controller
{
    /** The default event opens directly; without one the list of all events. */
    public function index(Request $request, EventService $events): View|RedirectResponse
    {
        $default = $events->defaultEvent();

        if ($default !== null) {
            return redirect()->route('mixed.event', ['event' => (int) $default->id]);
        }

        return view('mixed::player.index', [
            'events' => MixedEvent::query()->withCount(['participants', 'rounds'])->orderBy('id')->get(),
            'isGlobalAdmin' => MixedEventPolicy::isGlobalAdmin($this->user($request)),
        ]);
    }

    /** Static help page; the avoid-pair section and the admin link only for global administrators (as in ZF2). */
    public function info(Request $request): View
    {
        return view('mixed::player.info', [
            'isGlobalAdmin' => MixedEventPolicy::isGlobalAdmin($this->user($request)),
        ]);
    }

    public function event(Request $request, MixedEvent $event): View
    {
        $user = $this->authorizeView($request, $event);

        return view('mixed::player.event', [
            'event' => $event,
            'participants' => MixedParticipant::query()->where('event_id', $event->id)->orderBy('id')->get(),
            'rounds' => MixedRound::query()->where('event_id', $event->id)->orderBy('id')->get(),
            'canAdmin' => Gate::forUser($user)->allows('administer', $event),
        ]);
    }

    public function round(Request $request, MixedRound $round, RoundViewData $data): View
    {
        // Authorize on the round's own event first; the view data (standings, waves) is built only afterwards.
        $user = $this->authorizeView($request, MixedEvent::query()->whereKey($round->event_id)->firstOrFail());
        $view = $data->build($round, $user);

        return view($view['event']->isMexicano() ? 'mixed::player.round-mexicano' : 'mixed::player.round', $view);
    }

    public function durchmischung(Request $request, MixedEvent $event, RoundService $rounds, HistoryService $history, MixingMatrix $matrix): View
    {
        $user = $this->authorizeView($request, $event);

        $labels = $this->labels($event);
        $pastMatches = $rounds->pastMatches($event);
        $partnerCounts = $history->build($pastMatches)['partners'];

        return view('mixed::player.durchmischung', [
            'event' => $event,
            'matrix' => $matrix->build($labels, $partnerCounts, $pastMatches),
            'mixing' => $history->mixingScore($partnerCounts, array_keys($labels)),
            'canAdmin' => Gate::forUser($user)->allows('administer', $event),
        ]);
    }

    public function standings(Request $request, MixedEvent $event, RoundService $rounds): View
    {
        $user = $this->authorizeView($request, $event);

        return view('mixed::player.standings', [
            'event' => $event,
            'standings' => $rounds->standings($event),
            'labels' => $this->labels($event),
            'canAdmin' => Gate::forUser($user)->allows('administer', $event),
        ]);
    }

    private function authorizeView(Request $request, MixedEvent $event): User
    {
        $user = $this->user($request);
        abort_unless($user !== null && Gate::forUser($user)->allows('view', $event), 403);

        return $user;
    }

    private function user(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }

    /** @return array<int, string> participant id => label, by id */
    private function labels(MixedEvent $event): array
    {
        $labels = [];
        foreach (MixedParticipant::query()->where('event_id', $event->id)->orderBy('id')->get() as $participant) {
            $labels[(int) $participant->id] = (string) $participant->label;
        }

        return $labels;
    }
}
