<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Domain;

use App\Modules\MixedTournament\Models\MixedAvoidPair;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedParticipant;
use App\Modules\MixedTournament\Models\MixedRound;
use App\Modules\MixedTournament\Services\HistoryService;
use App\Modules\MixedTournament\Services\MexicanoService;
use App\Modules\MixedTournament\Services\RoundGenerationService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Random\Randomizer;

/**
 * Creates and regenerates round proposals (port of the old controller's
 * generateProposal/generateMexicanoProposal). The only source of randomness
 * is the injected Randomizer, handed to the pure services as the same two
 * callables the old code passed (shuffle, randomInt); the services never see
 * the Randomizer itself so the call protocol stays that of the old code.
 *
 * createAndPropose() is atomic: a failed proposal rolls the new round back,
 * so no empty round remains (design fix: ZF2 created the round first).
 */
final class ProposalGenerator
{
    public function __construct(
        private readonly Randomizer $random,
        private readonly RoundService $rounds,
        private readonly RoundGenerationService $americano,
        private readonly MexicanoService $mexicano,
        private readonly HistoryService $history,
    ) {}

    /**
     * @param  list<int>  $selectedIds  participant ids chosen for this round
     *
     * @throws MixedRuleException invalid_round_config, mexicano_round_open, min_four, extra_group_impossible
     */
    public function createAndPropose(MixedEvent $e, string $groupSizeMode, string $distribution, array $selectedIds): MixedRound
    {
        return DB::transaction(function () use ($e, $groupSizeMode, $distribution, $selectedIds): MixedRound {
            $round = $this->rounds->createRound($e, $groupSizeMode, $distribution);
            $this->generate($e, $round, $selectedIds);

            return $round;
        });
    }

    /**
     * @param  list<int>  $selectedIds
     *
     * @throws MixedRuleException round_state, min_four, extra_group_impossible
     */
    public function regenerate(MixedRound $r, array $selectedIds): void
    {
        DB::transaction(function () use ($r, $selectedIds): void {
            // Fixed lock order: event row, then the round row.
            $event = MixedEvent::query()->whereKey($r->event_id)->lockForUpdate()->firstOrFail();
            $round = MixedRound::query()->whereKey($r->id)->lockForUpdate()->firstOrFail();

            if ($round->status !== 'proposed') {
                throw new MixedRuleException('round_state');
            }

            $this->generate($event, $round, $selectedIds);
        });
    }

    /** @param  list<int>  $selectedIds */
    private function generate(MixedEvent $event, MixedRound $round, array $selectedIds): void
    {
        $wanted = array_flip(array_map(intval(...), $selectedIds));
        $selected = MixedParticipant::query()
            ->where('event_id', $event->id)
            ->orderBy('id')
            ->get()
            ->filter(fn (MixedParticipant $p): bool => isset($wanted[(int) $p->id]))
            ->values();

        if ($selected->count() < 4) {
            throw new MixedRuleException('min_four');
        }

        if ($event->isMexicano()) {
            $this->generateMexicano($event, $round, $selected->map(fn (MixedParticipant $p): int => (int) $p->id)->all());

            return;
        }

        $participants = [];
        $excluded = [];
        foreach ($selected as $p) {
            $participants[] = [
                'id' => (int) $p->id,
                'gender' => $p->gender,
                'itn' => $p->itnFloat(),
                'age' => $p->age !== null ? (int) $p->age : null,
            ];
            if ($p->no_extra_match) {
                $excluded[] = (int) $p->id;
            }
        }

        $avoidPairs = MixedAvoidPair::query()->where('event_id', $event->id)->orderBy('id')->get()
            ->map(fn (MixedAvoidPair $a): array => ['a' => (int) $a->participant_a, 'b' => (int) $a->participant_b])
            ->values()
            ->all();

        try {
            $proposal = $this->americano->propose(
                $participants,
                $round->group_size_mode,
                $round->distribution,
                $this->history->build($this->rounds->pastMatches($event)),
                $avoidPairs,
                fn (array $items): array => $this->random->shuffleArray($items),
                fn (int $min, int $max): int => $this->random->getInt($min, $max),
                $excluded,
            );
        } catch (InvalidArgumentException) {
            throw new MixedRuleException('extra_group_impossible');
        }

        $this->rounds->persistProposal($round, $proposal['groups'], $proposal['matches']);
    }

    /** @param  list<int>  $selected */
    private function generateMexicano(MixedEvent $event, MixedRound $round, array $selected): void
    {
        $shuffle = fn (array $items): array => $this->random->shuffleArray($items);

        // No finished round = no standings: start at random. Deliberately not
        // pastMatches(): a confirmed round without scores rates all zero.
        if ($this->rounds->completedRoundCount($event) > 0) {
            $rows = [];
            foreach ($this->rounds->standings($event) as $row) {
                if (in_array((int) $row['id'], $selected, true)) {
                    $rows[] = $row;
                }
            }
            $ranked = $this->mexicano->rank($rows, $shuffle);
        } else {
            $ranked = $shuffle($selected);
        }

        $proposal = $this->mexicano->propose($ranked, $this->rounds->sitOutCounts($event), $shuffle);

        $groups = [];
        $matches = [];
        foreach ($proposal['groups'] as $index => $group) {
            $groups[] = $group['players'];
            $matches[] = [
                'group' => $index,
                'match_no' => 0,
                'team_a' => $group['team_a'],
                'team_b' => $group['team_b'],
                'is_extra' => false,
            ];
        }

        $this->rounds->persistProposal($round, $groups, $matches, $proposal['sitters']);
    }
}
