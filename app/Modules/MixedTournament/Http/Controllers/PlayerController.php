<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\MixedTournament\Domain\EventService;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedParticipant;
use App\Modules\MixedTournament\Models\MixedRound;
use App\Modules\MixedTournament\Policies\MixedEventPolicy;
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

    public function round(MixedRound $round): never
    {
        abort(501);
    }

    public function durchmischung(MixedEvent $event): never
    {
        abort(501);
    }

    public function standings(MixedEvent $event): never
    {
        abort(501);
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
}
