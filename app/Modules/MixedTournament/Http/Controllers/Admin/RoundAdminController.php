<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\MixedTournament\Domain\MixedRuleException;
use App\Modules\MixedTournament\Domain\ProposalGenerator;
use App\Modules\MixedTournament\Domain\RoundService;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedParticipant;
use App\Modules\MixedTournament\Models\MixedRound;
use App\Modules\MixedTournament\Support\AdminEventHead;
use App\Modules\MixedTournament\Support\AdminInput;
use App\Modules\MixedTournament\Support\RoundViewData;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * New round, round workspace and score entry (old AdminController, round part).
 *
 * Abilities through the Gate on the round's OWN event: the workspace and the score entry need `score`,
 * everything else `administer`. Rounds come from route binding; every participant or match id posted
 * with a round is resolved through that round's event (another event's id: 404). Every refused rule is a
 * MixedRuleException, flashed as its message key.
 */
class RoundAdminController extends Controller
{
    public function __construct(
        private readonly RoundService $rounds,
        private readonly ProposalGenerator $proposals,
    ) {}

    /** "+ Neue Runde" (old new-round.phtml / new-round-mexicano.phtml). */
    public function newRound(Request $request, MixedEvent $event): View
    {
        $user = $this->authorize($request, $event, 'administer');
        $participants = MixedParticipant::query()->where('event_id', $event->id)->orderBy('id')->get();

        return view('mixed::admin.new-round', AdminEventHead::data($user, $event, $participants) + [
            'participants' => $participants,
            'rounds' => MixedRound::query()->where('event_id', $event->id)->orderBy('id')->get(),
            'openRound' => $event->isMexicano() ? $this->rounds->openRound($event) : null,
        ]);
    }

    /** Round and first proposal in one transaction: a refused proposal leaves no round behind. */
    public function createRound(Request $request, MixedEvent $event): RedirectResponse
    {
        $this->authorize($request, $event, 'administer');
        $selected = $this->selectedIds($request, $event);

        try {
            $round = $this->proposals->createAndPropose(
                $event,
                AdminInput::string($request, 'group_size_mode'),
                AdminInput::string($request, 'distribution'),
                $selected,
            );
        } catch (MixedRuleException $e) {
            return redirect()->route('mixed.admin.new-round', ['event' => (int) $event->id])
                ->with('error', __('mixed::messages.'.$e->key, $e->replace));
        }

        return $this->toRound($round)->with('success', __('mixed::messages.round_created'));
    }

    /** Round workspace (old admin/round.phtml, round-mexicano.phtml): proposal check and swaps, or score entry. */
    public function show(Request $request, MixedRound $round, RoundViewData $data): View
    {
        $event = $this->eventOf($round);
        $user = $this->authorize($request, $event, 'score');

        $view = $data->build($round, $user, $round->isProposed());

        return view($event->isMexicano() ? 'mixed::admin.round-mexicano' : 'mixed::admin.round', $view + [
            'participants' => MixedParticipant::query()->where('event_id', $event->id)->orderBy('id')->get(),
            'roundParticipantIds' => $this->rounds->roundParticipantIds($round),
        ]);
    }

    /** Stores group size mode and distribution of a proposed round (old update-round-config, no flash). */
    public function updateConfig(Request $request, MixedRound $round): RedirectResponse
    {
        $this->authorize($request, $this->eventOf($round), 'administer');

        return $this->attempt($round, function () use ($request, $round): ?string {
            $this->rounds->updateConfig($round, AdminInput::string($request, 'group_size_mode'), AdminInput::string($request, 'distribution'));

            return null;
        });
    }

    /**
     * "Neu generieren": optionally stores a changed configuration, then replaces the proposal for the posted
     * participants. Both in one transaction, so a refused proposal keeps the old configuration as well.
     */
    public function generate(Request $request, MixedRound $round): RedirectResponse
    {
        $event = $this->eventOf($round);
        $this->authorize($request, $event, 'administer');
        $selected = $this->selectedIds($request, $event);

        return $this->attempt($round, function () use ($request, $round, $selected): string {
            DB::transaction(function () use ($request, $round, $selected): void {
                if ($request->has('group_size_mode') || $request->has('distribution')) {
                    $this->rounds->updateConfig(
                        $round,
                        $request->has('group_size_mode') ? AdminInput::string($request, 'group_size_mode') : (string) $round->group_size_mode,
                        $request->has('distribution') ? AdminInput::string($request, 'distribution') : (string) $round->distribution,
                    );
                }
                $this->proposals->regenerate($round, $selected);
            });

            return 'proposal_created';
        });
    }

    public function swap(Request $request, MixedRound $round): RedirectResponse
    {
        $event = $this->eventOf($round);
        $this->authorize($request, $event, 'administer');
        $a = $this->participantId($request, $event, 'participant_a');
        $b = $this->participantId($request, $event, 'participant_b');

        return $this->attempt($round, function () use ($round, $a, $b): string {
            $this->rounds->swapPlayers($round, $a, $b);

            return 'swapped';
        });
    }

    public function swapSitter(Request $request, MixedRound $round): RedirectResponse
    {
        $event = $this->eventOf($round);
        $this->authorize($request, $event, 'administer');
        $sitter = $this->participantId($request, $event, 'sitter_id');
        $player = $this->participantId($request, $event, 'player_id');

        return $this->attempt($round, function () use ($round, $sitter, $player): string {
            $this->rounds->swapSitter($round, $sitter, $player);

            return 'sitter_swapped';
        });
    }

    public function confirm(Request $request, MixedRound $round): RedirectResponse
    {
        $this->authorize($request, $this->eventOf($round), 'administer');

        return $this->attempt($round, function () use ($round): string {
            $this->rounds->confirm($round);

            return 'confirmed';
        });
    }

    public function complete(Request $request, MixedRound $round): RedirectResponse
    {
        $this->authorize($request, $this->eventOf($round), 'administer');

        return $this->attempt($round, function () use ($round): string {
            $this->rounds->complete($round);

            return 'completed';
        });
    }

    public function reopen(Request $request, MixedRound $round): RedirectResponse
    {
        $this->authorize($request, $this->eventOf($round), 'administer');

        return $this->attempt($round, function () use ($round): string {
            $this->rounds->reopen($round);

            return 'reopened';
        });
    }

    /** Only a proposal can be deleted; afterwards back to the event (as in ZF2). */
    public function delete(Request $request, MixedRound $round): RedirectResponse
    {
        $event = $this->eventOf($round);
        $this->authorize($request, $event, 'administer');

        try {
            $this->rounds->delete($round);
        } catch (MixedRuleException $e) {
            return $this->toRound($round)->with('error', __('mixed::messages.'.$e->key, $e->replace));
        }

        return redirect()->route('mixed.admin.event', ['event' => (int) $event->id])
            ->with('success', __('mixed::messages.round_deleted'));
    }

    /** One of the five fixed results; a match of another round is not found (RoundService). */
    public function score(Request $request, MixedRound $round): RedirectResponse
    {
        $this->authorize($request, $this->eventOf($round), 'score');
        $matchId = AdminInput::id($request, 'match_id');
        $result = AdminInput::string($request, 'result');

        return $this->attempt($round, function () use ($round, $matchId, $result): string {
            $this->rounds->recordScore($round, $matchId, $result);

            return 'score_saved';
        });
    }

    /**
     * Runs a round action and redirects to the workspace with its success flash (null: none) or the refused
     * rule's flash.
     *
     * @param  Closure(): ?string  $action  returns the success message key
     */
    private function attempt(MixedRound $round, Closure $action): RedirectResponse
    {
        try {
            $key = $action();
        } catch (MixedRuleException $e) {
            return $this->toRound($round)->with('error', __('mixed::messages.'.$e->key, $e->replace));
        }

        $redirect = $this->toRound($round);

        return $key !== null ? $redirect->with('success', __('mixed::messages.'.$key)) : $redirect;
    }

    private function toRound(MixedRound $round): RedirectResponse
    {
        return redirect()->route('mixed.admin.round', ['round' => (int) $round->id]);
    }

    private function eventOf(MixedRound $round): MixedEvent
    {
        return MixedEvent::query()->whereKey($round->event_id)->firstOrFail();
    }

    private function authorize(Request $request, MixedEvent $event, string $ability): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && Gate::forUser($user)->allows($ability, $event), 403);

        return $user;
    }

    /**
     * The posted participants[] (distinct, posted order); an id of another event is not found.
     *
     * @return list<int>
     */
    private function selectedIds(Request $request, MixedEvent $event): array
    {
        $ids = [];
        foreach ((array) $request->input('participants', []) as $id) {
            // Only decimal ids; anything else cannot match a participant and makes the request not found.
            $ids[is_scalar($id) && ctype_digit((string) $id) ? (int) $id : 0] = true;
        }
        $ids = array_keys($ids);

        $known = MixedParticipant::query()->where('event_id', $event->id)->whereIn('id', $ids)->count();
        abort_if($known !== count($ids), 404);

        return $ids;
    }

    /** A posted participant id that must belong to the round's event (else 404). */
    private function participantId(Request $request, MixedEvent $event, string $field): int
    {
        $id = AdminInput::id($request, $field);
        abort_unless(MixedParticipant::query()->where('event_id', $event->id)->whereKey($id)->exists(), 404);

        return $id;
    }
}
