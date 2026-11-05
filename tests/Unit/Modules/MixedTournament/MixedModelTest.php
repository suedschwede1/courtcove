<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\MixedTournament;

use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedMatch;
use App\Modules\MixedTournament\Models\MixedMember;
use App\Modules\MixedTournament\Models\MixedParticipant;
use App\Modules\MixedTournament\Models\MixedRound;
use App\Modules\MixedTournament\Models\MixedRoundPlayer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MixedModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function raw_date_columns_stay_strings(): void
    {
        $event = MixedEvent::factory()->create(['event_date' => '2027-04-10', 'created' => '2027-04-01 09:00:00']);
        $member = MixedMember::factory()->create(['birth_date' => '1990-05-01', 'itn' => '6.5']);

        $this->assertSame('2027-04-10', $event->fresh()->event_date);
        $this->assertSame('2027-04-01 09:00:00', $event->fresh()->created);
        $this->assertSame('1990-05-01', $member->fresh()->birth_date);
    }

    #[Test]
    public function matches_are_ordered_by_group_then_match(): void
    {
        $round = MixedRound::factory()->create();
        foreach ([[2, 1], [1, 2], [1, 1], [2, 2]] as [$group, $match]) {
            MixedMatch::factory()->create(['round_id' => $round->id, 'group_no' => $group, 'match_no' => $match]);
        }

        $this->assertSame(
            [[1, 1], [1, 2], [2, 1], [2, 2]],
            $round->matches->map(fn (MixedMatch $m): array => [$m->group_no, $m->match_no])->all(),
        );
    }

    #[Test]
    public function players_are_ordered_by_id(): void
    {
        $round = MixedRound::factory()->create();
        foreach ([9, 3, 5] as $participantId) {
            MixedRoundPlayer::query()->create(['round_id' => $round->id, 'participant_id' => $participantId, 'group_no' => 1]);
        }

        $this->assertSame([9, 3, 5], $round->players->pluck('participant_id')->all());
    }

    #[Test]
    public function event_relations_are_ordered_by_id(): void
    {
        $event = MixedEvent::factory()->create();
        MixedParticipant::factory()->count(3)->create(['event_id' => $event->id]);
        MixedRound::factory()->create(['event_id' => $event->id, 'round_no' => 2]);
        MixedRound::factory()->create(['event_id' => $event->id, 'round_no' => 1]);

        $ids = $event->participants->pluck('id')->all();
        $sorted = $ids;
        sort($sorted);
        $this->assertSame($sorted, $ids);
        $this->assertSame([2, 1], $event->rounds->pluck('round_no')->all());
        $this->assertFalse($event->isMexicano());
        $this->assertTrue(MixedEvent::factory()->make(['mode' => 'mexicano'])->isMexicano());
    }

    #[Test]
    public function round_status_helpers(): void
    {
        $this->assertTrue(MixedRound::factory()->make(['status' => 'proposed'])->isProposed());
        $this->assertTrue(MixedRound::factory()->make(['status' => 'confirmed'])->isConfirmed());
        $this->assertTrue(MixedRound::factory()->make(['status' => 'completed'])->isCompleted());
        $this->assertFalse(MixedRound::factory()->make(['status' => 'completed'])->isProposed());
    }

    #[Test]
    public function itn_float_converts_or_returns_null(): void
    {
        $this->assertSame(6.5, MixedParticipant::factory()->create(['itn' => '6.5'])->fresh()->itnFloat());
        $this->assertNull(MixedParticipant::factory()->create()->fresh()->itnFloat());
    }

    #[Test]
    public function boolean_flags_round_trip_as_0_1_in_the_table(): void
    {
        $event = MixedEvent::factory()->create(['is_default' => true, 'is_test' => false]);
        $participant = MixedParticipant::factory()->create(['no_extra_match' => true]);
        $match = MixedMatch::factory()->create(['is_extra' => true]);

        $this->assertSame(1, (int) DB::table('bs_mixed_events')->where('id', $event->id)->value('is_default'));
        $this->assertSame(0, (int) DB::table('bs_mixed_events')->where('id', $event->id)->value('is_test'));
        $this->assertSame(1, (int) DB::table('bs_mixed_participants')->where('id', $participant->id)->value('no_extra_match'));
        $this->assertSame(1, (int) DB::table('bs_mixed_matches')->where('id', $match->id)->value('is_extra'));
        $this->assertTrue($event->fresh()->is_default);
        $this->assertFalse($event->fresh()->is_test);
    }
}
