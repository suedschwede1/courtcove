<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\MixedTournament;

use App\Models\User;
use App\Modules\MixedTournament\Database\Seeders\MixedDemoSeeder;
use App\Modules\MixedTournament\Models\MixedAvoidPair;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedEventGrant;
use App\Modules\MixedTournament\Models\MixedMatch;
use App\Modules\MixedTournament\Models\MixedMember;
use App\Modules\MixedTournament\Models\MixedParticipant;
use App\Modules\MixedTournament\Models\MixedRound;
use App\Modules\MixedTournament\Models\MixedRoundPlayer;
use App\Modules\MixedTournament\Preflight\MixedPreflight;
use App\Modules\MixedTournament\Staging\MixedScrub;
use App\Modules\TournamentBase\Preflight\PreflightResult;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Random\Randomizer;
use Tests\TestCase;

class MixedDemoSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function americano(): MixedEvent
    {
        return MixedEvent::query()->where('title', 'Demo-Mixed')->firstOrFail();
    }

    private function mexicano(): MixedEvent
    {
        return MixedEvent::query()->where('title', 'Demo-Mexicano')->firstOrFail();
    }

    #[Test]
    public function seeds_both_demo_events(): void
    {
        $this->seed(MixedDemoSeeder::class);

        $a = $this->americano();
        $this->assertSame('americano', $a->mode);
        $this->assertTrue($a->is_default);
        $this->assertSame(3, $a->court_count);
        $this->assertSame('2027-04-17', $a->event_date);
        $this->assertCount(13, MixedParticipant::query()->where('event_id', $a->id)->get());
        $this->assertSame(1, MixedParticipant::query()->where('event_id', $a->id)->where('status', 'paused')->count());
        $this->assertSame(1, MixedParticipant::query()->where('event_id', $a->id)->where('no_extra_match', 1)->count());
        $this->assertSame(1, MixedAvoidPair::query()->where('event_id', $a->id)->count());
        $this->assertEqualsCanonicalizing(['admin', 'score'], MixedEventGrant::query()->where('event_id', $a->id)->pluck('role')->all());

        $rounds = MixedRound::query()->where('event_id', $a->id)->orderBy('round_no')->get();
        $this->assertSame(['completed', 'confirmed'], $rounds->pluck('status')->all());
        $this->assertSame(['4_only', '4_and_5'], $rounds->pluck('group_size_mode')->all());
        $this->assertSame(['itn_balanced', 'random'], $rounds->pluck('distribution')->all());

        $first = MixedMatch::query()->where('round_id', $rounds[0]->id)->orderBy('group_no')->orderBy('match_no')->orderBy('id')->get();
        $this->assertSame(0, $first->whereNull('games_a')->count());
        $this->assertSame(['3:1', '2:2', '4:0', '1:3'], array_slice($first->map(fn ($m): string => "{$m->games_a}:{$m->games_b}")->all(), 0, 4));

        $second = MixedMatch::query()->where('round_id', $rounds[1]->id)->get();
        $this->assertGreaterThan(0, $second->whereNull('games_a')->count());
        $this->assertGreaterThan(0, $second->whereNotNull('games_a')->count());

        $m = $this->mexicano();
        $this->assertSame('mexicano', $m->mode);
        $this->assertSame(2, $m->court_count);
        $this->assertSame('2027-05-08', $m->event_date);
        $this->assertCount(14, MixedParticipant::query()->where('event_id', $m->id)->get());
        $this->assertSame(
            ['completed', 'proposed'],
            MixedRound::query()->where('event_id', $m->id)->orderBy('round_no')->pluck('status')->all()
        );

        $test = MixedEvent::query()->where('title', 'Demo-Test')->firstOrFail();
        $this->assertTrue($test->is_test);
        $this->assertFalse($test->is_default);
    }

    #[Test]
    public function americano_demo_has_fillers_with_two_rows(): void
    {
        $this->seed(MixedDemoSeeder::class);

        $round = MixedRound::query()->where('event_id', $this->americano()->id)->where('round_no', 1)->firstOrFail();
        $perParticipant = MixedRoundPlayer::query()->where('round_id', $round->id)->orderBy('id')->get()->groupBy('participant_id');

        $this->assertGreaterThan(0, $perParticipant->filter(fn ($rows): bool => $rows->count() === 2)->count());
        $this->assertSame(0, $perParticipant->filter(fn ($rows): bool => $rows->count() > 2)->count());
        $this->assertGreaterThan(0, MixedMatch::query()->where('round_id', $round->id)->where('is_extra', 1)->count());
    }

    #[Test]
    public function mexicano_demo_has_sitters_with_null_group(): void
    {
        $this->seed(MixedDemoSeeder::class);

        $round = MixedRound::query()->where('event_id', $this->mexicano()->id)->where('round_no', 2)->firstOrFail();
        $sitters = MixedRoundPlayer::query()->where('round_id', $round->id)->whereNull('group_no')->get();

        $this->assertCount(2, $sitters);
        $this->assertSame(2, $sitters->pluck('participant_id')->unique()->count());
        $this->assertSame(3, MixedMatch::query()->where('round_id', $round->id)->count());
    }

    #[Test]
    public function only_example_test_users_are_created(): void
    {
        $this->seed(MixedDemoSeeder::class);

        $this->assertSame(
            ['mixed-demo-admin@example.test', 'mixed-demo-score@example.test'],
            User::query()->orderBy('email')->pluck('email')->all()
        );
        $this->assertSame(
            ['Demo Ergebnisdienst', 'Demo Turnierleitung'],
            User::query()->orderBy('alias')->pluck('alias')->all()
        );
        foreach (User::query()->get() as $user) {
            $this->assertSame('enabled', $user->status);
        }
    }

    #[Test]
    public function roster_is_fictional(): void
    {
        $this->seed(MixedDemoSeeder::class);

        $this->assertSame(30, MixedMember::query()->count());
        foreach (MixedMember::query()->get() as $member) {
            $this->assertStringStartsWith('Beispiel ', $member->label);
            $this->assertContains($member->gender, ['m', 'f']);
            $this->assertGreaterThanOrEqual(3.0, (float) $member->itn);
            $this->assertLessThanOrEqual(9.5, (float) $member->itn);
            $this->assertGreaterThanOrEqual(1960, (int) substr((string) $member->birth_date, 0, 4));
            $this->assertLessThanOrEqual(2008, (int) substr((string) $member->birth_date, 0, 4));
        }
        foreach (MixedParticipant::query()->get() as $participant) {
            $this->assertStringStartsWith('Beispiel ', $participant->label);
        }
    }

    #[Test]
    public function seeding_twice_after_truncate_is_stable(): void
    {
        $this->seed(MixedDemoSeeder::class);
        $first = $this->snapshot();

        foreach ((new MixedScrub)->tables() as $table) {
            DB::table($table)->truncate();
        }

        $this->seed(MixedDemoSeeder::class);

        $this->assertSame($first, $this->snapshot());
        $this->assertSame(1, User::query()->where('email', 'mixed-demo-admin@example.test')->count());
    }

    #[Test]
    public function seeder_keeps_a_previously_bound_randomizer(): void
    {
        $mine = new Randomizer;
        $this->app->instance(Randomizer::class, $mine);

        $this->seed(MixedDemoSeeder::class);

        $this->assertSame($mine, $this->app->make(Randomizer::class));
    }

    #[Test]
    public function preflight_passes_on_demo_data(): void
    {
        $this->seed(MixedDemoSeeder::class);

        $lines = array_map(fn (PreflightResult $r): string => $r->line(), app(MixedPreflight::class)->run());

        $this->assertSame([], array_values(array_filter($lines, fn (string $l): bool => str_starts_with($l, '[FAIL]'))));
        $this->assertSame([], array_values(array_filter($lines, fn (string $l): bool => str_starts_with($l, '[WARN]'))));
    }

    #[Test]
    public function scrub_is_registered(): void
    {
        $this->assertContains('mixed', collect($this->app->tagged('phase2.scrub'))->map->module()->all());
        $this->assertSame(
            ['bs_mixed_event_grants', 'bs_mixed_matches', 'bs_mixed_round_players', 'bs_mixed_rounds', 'bs_mixed_avoid_pairs', 'bs_mixed_participants', 'bs_mixed_events', 'bs_mixed_members'],
            (new MixedScrub)->tables()
        );
        $this->assertSame(MixedDemoSeeder::class, (new MixedScrub)->seeder());
    }

    #[Test]
    public function scrub_truncates_mixed_tables_and_reseeds(): void
    {
        DB::connection()->setDatabaseName('booking_staging');
        config(['tournament-base.live_database' => 'fictional_live']);
        $this->app['env'] = 'production';
        $event = MixedEvent::factory()->create(['title' => 'Echtes Turnier']);
        MixedParticipant::factory()->create(['event_id' => $event->id, 'label' => 'Roster Echt']);
        MixedMember::factory()->create(['label' => 'Roster Echt']);
        $versions = DB::table('bs_mixed_schema_versions')->pluck('version')->all();
        $this->assertNotEmpty($versions);

        $this->artisan('phase2:scrub-staging', ['--force' => true, '--no-interaction' => true])
            ->assertExitCode(0);

        $this->assertFalse(MixedMember::query()->where('label', 'Roster Echt')->exists());
        $this->assertFalse(MixedParticipant::query()->where('label', 'Roster Echt')->exists());
        $this->assertFalse(MixedEvent::query()->where('title', 'Echtes Turnier')->exists());
        $this->assertTrue(MixedEvent::query()->where('title', 'Demo-Mixed')->exists());
        $this->assertSame($versions, DB::table('bs_mixed_schema_versions')->pluck('version')->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        return [
            'events' => MixedEvent::query()->orderBy('id')->get(['title', 'mode', 'status', 'is_default', 'is_test', 'court_count'])->toArray(),
            'participants' => MixedParticipant::query()->orderBy('id')->get(['event_id', 'label', 'gender', 'itn', 'age', 'status'])->toArray(),
            'rounds' => MixedRound::query()->orderBy('id')->get(['event_id', 'round_no', 'status'])->toArray(),
            'players' => MixedRoundPlayer::query()->orderBy('id')->get(['round_id', 'participant_id', 'group_no'])->toArray(),
            'matches' => MixedMatch::query()->orderBy('id')
                ->get(['round_id', 'group_no', 'match_no', 'team_a_p1', 'team_a_p2', 'team_b_p1', 'team_b_p2', 'games_a', 'games_b', 'is_extra'])->toArray(),
        ];
    }
}
