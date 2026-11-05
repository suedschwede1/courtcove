<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Services;

/**
 * Builds the complete round proposal from a participant list: groups
 * (including a possible extra group for leftover players) and their matches
 * according to the rotation table. Pure orchestration - the actual logic lives
 * in GroupingService and RotationService.
 */
final class RoundGenerationService
{
    public function __construct(
        private readonly GroupingService $grouping,
        private readonly RotationService $rotation,
    ) {}

    /**
     * @param  list<array{id: int, gender?: string|null, itn?: float|int|null, age?: int|null}>  $participants  per entry: id, gender, itn
     * @param  string  $groupSizeMode  '4_only' | '4_and_5'
     * @param  string  $distribution  'random' | 'itn' | 'itn_balanced' | 'age' | 'ladies_apart'
     * @param  array{partners: array<string, int>, opponents: array<string, int>}  $history  from HistoryService::build()
     * @param  list<array{a: int|string, b: int|string}>  $avoidPairs
     * @param  callable(array<int, mixed>): array<int, mixed>  $shuffle
     * @param  callable(int, int): int  $randomInt
     * @param  list<int>  $excludedFillerIds  participant ids formExtraGroup() may never use as a filler (second
     *                                        match) - see GroupingService::formExtraGroup()
     * @return array{groups: list<list<int>>, matches: list<array{group: int, match_no: int, team_a: array{int, int}, team_b: array{int, int}, is_extra: bool}>}
     */
    public function propose(
        array $participants,
        string $groupSizeMode,
        string $distribution,
        array $history,
        array $avoidPairs,
        callable $shuffle,
        callable $randomInt,
        array $excludedFillerIds = [],
    ): array {
        $sizing = $this->grouping->groupSizes(count($participants), $groupSizeMode);
        $shuffled = $shuffle($participants);
        $leftoverIds = [];

        if ($sizing['leftover'] > 0) {
            $leftovers = array_splice($shuffled, count($shuffled) - $sizing['leftover']);

            foreach ($leftovers as $p) {
                $leftoverIds[] = $p['id'];
            }
        }

        $groups = $this->grouping->initialAssignment($shuffled, $sizing['sizes'], $distribution, $shuffle);
        $groups = $this->grouping->repair($groups, $history, $avoidPairs, $randomInt);

        $extraGroupIndex = null;

        if ($leftoverIds) {
            $extra = $this->grouping->formExtraGroup($groups, $leftoverIds, $excludedFillerIds);
            $groups[] = $extra['group'];
            $extraGroupIndex = count($groups) - 1;
        }

        $matches = [];

        foreach ($groups as $groupIndex => $group) {
            foreach ($this->rotation->matches(count($group)) as $matchNo => $match) {
                $matches[] = [
                    'group' => $groupIndex,
                    'match_no' => $matchNo,
                    'team_a' => [$group[$match['teamA'][0]], $group[$match['teamA'][1]]],
                    'team_b' => [$group[$match['teamB'][0]], $group[$match['teamB'][1]]],
                    'is_extra' => $groupIndex === $extraGroupIndex,
                ];
            }
        }

        return ['groups' => $groups, 'matches' => $matches];
    }
}
