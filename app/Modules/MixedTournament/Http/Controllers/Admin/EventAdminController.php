<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\MixedTournament\Domain\EventService;
use App\Modules\MixedTournament\Models\MixedAvoidPair;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedEventGrant;
use App\Modules\MixedTournament\Models\MixedParticipant;
use App\Modules\MixedTournament\Models\MixedRound;
use App\Modules\MixedTournament\Policies\MixedEventPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
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

    /** Global right (route middleware): a test-mode user must not switch it, it would make everyone admin. */
    public function setTestMode(Request $request, MixedEvent $event): RedirectResponse
    {
        $on = (string) $request->input('is_test') === '1';
        $this->events->setTestMode($event, $on);

        return $this->toConfig($event)->with('success', __('mixed::messages.'.($on ? 'test_mode_on' : 'test_mode_off')));
    }

    /** Global right (route middleware). The user must exist and the role be score|admin. */
    public function grant(Request $request, MixedEvent $event): RedirectResponse
    {
        $uid = (int) $request->input('uid');
        $role = (string) $request->input('role');

        if ($uid <= 0 || User::query()->whereKey($uid)->doesntExist() || ! in_array($role, ['score', 'admin'], true)) {
            return $this->toConfig($event)->with('error', __('mixed::messages.grant_select'));
        }

        $this->events->setGrant($event, $uid, $role);

        return $this->toConfig($event)->with('success', __('mixed::messages.grant_saved'));
    }

    /** Global right (route middleware). A uid without a grant in THIS event is not found. */
    public function revoke(Request $request, MixedEvent $event): RedirectResponse
    {
        $uid = (int) $request->input('uid');
        abort_if($this->events->grant($event, $uid) === null, 404);

        $this->events->removeGrant($event, $uid);

        return $this->toConfig($event)->with('success', __('mixed::messages.grant_removed'));
    }

    public function setCourtCount(Request $request, MixedEvent $event): RedirectResponse
    {
        $this->authorizeAdminister($request, $event);

        $this->events->setCourtCount($event, (int) $request->input('court_count'));

        return $this->toConfig($event)->with('success', __('mixed::messages.court_count_saved'));
    }

    /** Participants tab (old admin/event.phtml). The member roster is NOT embedded; the picker searches. */
    public function event(Request $request, MixedEvent $event): View
    {
        $user = $this->authorizeAdminister($request, $event);
        $participants = MixedParticipant::query()->where('event_id', $event->id)->orderBy('id')->get();

        return view('mixed::admin.event', $this->head($user, $event, $participants) + [
            'participants' => $participants,
        ]);
    }

    /** Configuration tab (old admin/config.phtml): courts, blank scorecards, grants, avoid pairs, test mode. */
    public function config(Request $request, MixedEvent $event): View
    {
        $user = $this->authorizeAdminister($request, $event);
        $participants = MixedParticipant::query()->where('event_id', $event->id)->orderBy('id')->get();
        $isGlobalAdmin = MixedEventPolicy::isGlobalAdmin($user);

        $grants = collect();
        $users = collect();
        $userLabels = [];
        if ($isGlobalAdmin) {
            $grants = MixedEventGrant::query()->where('event_id', $event->id)->orderBy('id')->get();
            $userLabels = User::query()->whereIn('uid', $grants->pluck('uid')->all())->pluck('alias', 'uid')->all();
            // Selectable as in ZF2: enabled accounts and administrators (not deleted, blocked or unconfirmed).
            $users = User::query()->whereIn('status', ['enabled', 'admin'])->orderBy('alias')->orderBy('uid')->get(['uid', 'alias']);
        }

        return view('mixed::admin.config', $this->head($user, $event, $participants) + [
            'participants' => $participants,
            'avoidPairs' => MixedAvoidPair::query()->where('event_id', $event->id)->orderBy('id')->get(),
            'grants' => $grants,
            'userLabels' => $userLabels,
            'users' => $users,
        ]);
    }

    public function durchmischung(MixedEvent $event): never
    {
        abort(501);
    }

    public function standings(MixedEvent $event): never
    {
        abort(501);
    }

    /**
     * Shared header data of the event admin tabs: counts, the events the viewer administers (switcher) and
     * whether the global actions (set default, new event) are offered.
     *
     * @param  Collection<int, MixedParticipant>  $participants
     * @return array<string, mixed>
     */
    private function head(User $user, MixedEvent $event, Collection $participants): array
    {
        $gate = Gate::forUser($user);
        $switch = MixedEvent::query()->orderBy('id')->get()
            ->filter(static fn (MixedEvent $e): bool => $gate->allows('administer', $e))
            ->values();

        return [
            'event' => $event,
            'activeCount' => $participants->where('status', 'active')->count(),
            'totalCount' => $participants->count(),
            'roundCount' => MixedRound::query()->where('event_id', $event->id)->count(),
            'switchEvents' => $switch,
            'isGlobalAdmin' => MixedEventPolicy::isGlobalAdmin($user),
        ];
    }

    private function authorizeAdminister(Request $request, MixedEvent $event): User
    {
        $user = $this->user($request);
        abort_unless(Gate::forUser($user)->allows('administer', $event), 403);

        return $user;
    }

    private function toConfig(MixedEvent $event): RedirectResponse
    {
        return redirect()->route('mixed.admin.config', ['event' => (int) $event->id]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
