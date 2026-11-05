<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\MixedTournament;

use App\Modules\MixedTournament\Domain\MemberImportService;
use App\Modules\MixedTournament\Domain\MixedRuleException;
use App\Modules\MixedTournament\Domain\ParticipantService;
use App\Modules\MixedTournament\Models\MixedAvoidPair;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedMember;
use App\Modules\MixedTournament\Models\MixedParticipant;
use App\Modules\MixedTournament\Models\MixedRoundPlayer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ParticipantServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): ParticipantService
    {
        return $this->app->make(ParticipantService::class);
    }

    private function importer(): MemberImportService
    {
        return $this->app->make(MemberImportService::class);
    }

    private function event(): MixedEvent
    {
        return MixedEvent::factory()->create(['event_date' => '2027-04-10']);
    }

    /** @return array{label: string, gender: ?string, itn: ?float, age: ?int, no_extra_match: bool} */
    private function input(string $label, ?string $gender = null, ?float $itn = null, ?int $age = null): array
    {
        return ['label' => $label, 'gender' => $gender, 'itn' => $itn, 'age' => $age, 'no_extra_match' => false];
    }

    private function roster(): MixedMember
    {
        return MixedMember::factory()->create([
            'label' => 'Beispiel Anna', 'gender' => 'f', 'itn' => '6.5', 'birth_date' => '1990-05-01',
        ]);
    }

    #[Test]
    public function add_fills_missing_fields_from_the_member_roster(): void
    {
        $this->roster();
        $event = $this->event();

        $p = $this->service()->add($event, $this->input('Beispiel Anna'))->fresh();

        $this->assertSame('f', $p->gender);
        $this->assertEquals(6.5, $p->itn);
        $this->assertSame(36, $p->age);
        $this->assertSame('active', $p->status);
        $this->assertNull($p->uid);

        $given = $this->service()->add($event, $this->input('Beispiel Anna', 'm', 9.0, 50))->fresh();

        $this->assertSame('m', $given->gender);
        $this->assertEquals(9.0, $given->itn);
        $this->assertSame(50, $given->age);
    }

    #[Test]
    public function add_without_a_roster_match_keeps_the_fields_empty(): void
    {
        $p = $this->service()->add($this->event(), $this->input('Spieler 01'))->fresh();

        $this->assertNull($p->gender);
        $this->assertNull($p->itn);
        $this->assertNull($p->age);
    }

    #[Test]
    public function refresh_fills_only_null_fields_and_counts(): void
    {
        $this->roster();
        $event = $this->event();
        $needs = MixedParticipant::factory()->create(['event_id' => $event->id, 'label' => 'Beispiel Anna', 'gender' => null, 'itn' => '8.0', 'age' => null]);
        $complete = MixedParticipant::factory()->create(['event_id' => $event->id, 'label' => 'Beispiel Anna', 'gender' => 'm', 'itn' => '9.0', 'age' => 50]);
        $unknown = MixedParticipant::factory()->create(['event_id' => $event->id, 'label' => 'Spieler 01']);

        $this->assertSame(1, $this->service()->refreshFromMembers($event));

        $needs = $needs->fresh();
        $this->assertSame('f', $needs->gender);
        $this->assertEquals(8.0, $needs->itn);
        $this->assertSame(36, $needs->age);
        $this->assertSame(50, $complete->fresh()->age);
        $this->assertNull($unknown->fresh()->gender);
        $this->assertSame(0, $this->service()->refreshFromMembers($event));
    }

    #[Test]
    public function update_and_set_status(): void
    {
        $p = MixedParticipant::factory()->create(['event_id' => $this->event()->id, 'label' => 'Spieler 01']);

        $this->service()->update($p, ['label' => 'Spieler 02', 'gender' => 'f', 'itn' => 7.5, 'age' => 30, 'no_extra_match' => true]);
        $p = $p->fresh();
        $this->assertSame('Spieler 02', $p->label);
        $this->assertSame('f', $p->gender);
        $this->assertTrue($p->no_extra_match);

        $this->service()->setStatus($p, 'paused');
        $this->assertSame('paused', $p->fresh()->status);
    }

    #[Test]
    public function pause_all_only_touches_active(): void
    {
        $event = $this->event();
        $other = $this->event();
        $active = MixedParticipant::factory()->create(['event_id' => $event->id, 'status' => 'active']);
        $paused = MixedParticipant::factory()->create(['event_id' => $event->id, 'status' => 'paused']);
        $foreign = MixedParticipant::factory()->create(['event_id' => $other->id, 'status' => 'active']);

        $this->service()->pauseAll($event);

        $this->assertSame('paused', $active->fresh()->status);
        $this->assertSame('paused', $paused->fresh()->status);
        $this->assertSame('active', $foreign->fresh()->status);
    }

    #[Test]
    public function remove_unused_participant_deletes_it(): void
    {
        $p = MixedParticipant::factory()->create(['event_id' => $this->event()->id]);

        $this->service()->remove($p);

        $this->assertSame(0, MixedParticipant::query()->count());
    }

    #[Test]
    public function remove_used_participant_is_refused(): void
    {
        $p = MixedParticipant::factory()->create(['event_id' => $this->event()->id]);
        MixedRoundPlayer::query()->create(['round_id' => 1, 'participant_id' => $p->id, 'group_no' => null]);

        try {
            $this->service()->remove($p);
            $this->fail('expected MixedRuleException');
        } catch (MixedRuleException $e) {
            $this->assertSame('participant_in_use', $e->key);
        }

        $this->assertSame(1, MixedParticipant::query()->count());
        $this->assertSame('Verwendete Teilnehmer können nicht gelöscht, sondern nur pausiert werden.', __('mixed::messages.participant_in_use', [], 'de'));
    }

    #[Test]
    public function avoid_pair_is_normalised_and_scoped(): void
    {
        $event = $this->event();
        $other = $this->event();
        $a = MixedParticipant::factory()->create(['event_id' => $event->id]);
        $b = MixedParticipant::factory()->create(['event_id' => $event->id]);
        $foreign = MixedParticipant::factory()->create(['event_id' => $other->id]);

        $this->service()->addAvoidPair($event, $b->id, $a->id);

        $pair = MixedAvoidPair::query()->sole();
        $this->assertSame([$a->id, $b->id], [$pair->participant_a, $pair->participant_b]);

        foreach ([[$a->id, $foreign->id], [$a->id, $a->id], [$a->id, 9999]] as [$x, $y]) {
            try {
                $this->service()->addAvoidPair($event, $x, $y);
                $this->fail('expected MixedRuleException');
            } catch (MixedRuleException $e) {
                $this->assertSame('avoid_pair_invalid', $e->key);
            }
        }
        $this->assertSame(1, MixedAvoidPair::query()->count());
    }

    #[Test]
    public function adding_the_same_pair_twice_keeps_one_row(): void
    {
        $event = $this->event();
        $a = MixedParticipant::factory()->create(['event_id' => $event->id]);
        $b = MixedParticipant::factory()->create(['event_id' => $event->id]);

        $this->service()->addAvoidPair($event, $a->id, $b->id);
        $this->service()->addAvoidPair($event, $b->id, $a->id);

        $this->assertSame(1, MixedAvoidPair::query()->count());
    }

    #[Test]
    public function remove_avoid_pair_of_another_event_is_ignored(): void
    {
        $event = $this->event();
        $other = $this->event();
        $a = MixedParticipant::factory()->create(['event_id' => $event->id]);
        $b = MixedParticipant::factory()->create(['event_id' => $event->id]);
        $this->service()->addAvoidPair($event, $a->id, $b->id);
        $pairId = MixedAvoidPair::query()->sole()->id;

        $this->service()->removeAvoidPair($other, $pairId);
        $this->assertSame(1, MixedAvoidPair::query()->count());

        $this->service()->removeAvoidPair($event, $pairId);
        $this->assertSame(0, MixedAvoidPair::query()->count());
    }

    #[Test]
    public function member_search_needs_two_chars_and_limits(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            MixedMember::factory()->create(['label' => 'Beispiel '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }
        $event = $this->event();

        $this->assertSame([], $this->service()->searchMembers($event, 'B'));
        $this->assertSame([], $this->service()->searchMembers($event, ' B '));

        $rows = $this->service()->searchMembers($event, 'Beis');
        $this->assertCount(20, $rows);
        $this->assertSame('Beispiel 01', $rows[0]['label']);
        $this->assertSame('Beispiel 20', $rows[19]['label']);
        $this->assertCount(5, $this->service()->searchMembers($event, 'Beis', 5));
    }

    #[Test]
    public function member_search_returns_typed_fields_and_age_at_the_event_date(): void
    {
        $this->roster();

        $rows = $this->service()->searchMembers($this->event(), 'Anna');

        $this->assertSame([['label' => 'Beispiel Anna', 'gender' => 'f', 'itn' => 6.5, 'age' => 36]], $rows);
    }

    #[Test]
    public function member_search_escapes_wildcards(): void
    {
        MixedMember::factory()->create(['label' => 'Beispiel 01']);
        MixedMember::factory()->create(['label' => '100% Spieler']);
        $event = $this->event();

        $this->assertSame([], $this->service()->searchMembers($event, '%_'));
        $this->assertSame([], $this->service()->searchMembers($event, '__'));
        $this->assertSame(['100% Spieler'], array_column($this->service()->searchMembers($event, '0%'), 'label'));
    }

    private function csv(array $rows): string
    {
        $lines = ['Nachname;Vorname;Geschlecht;ITN;GebDatum'];
        foreach ($rows as $row) {
            $lines[] = implode(';', $row);
        }

        return implode("\n", $lines);
    }

    #[Test]
    public function import_replaces_the_roster_in_one_transaction(): void
    {
        MixedMember::factory()->count(3)->create();

        $count = $this->importer()->import($this->csv([
            ['Beispiel', 'Anna', 'weiblich', 'ITN 6,5', '01.05.1990'],
            ['Beispiel', 'Bernd', 'maennlich', '', ''],
        ]));

        $this->assertSame(2, $count);
        $this->assertSame(2, $this->importer()->count());
        $this->assertSame(['Beispiel Anna', 'Beispiel Bernd'], MixedMember::query()->orderBy('id')->pluck('label')->all());
        $anna = MixedMember::query()->where('label', 'Beispiel Anna')->sole();
        $this->assertSame('f', $anna->gender);
        $this->assertSame('1990-05-01', $anna->birth_date);
    }

    #[Test]
    public function import_failure_keeps_the_old_roster(): void
    {
        MixedMember::factory()->count(3)->create();
        $created = 0;
        MixedMember::creating(function () use (&$created): void {
            if (++$created === 2) {
                throw new RuntimeException('second row fails');
            }
        });

        try {
            $this->importer()->import($this->csv([
                ['Beispiel', 'Anna', '', '', ''],
                ['Beispiel', 'Bernd', '', '', ''],
                ['Beispiel', 'Clara', '', '', ''],
            ]));
            $this->fail('expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertSame('second row fails', $e->getMessage());
        } finally {
            MixedMember::flushEventListeners();
        }

        $this->assertSame(3, $this->importer()->count());
        $this->assertSame(0, MixedMember::query()->where('label', 'like', 'Beispiel %')->count());
    }

    #[Test]
    public function import_reports_a_missing_column(): void
    {
        MixedMember::factory()->count(2)->create();

        try {
            $this->importer()->import("Name;Vorname\nBeispiel;Anna");
            $this->fail('expected MixedRuleException');
        } catch (MixedRuleException $e) {
            $this->assertSame('csv_column_missing', $e->key);
            $this->assertSame(['column' => 'Nachname'], $e->replace);
            $this->assertSame('Spalte "Nachname" fehlt im CSV-Export.', __('mixed::messages.csv_column_missing', $e->replace, 'de'));
        }

        $this->assertSame(2, $this->importer()->count());
    }

    #[Test]
    public function import_skips_rows_without_a_label_but_reports_the_parsed_count(): void
    {
        $count = $this->importer()->import($this->csv([
            ['Beispiel', 'Anna', '', '', ''],
            ['', '', '', '', ''],
        ]));

        $this->assertSame(2, $count);
        $this->assertSame(1, $this->importer()->count());
    }
}
