<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Services;

/**
 * Wave planning: when not all groups of a round fit on the courts at the same
 * time, they play one after the other.
 *
 * A class of its own and not inline code in the views: the calculation is
 * needed in four places (round view and scorecard, admin and public). If it
 * ran twice, a change in one place would make the printout show another wave
 * plan than the screen - and the symptom is people standing on the wrong
 * court.
 *
 * Deliberately without constructor dependencies and without an event.
 */
final class WaveService
{
    /**
     * Americano: group -> wave.
     *
     * @param  array<int, list<array<string, mixed>>>  $byGroup  matches grouped by group_no
     * @param  int  $courtCount  number of playable courts
     * @return array<int, int> groupNo => wave, waves start at 1
     */
    public function americanoWaves(array $byGroup, int $courtCount): array
    {
        $courtCount = max(1, $courtCount);
        $groupSizes = $this->groupSizes($byGroup);
        $regularGroupNos = [];
        $extraGroupNos = [];

        foreach ($byGroup as $groupNo => $groupMatches) {
            if (! empty($groupMatches[0]['is_extra'])) {
                $extraGroupNos[] = $groupNo;
            } else {
                $regularGroupNos[] = $groupNo;
            }
        }

        // LARGE GROUPS FIRST: a group of five plays five matches, a group of
        // four three. If the group of five ran in the last wave, everybody
        // else would wait for it at the end; started in the first wave, its
        // extra time runs in parallel. With equal sizes the group number
        // decides so the split is the same on every call.
        usort($regularGroupNos, function (int $a, int $b) use ($groupSizes): int {
            if ($groupSizes[$a] !== $groupSizes[$b]) {
                return $groupSizes[$b] - $groupSizes[$a];
            }

            return $a - $b;
        });

        $waveByGroup = [];

        foreach ($regularGroupNos as $index => $groupNo) {
            $waveByGroup[$groupNo] = intdiv($index, $courtCount) + 1;
        }

        // The extra group ALWAYS gets the last wave: its fillers have to have
        // finished their home group first, whatever wave that one played in.
        if ($extraGroupNos) {
            $lastWave = $regularGroupNos
                ? intdiv(count($regularGroupNos) - 1, $courtCount) + 1
                : 0;

            foreach ($extraGroupNos as $groupNo) {
                $waveByGroup[$groupNo] = $lastWave + 1;
            }
        }

        return $waveByGroup;
    }

    /**
     * Mexicano: group -> wave and court.
     *
     * Unlike Americano there is no extra group and there are no groups of
     * five, so the assignment is direct and a court can be assigned.
     *
     * @param  array<int, list<array<string, mixed>>>  $byGroup
     * @return array<int, array{wave: int, court: int}>
     */
    public function mexicanoSlots(array $byGroup, int $courtCount): array
    {
        $courtCount = max(1, $courtCount);
        $slots = [];
        $index = 0;

        foreach ($byGroup as $groupNo => $groupMatches) {
            $slots[$groupNo] = [
                'wave' => intdiv($index, $courtCount) + 1,
                'court' => $index % $courtCount + 1,
            ];
            $index++;
        }

        return $slots;
    }

    /**
     * Highest wave number of an assignment, 0 for an empty round.
     *
     * @param  array<int, int|array{wave: int, court: int}>  $waveByGroup  result of americanoWaves() OR mexicanoSlots()
     */
    public function waveCount(array $waveByGroup): int
    {
        if (! $waveByGroup) {
            return 0;
        }

        $first = reset($waveByGroup);
        $waves = is_array($first)
            ? array_column($waveByGroup, 'wave')
            : $waveByGroup;

        return (int) max($waves);
    }

    /**
     * Number of players per group - DISTINCT players over all matches of the
     * group are counted, not matches. After a player swap the group size can
     * change, which is why it is computed here and not taken from the
     * grouping strategy.
     *
     * @param  array<int, list<array<string, mixed>>>  $byGroup
     * @return array<int, int>
     */
    private function groupSizes(array $byGroup): array
    {
        $sizes = [];

        foreach ($byGroup as $groupNo => $groupMatches) {
            $seen = [];

            foreach ($groupMatches as $match) {
                foreach (['team_a_p1', 'team_a_p2', 'team_b_p1', 'team_b_p2'] as $slot) {
                    $seen[(int) $match[$slot]] = true;
                }
            }

            $sizes[$groupNo] = count($seen);
        }

        return $sizes;
    }
}
