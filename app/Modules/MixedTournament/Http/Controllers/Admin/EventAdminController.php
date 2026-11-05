<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\MixedTournament\Domain\EventService;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedRound;
use App\Modules\MixedTournament\Policies\MixedEventPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * Overview, events, configuration and grants (old AdminController, event part).
 *
 * The overview is open to every logged-in user. Create-event, set-default, test mode and grants sit
 * behind the global `can:admin.mixed` route middleware; the event pages need the event `administer`
 * ability, checked here through the Gate (a test event makes everyone event admin, never global admin).
 */
class EventAdminController extends Controller
{
    public function __construct(private readonly EventService $events) {}

    public function index(Request $request): View
    {
        $user = $this->user($request);
        $gate = Gate::forUser($user);

        $events = MixedEvent::query()
            ->withMax('rounds', 'round_no')
            ->orderBy('id')
            ->get();

        $canAdmin = [];
        foreach ($events as $event) {
            $canAdmin[(int) $event->id] = $gate->allows('administer', $event);
        }

        $administered = array_keys(array_filter($canAdmin));
        $titles = $events->pluck('title', 'id');
        $todos = MixedRound::query()
            ->whereIn('event_id', $administered)
            ->where('status', 'proposed')
            ->orderBy('event_id')
            ->orderBy('id')
            ->get()
            ->each(static function (MixedRound $round) use ($titles): void {
                $round->setAttribute('event_title', (string) ($titles[(int) $round->event_id] ?? ''));
            });

        return view('mixed::admin.index', [
            'events' => $events,
            'canAdmin' => $canAdmin,
            'todos' => $todos,
            'isGlobalAdmin' => MixedEventPolicy::isGlobalAdmin($user),
        ]);
    }

    /** Global right (route middleware). */
    public function createEvent(Request $request): RedirectResponse
    {
        $title = trim((string) $request->input('title', ''));
        $date = (string) $request->input('event_date', '');

        $valid = $title !== ''
            && Validator::make(['event_date' => $date], ['event_date' => ['required', 'date_format:Y-m-d']])->passes();

        if (! $valid) {
            return redirect()->route('mixed.admin.index')->with('error', __('mixed::messages.title_date_required'));
        }

        $mode = $request->input('mode') === 'mexicano' ? 'mexicano' : 'americano';
        $event = $this->events->create($title, $date, $mode);

        return redirect()->route('mixed.admin.event', ['event' => (int) $event->id])
            ->with('success', __('mixed::messages.event_created'));
    }

    /** Global right (route middleware). */
    public function setDefault(MixedEvent $event): RedirectResponse
    {
        $this->events->setDefault($event);

        return redirect()->route('mixed.admin.event', ['event' => (int) $event->id])
            ->with('success', __('mixed::messages.default_set'));
    }

    public function setTestMode(MixedEvent $event): never
    {
        abort(501);
    }

    public function grant(MixedEvent $event): never
    {
        abort(501);
    }

    public function revoke(MixedEvent $event): never
    {
        abort(501);
    }

    public function setCourtCount(MixedEvent $event): never
    {
        abort(501);
    }

    public function event(MixedEvent $event): never
    {
        abort(501);
    }

    public function config(MixedEvent $event): never
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

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
