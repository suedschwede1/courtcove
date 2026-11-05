<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\MixedTournament\Domain\MixedRuleException;
use App\Modules\MixedTournament\Domain\ParticipantService;
use App\Modules\MixedTournament\Models\MixedAvoidPair;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedParticipant;
use App\Modules\MixedTournament\Support\AdminInput;
use App\Modules\MixedTournament\Support\ParticipantInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Participants, avoid pairs and the member search (old AdminController, participant part). Every action
 * needs the event `administer` ability through the Gate.
 *
 * ParticipantService::update/setStatus/remove take no event scope, so the participant routes resolve the
 * participant THROUGH the event: the posted `event_id` (as in the ZF2 forms) names the event, the ability on
 * that event is checked first (403), and only then the participant is looked up inside it (404), so a user
 * without rights learns nothing about participant ids. Avoid pairs are checked the same way.
 */
class ParticipantAdminController extends Controller
{
    public function __construct(private readonly ParticipantService $participants) {}

    /** Authenticated member picker search: `{"members": [{label, gender, itn, age}]}`, no contact data. */
    public function memberSearch(Request $request, MixedEvent $event): JsonResponse
    {
        $this->authorizeAdminister($request, $event);

        return response()->json([
            'members' => $this->participants->searchMembers($event, AdminInput::string($request, 'q')),
        ]);
    }

    public function matchMembers(Request $request, MixedEvent $event): RedirectResponse
    {
        $this->authorizeAdminister($request, $event);

        $count = $this->participants->refreshFromMembers($event);

        return $this->toEvent($event)->with('success', __('mixed::messages.members_matched', ['count' => $count]));
    }

    public function pauseAll(Request $request, MixedEvent $event): RedirectResponse
    {
        $this->authorizeAdminister($request, $event);

        $this->participants->pauseAll($event);

        return $this->toEvent($event)->with('success', __('mixed::messages.all_paused'));
    }

    public function add(Request $request, MixedEvent $event): RedirectResponse
    {
        $this->authorizeAdminister($request, $event);

        try {
            $this->participants->add($event, ParticipantInput::parse($request->all()));
        } catch (MixedRuleException $e) {
            return $this->toEvent($event)->with('error', __('mixed::messages.'.$e->key, $e->replace));
        }

        return $this->toEvent($event)->with('success', __('mixed::messages.participant_added'));
    }

    public function update(Request $request, string $participant): RedirectResponse
    {
        [$event, $participant] = $this->participantInEvent($request, $participant);

        try {
            $this->participants->update($participant, ParticipantInput::parse($request->all()));
        } catch (MixedRuleException $e) {
            return $this->toEvent($event)->with('error', __('mixed::messages.'.$e->key, $e->replace));
        }

        return $this->toEvent($event)->with('success', __('mixed::messages.participant_updated'));
    }

    /** Pausing instead of deleting. Only `active` or `paused` (anything else is 422). */
    public function status(Request $request, string $participant): RedirectResponse
    {
        [$event, $participant] = $this->participantInEvent($request, $participant);

        $status = AdminInput::string($request, 'status');
        abort_unless(in_array($status, ['active', 'paused'], true), 422);

        $this->participants->setStatus($participant, $status);

        return $this->toEvent($event);
    }

    public function remove(Request $request, string $participant): RedirectResponse
    {
        [$event, $participant] = $this->participantInEvent($request, $participant);

        try {
            $this->participants->remove($participant);
        } catch (MixedRuleException $e) {
            return $this->toEvent($event)->with('error', __('mixed::messages.'.$e->key, $e->replace));
        }

        return $this->toEvent($event)->with('success', __('mixed::messages.participant_removed'));
    }

    /** The form sits on the configuration tab, so the answer goes back there. */
    public function addAvoidPair(Request $request, MixedEvent $event): RedirectResponse
    {
        $this->authorizeAdminister($request, $event);

        $a = AdminInput::id($request, 'participant_a');
        $b = AdminInput::id($request, 'participant_b');

        try {
            if ($a <= 0 || $b <= 0) {
                throw new MixedRuleException('avoid_pair_invalid');
            }
            $this->participants->addAvoidPair($event, $a, $b);
        } catch (MixedRuleException $e) {
            return $this->toConfig($event)->with('error', __('mixed::messages.'.$e->key, $e->replace));
        }

        return $this->toConfig($event)->with('success', __('mixed::messages.avoid_pair_added'));
    }

    /** A pair id of another event is not found. */
    public function removeAvoidPair(Request $request, MixedEvent $event): RedirectResponse
    {
        $this->authorizeAdminister($request, $event);

        $pairId = AdminInput::id($request, 'pair_id');
        abort_unless(MixedAvoidPair::query()->where('id', $pairId)->where('event_id', $event->id)->exists(), 404);

        $this->participants->removeAvoidPair($event, $pairId);

        return $this->toConfig($event)->with('success', __('mixed::messages.avoid_pair_removed'));
    }

    /**
     * The event named by the posted `event_id` (404 if there is none), the administer ability on it (403),
     * then the participant inside THAT event (404). Authorization comes before the participant lookup.
     *
     * @return array{MixedEvent, MixedParticipant}
     */
    private function participantInEvent(Request $request, string $participantId): array
    {
        $event = MixedEvent::query()->find(AdminInput::id($request, 'event_id'));
        abort_if($event === null, 404);

        $this->authorizeAdminister($request, $event);

        $participant = MixedParticipant::query()
            ->where('id', (int) $participantId)
            ->where('event_id', $event->id)
            ->first();
        abort_if($participant === null, 404);

        return [$event, $participant];
    }

    private function authorizeAdminister(Request $request, MixedEvent $event): void
    {
        $user = $request->user();
        abort_unless($user instanceof User && Gate::forUser($user)->allows('administer', $event), 403);
    }

    private function toEvent(MixedEvent $event): RedirectResponse
    {
        return redirect()->route('mixed.admin.event', ['event' => (int) $event->id]);
    }

    private function toConfig(MixedEvent $event): RedirectResponse
    {
        return redirect()->route('mixed.admin.config', ['event' => (int) $event->id]);
    }
}
