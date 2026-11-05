<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Domain;

use App\Modules\MixedTournament\Models\MixedAvoidPair;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedMember;
use App\Modules\MixedTournament\Models\MixedParticipant;
use App\Modules\MixedTournament\Models\MixedRoundPlayer;
use App\Modules\MixedTournament\Services\AgeCalculator;

/**
 * Participants, avoid pairs and the member picker search
 * (old MixedManager::addParticipant() ... removeAvoidPair()).
 *
 * `$input` is the shape of ParticipantInput::parse():
 * label, gender (m|f|null), itn (float|null), age (int|null), no_extra_match.
 */
final class ParticipantService
{
    public function __construct(private readonly AgeCalculator $age) {}

    /**
     * Gender, ITN and age that were left empty are filled from the member
     * roster entry with exactly this label; given values are never overwritten.
     *
     * @param  array{label: string, gender?: ?string, itn?: ?float, age?: ?int, no_extra_match?: bool}  $input
     */
    public function add(MixedEvent $event, array $input): MixedParticipant
    {
        $gender = $input['gender'] ?? null;
        $itn = $input['itn'] ?? null;
        $age = $input['age'] ?? null;

        if ($gender === null || $itn === null || $age === null) {
            $member = $this->memberByLabel($input['label']);
            if ($member !== null) {
                $gender ??= $member->gender;
                $itn ??= $member->itn !== null ? (float) $member->itn : null;
                $age ??= $this->age->ageFromBirthDate($member->birth_date, (string) $event->event_date);
            }
        }

        return MixedParticipant::query()->create([
            'event_id' => $event->id,
            'uid' => null,
            'label' => $input['label'],
            'gender' => $gender,
            'itn' => $itn,
            'age' => $this->age->normalizeAge($age),
            'status' => 'active',
            'no_extra_match' => ! empty($input['no_extra_match']) ? 1 : 0,
        ]);
    }

    /** @param  array{label: string, gender?: ?string, itn?: ?float, age?: ?int, no_extra_match?: bool}  $input */
    public function update(MixedParticipant $participant, array $input): void
    {
        $participant->update([
            'label' => $input['label'],
            'gender' => $input['gender'] ?? null,
            'itn' => $input['itn'] ?? null,
            'age' => $this->age->normalizeAge($input['age'] ?? null),
            'no_extra_match' => ! empty($input['no_extra_match']) ? 1 : 0,
        ]);
    }

    /** Pausing instead of deleting: paused participants are only not preselected for a new round. */
    public function setStatus(MixedParticipant $participant, string $status): void
    {
        $participant->update(['status' => $status]);
    }

    public function pauseAll(MixedEvent $event): void
    {
        MixedParticipant::query()
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->update(['status' => 'paused']);
    }

    /** @throws MixedRuleException participant_in_use */
    public function remove(MixedParticipant $participant): void
    {
        if (MixedRoundPlayer::query()->where('participant_id', $participant->id)->exists()) {
            throw new MixedRuleException('participant_in_use');
        }

        $participant->delete();
    }

    /**
     * "Determine ITN + age": only fields that are still empty are filled from
     * the member roster (by exact label).
     *
     * @return int number of updated participants
     */
    public function refreshFromMembers(MixedEvent $event): int
    {
        $updated = 0;

        $participants = MixedParticipant::query()->where('event_id', $event->id)->orderBy('id')->get();

        foreach ($participants as $participant) {
            if ($participant->gender !== null && $participant->itn !== null && $participant->age !== null) {
                continue;
            }

            $member = $this->memberByLabel((string) $participant->label);
            if ($member === null) {
                continue;
            }

            $changes = [];
            if ($participant->gender === null && $member->gender !== null) {
                $changes['gender'] = $member->gender;
            }
            if ($participant->itn === null && $member->itn !== null) {
                $changes['itn'] = $member->itn;
            }
            if ($participant->age === null && $member->birth_date !== null) {
                $changes['age'] = $this->age->ageFromBirthDate($member->birth_date, (string) $event->event_date);
            }

            if ($changes !== []) {
                $participant->update($changes);
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * One opponent block, stored as (min, max). A pair that already exists is
     * left as it is.
     *
     * @throws MixedRuleException avoid_pair_invalid
     */
    public function addAvoidPair(MixedEvent $event, int $a, int $b): void
    {
        $low = min($a, $b);
        $high = max($a, $b);

        $inEvent = fn (int $id): bool => MixedParticipant::query()->where('id', $id)->where('event_id', $event->id)->exists();

        if ($low === $high || ! $inEvent($low) || ! $inEvent($high)) {
            throw new MixedRuleException('avoid_pair_invalid');
        }

        MixedAvoidPair::query()->firstOrCreate([
            'event_id' => $event->id,
            'participant_a' => $low,
            'participant_b' => $high,
        ]);
    }

    public function removeAvoidPair(MixedEvent $event, int $pairId): void
    {
        MixedAvoidPair::query()->where('id', $pairId)->where('event_id', $event->id)->delete();
    }

    /**
     * Authenticated member picker search.
     *
     * @return list<array{label: string, gender: ?string, itn: ?float, age: ?int}>
     */
    public function searchMembers(MixedEvent $event, string $q, int $limit = 20): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';

        return MixedMember::query()
            ->whereRaw("label LIKE ? ESCAPE '!'", [$like])
            ->orderBy('label')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (MixedMember $m): array => [
                'label' => (string) $m->label,
                'gender' => $m->gender,
                'itn' => $m->itn !== null ? (float) $m->itn : null,
                'age' => $this->age->ageFromBirthDate($m->birth_date, (string) $event->event_date),
            ])
            ->values()
            ->all();
    }

    private function memberByLabel(string $label): ?MixedMember
    {
        return MixedMember::query()->where('label', $label)->orderBy('id')->first();
    }
}
