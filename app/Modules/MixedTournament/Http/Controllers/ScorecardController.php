<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\MixedTournament\Domain\RoundService;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedMatch;
use App\Modules\MixedTournament\Models\MixedParticipant;
use App\Modules\MixedTournament\Models\MixedRound;
use App\Modules\MixedTournament\Services\ScorecardBuilder;
use App\Modules\MixedTournament\Services\ScorecardTemplateService;
use App\Modules\MixedTournament\Support\MixedFormat;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Printable scorecards (old IndexController::scorecardAction / scorecardBlankAction): standalone
 * A4 pages without the app layout. Every reader of the event may print them, like in ZF2.
 *
 * The result boxes are ALWAYS empty: the slip is a form, not a record, so no score reaches the view.
 */
class ScorecardController extends Controller
{
    public function round(Request $request, MixedRound $round, RoundService $rounds, ScorecardBuilder $builder): View|RedirectResponse
    {
        // The round's own event decides the access; nothing is built before that.
        $event = MixedEvent::query()->whereKey($round->event_id)->firstOrFail();
        $this->authorizeView($request, $event);

        // Only while confirmed: before, a swap would invalidate a slip already handed out; after, it is waste paper.
        if (! $round->isConfirmed()) {
            return redirect()->route('mixed.round', ['round' => (int) $round->id])
                ->with('error', __('mixed::messages.scorecard_only_confirmed'));
        }

        $labels = [];
        $genders = [];
        foreach (MixedParticipant::query()->where('event_id', $event->id)->orderBy('id')->get() as $participant) {
            $labels[(int) $participant->id] = (string) $participant->label;
            $genders[(int) $participant->id] = MixedFormat::gender($participant->gender);
        }

        // Only the pairing columns: games never reach the view.
        $matches = $round->matches()->get()->map(static fn (MixedMatch $m): array => $m->only([
            'group_no', 'match_no', 'team_a_p1', 'team_a_p2', 'team_b_p1', 'team_b_p2', 'is_extra',
        ]))->values()->all();
        $courtCount = (int) $event->court_count;

        return view($event->isMexicano() ? 'mixed::print.scorecard-mexicano' : 'mixed::print.scorecard', [
            'event' => $event,
            'round' => $round,
            'date' => MixedFormat::date($event->event_date),
            'cards' => $event->isMexicano()
                ? $builder->mexicano($matches, $labels, $genders, $courtCount)
                : $builder->americano($matches, $labels, $genders, $courtCount),
            'sitters' => array_map(static fn (int $id): string => $labels[$id] ?? '?', $rounds->roundSitters($round)),
            'backUrl' => route('mixed.round', ['round' => (int) $round->id]),
        ]);
    }

    /**
     * Blank slips without names for printing in stock: position codes instead of names. Bound to the
     * event because they are printed before a round exists, so there is no status check.
     */
    public function blank(Request $request, MixedEvent $event, ScorecardTemplateService $template): View
    {
        $user = $this->authorizeView($request, $event);

        // Only 4 or 5; anything else falls back to 4 so a tampered parameter never reaches the rotation.
        $size = (int) $request->query('size') === 5 ? 5 : 4;
        // At least 2 (two per A4 page), at most 40 (a typo must not produce hundreds of pages).
        $count = max(2, min(40, (int) $request->query('count', '8')));

        return view('mixed::print.scorecard-blank', [
            'event' => $event,
            'rows' => $template->rows((string) $event->mode, $size),
            'playerCount' => $template->playerCount((string) $event->mode, $size),
            'count' => $count,
            'backUrl' => Gate::forUser($user)->allows('administer', $event)
                ? route('mixed.admin.config', ['event' => (int) $event->id])
                : route('mixed.event', ['event' => (int) $event->id]),
        ]);
    }

    private function authorizeView(Request $request, MixedEvent $event): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && Gate::forUser($user)->allows('view', $event), 403);

        return $user;
    }
}
