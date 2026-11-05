<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Support;

use App\Models\User;
use App\Modules\MixedTournament\Domain\RoundService;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedMatch;
use App\Modules\MixedTournament\Models\MixedParticipant;
use App\Modules\MixedTournament\Models\MixedRound;
use App\Modules\MixedTournament\Services\WaveService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Everything a round page shows (player and admin view): matches by group, the wave plan
 * (Americano: group -> wave; Mexicano: group -> wave and court), sitters, the top 5 and the
 * viewer's abilities. One place, so screen and printout never compute different wave plans.
 */
final class RoundViewData
{
    private const SLOTS = ['team_a_p1', 'team_a_p2', 'team_b_p1', 'team_b_p2'];

    public function __construct(
        private readonly RoundService $rounds,
        private readonly WaveService $waves,
    ) {}

    /**
     * @return array{
     *     event: MixedEvent,
     *     round: MixedRound,
     *     rounds: Collection<int, MixedRound>,
     *     labels: array<int, string>,
     *     genders: array<int, string|null>,
     *     byGroup: array<int, list<MixedMatch>>,
     *     groupPlayers: array<int, list<int>>,
     *     playsTwice: list<int>,
     *     waves: array<int, int>|null,
     *     slots: array<int, array{wave: int, court: int}>|null,
     *     waveCount: int,
     *     courtCount: int,
     *     sitters: list<int>,
     *     top5: list<array{id: int, points: int, played: int, games_won: int, games_avg: float, points_avg: float, rounds_played: int, eligible: bool, rounds_needed: int}>,
     *     preview: array<string, mixed>|null,
     *     canScore: bool,
     *     canAdmin: bool,
     * }
     */
    public function build(MixedRound $r, ?User $viewer, bool $withPreview = false): array
    {
        $event = MixedEvent::query()->whereKey($r->event_id)->firstOrFail();
        $rounds = MixedRound::query()->where('event_id', $event->id)->orderBy('id')->get();

        $labels = [];
        $genders = [];
        foreach (MixedParticipant::query()->where('event_id', $event->id)->orderBy('id')->get() as $participant) {
            $labels[(int) $participant->id] = (string) $participant->label;
            $genders[(int) $participant->id] = $participant->gender;
        }

        $byGroup = [];
        foreach ($r->matches()->get() as $match) {
            $byGroup[(int) $match->group_no][] = $match;
        }
        ksort($byGroup);

        // Distinct players per group in order of appearance; a filler of the extra group appears in two groups.
        $groupPlayers = [];
        $groupCount = [];
        foreach ($byGroup as $groupNo => $matches) {
            $seen = [];
            foreach ($matches as $match) {
                foreach (self::SLOTS as $slot) {
                    $seen[(int) $match->{$slot}] = true;
                }
            }
            $groupPlayers[$groupNo] = array_keys($seen);
            foreach ($groupPlayers[$groupNo] as $pid) {
                $groupCount[$pid] = ($groupCount[$pid] ?? 0) + 1;
            }
        }
        $playsTwice = array_keys(array_filter($groupCount, static fn (int $count): bool => $count > 1));

        $asArrays = array_map(
            static fn (array $matches): array => array_map(static fn (MixedMatch $m): array => $m->toArray(), $matches),
            $byGroup,
        );
        $courtCount = max(1, (int) $event->court_count);

        if ($event->isMexicano()) {
            $waves = null;
            $slots = $this->waves->mexicanoSlots($asArrays, $courtCount);
            $waveCount = $this->waves->waveCount($slots);
        } else {
            $waves = $this->waves->americanoWaves($asArrays, $courtCount);
            $slots = null;
            $waveCount = $this->waves->waveCount($waves);
        }

        $gate = $viewer !== null ? Gate::forUser($viewer) : null;

        return [
            'event' => $event,
            'round' => $r,
            'rounds' => $rounds,
            'labels' => $labels,
            'genders' => $genders,
            'byGroup' => $byGroup,
            'groupPlayers' => $groupPlayers,
            'playsTwice' => $playsTwice,
            'waves' => $waves,
            'slots' => $slots,
            'waveCount' => $waveCount,
            'courtCount' => $courtCount,
            'sitters' => $this->rounds->roundSitters($r),
            'top5' => array_slice($this->rounds->standings($event), 0, 5),
            'preview' => $withPreview ? $this->rounds->preview($r) : null,
            'canScore' => $gate?->allows('score', $event) ?? false,
            'canAdmin' => $gate?->allows('administer', $event) ?? false,
        ];
    }
}
