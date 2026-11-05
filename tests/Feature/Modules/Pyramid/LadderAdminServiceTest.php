<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Models\User;
use App\Models\UserMeta;
use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidEvent;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use App\Modules\Pyramid\Services\ChallengeService;
use App\Modules\Pyramid\Services\LadderAdminService;
use App\Modules\Pyramid\Services\ResultService;
use App\Modules\Pyramid\Support\EventText;
use App\Modules\Pyramid\Support\PyramidRuleException;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsPyramidLadders;
use Tests\TestCase;

class LadderAdminServiceTest extends TestCase
{
    use BuildsPyramidLadders;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeSecond();
    }

    private function service(): LadderAdminService
    {
        return $this->app->make(LadderAdminService::class);
    }

    private function waiting(PyramidLadder $ladder, string $label, ?string $itn, array $extra = []): PyramidPlayer
    {
        return PyramidPlayer::factory()->waiting()->create(array_merge([
            'ladder_id' => $ladder->id,
            'label' => $label,
            'itn' => $itn,
            'itn_rated' => $itn ?? '10.0',
        ], $extra));
    }

    private function assertRuleKey(string $key, callable $callback): void
    {
        try {
            $callback();
        } catch (PyramidRuleException $exception) {
            $this->assertSame($key, $exception->verdict->key);

            return;
        }

        $this->fail('Expected a PyramidRuleException with key '.$key);
    }

    /**
     * @return array<string, mixed>
     */
    private function lastEventPayload(int $ladderId, string $type): array
    {
        $event = PyramidEvent::query()->where('ladder_id', $ladderId)->where('type', $type)->orderByDesc('id')->firstOrFail();

        return json_decode((string) $event->payload, true);
    }

    #[Test]
    public function create_ladder_starts_in_setup_with_defaults(): void
    {
        $ladder = $this->service()->createLadder('  Sommer  ', 2027, '');

        $this->assertSame('Sommer', $ladder->title);
        $this->assertSame('setup', $ladder->status);
        $this->assertNull($ladder->category);
        $this->assertSame([7, 3, 7], [(int) $ladder->days_to_play, (int) $ladder->winner_protected_days, (int) $ladder->loser_cooldown_days]);
        $this->assertEquals(10.0, (float) $ladder->itn_default);
        $this->assertSame(0, (int) $ladder->is_default);

        $this->assertRuleKey('pyramid::admin.title_required', fn () => $this->service()->createLadder('   ', 2027, null));
    }

    #[Test]
    public function seed_ranks_waiting_players_and_starts_the_ladder(): void
    {
        $ladder = PyramidLadder::factory()->setup()->create();
        $this->waiting($ladder, 'Spieler A', '7.1');
        $this->waiting($ladder, 'Spieler B', null);
        $this->waiting($ladder, 'Spieler C', '5.7');

        $count = $this->service()->seed($ladder, 42);

        $this->assertSame(3, $count);
        $this->assertSame(['Spieler C' => 1, 'Spieler A' => 2, 'Spieler B' => 3], $this->positionsByLabel((int) $ladder->id));
        $this->assertSame('running', PyramidLadder::query()->findOrFail($ladder->id)->status);
        $this->assertSame(['message' => EventText::SEEDED], $this->lastEventPayload((int) $ladder->id, 'admin'));
        $this->assertSame(3, PyramidPlayer::query()->where('ladder_id', $ladder->id)->where('status', 'active')->count());
        $this->assertRankingIntact((int) $ladder->id);
    }

    #[Test]
    public function seed_refuses_an_existing_ranking(): void
    {
        $ladder = $this->makeLadder(3);
        $this->waiting($ladder, 'Spieler W', null);

        $this->assertRuleKey('pyramid::admin.has_ranking', fn () => $this->service()->seed($ladder, null));
    }

    #[Test]
    public function seed_needs_players(): void
    {
        $ladder = PyramidLadder::factory()->setup()->create();

        $this->assertRuleKey('pyramid::admin.no_players', fn () => $this->service()->seed($ladder, null));
    }

    #[Test]
    public function email_match_overrides_uid_label_and_phone(): void
    {
        $ladder = PyramidLadder::factory()->setup()->create();
        $user = User::factory()->create(['alias' => 'Demo Spieler 07', 'email' => 'demo07@example.test', 'status' => 'enabled']);
        UserMeta::query()->create(['uid' => $user->uid, 'key' => 'phone', 'value' => '+43 000 000 0007']);

        $player = $this->service()->addPlayer($ladder, [
            'label' => 'x', 'uid' => 0, 'phone' => '123', 'email' => 'demo07@example.test',
            'itn' => '7,5', 'gender' => 'f', 'age' => '33',
        ]);

        $this->assertSame((int) $user->uid, (int) $player->uid);
        $this->assertSame('Demo Spieler 07', $player->label);
        $this->assertSame('+43 000 000 0007', $player->phone);
        $this->assertSame('demo07@example.test', $player->email);
        $this->assertSame('waiting', $player->status);
        $this->assertEquals(7.5, (float) $player->itn);
        $this->assertEquals(7.5, (float) $player->itn_rated);
        $this->assertSame('f', $player->gender);
        $this->assertSame(33, (int) $player->age);
        $this->assertNull($player->position);
    }

    #[Test]
    public function email_match_ignores_deleted_accounts(): void
    {
        $ladder = PyramidLadder::factory()->setup()->create();
        User::factory()->create(['alias' => 'Demo Spieler 08', 'email' => 'demo08@example.test', 'status' => 'deleted']);

        $player = $this->service()->addPlayer($ladder, [
            'label' => 'Spieler X', 'uid' => 0, 'phone' => '', 'email' => 'demo08@example.test', 'itn' => '', 'gender' => 'x', 'age' => '200',
        ]);

        $this->assertNull($player->uid);
        $this->assertSame('Spieler X', $player->label);
        $this->assertNull($player->phone);
        $this->assertNull($player->itn);
        $this->assertEquals(10.0, (float) $player->itn_rated);
        $this->assertNull($player->gender);
        $this->assertNull($player->age);
    }

    #[Test]
    public function email_match_ignores_disabled_accounts(): void
    {
        $ladder = PyramidLadder::factory()->setup()->create();
        $user = User::factory()->create(['alias' => 'Demo Spieler 09', 'email' => 'demo09@example.test', 'status' => 'disabled']);
        UserMeta::query()->create(['uid' => $user->uid, 'key' => 'phone', 'value' => '+43 000 000 0009']);

        $player = $this->service()->addPlayer($ladder, [
            'label' => 'Spieler Y', 'uid' => 0, 'phone' => '111', 'email' => 'demo09@example.test',
        ]);

        $this->assertNull($player->uid);
        $this->assertSame('Spieler Y', $player->label);
        $this->assertSame('111', $player->phone);
        $this->assertSame('demo09@example.test', $player->email);
    }

    #[Test]
    public function duplicate_member_is_rejected(): void
    {
        $ladder = PyramidLadder::factory()->setup()->create();
        $this->waiting($ladder, 'Spieler A', null, ['uid' => 5]);

        $this->assertRuleKey('pyramid::admin.already_participant', fn () => $this->service()->addPlayer($ladder, ['label' => 'Spieler B', 'uid' => 5]));
        $this->assertRuleKey('pyramid::admin.label_required', fn () => $this->service()->addPlayer($ladder, ['label' => ' ', 'uid' => 0]));
    }

    #[Test]
    public function admit_appends_and_logs_join(): void
    {
        $ladder = $this->makeLadder(3);
        $player = $this->waiting($ladder, 'Spieler W', null);

        $position = $this->service()->admit($player, '6,5', 42);

        $this->assertSame(4, $position);
        $row = PyramidPlayer::query()->findOrFail($player->id);
        $this->assertSame('active', $row->status);
        $this->assertEquals(6.5, (float) $row->itn_rated);
        $event = PyramidEvent::query()->where('type', 'join')->firstOrFail();
        $this->assertSame('{"player":'.$player->id.',"position":4}', $event->payload);
        $this->assertSame(42, (int) $event->actor_uid);
        $this->assertRankingIntact((int) $ladder->id);

        $this->assertRuleKey('pyramid::admin.not_waiting', fn () => $this->service()->admit($row, '', null));
    }

    #[Test]
    public function release_is_refused_while_a_challenge_runs(): void
    {
        $ladder = $this->makeLadder(4);
        $player = $this->waiting($ladder, 'Spieler W', null);

        $this->assertSame('entering', $this->service()->releaseEntry($player, true, 42));
        $this->assertSame(['message' => 'Spieler W ist zum Einfordern freigegeben.'], $this->lastEventPayload((int) $ladder->id, 'admin'));

        $entering = PyramidPlayer::query()->findOrFail($player->id);
        $this->app->make(ChallengeService::class)->create($ladder, $entering, $this->playerAt($ladder, 4), 42);

        $this->assertRuleKey('pyramid::admin.entry_busy', fn () => $this->service()->releaseEntry($entering, false, 42));
        $this->assertSame('entering', PyramidPlayer::query()->findOrFail($player->id)->status);
    }

    #[Test]
    public function release_can_be_withdrawn(): void
    {
        $ladder = $this->makeLadder(2);
        $player = PyramidPlayer::factory()->entering()->create(['ladder_id' => $ladder->id, 'label' => 'Spieler W']);

        $this->assertSame('waiting', $this->service()->releaseEntry($player, false, null));
        $this->assertSame('waiting', PyramidPlayer::query()->findOrFail($player->id)->status);
    }

    #[Test]
    public function removing_a_player_closes_the_gap_and_logs_it(): void
    {
        $ladder = $this->makeLadder(5);
        $second = $this->playerAt($ladder, 2);

        $this->service()->setStatus($second, 'removed', 42);

        $this->assertSame(['Spieler 1' => 1, 'Spieler 3' => 2, 'Spieler 4' => 3, 'Spieler 5' => 4], $this->positionsByLabel((int) $ladder->id));
        $this->assertRankingIntact((int) $ladder->id);
        $this->assertSame(
            ['message' => 'Rangliste normalisiert. Vorher: Lücken oder Dubletten in der Positionsfolge'],
            $this->lastEventPayload((int) $ladder->id, 'admin')
        );
    }

    #[Test]
    public function reactivating_without_a_position_appends(): void
    {
        $ladder = $this->makeLadder(3);
        $player = PyramidPlayer::factory()->create(['ladder_id' => $ladder->id, 'label' => 'Spieler R', 'status' => 'removed', 'position' => null]);

        $this->service()->setStatus($player, 'active', null);

        $this->assertSame(4, (int) PyramidPlayer::query()->findOrFail($player->id)->position);

        $this->service()->setStatus($this->playerAt($ladder, 1), 'paused', null);
        $this->assertSame('paused', $this->playerAt($ladder, 1)->status);
        $this->assertRankingIntact((int) $ladder->id);
    }

    #[Test]
    public function unknown_status_is_an_invalid_argument(): void
    {
        $ladder = $this->makeLadder(2);

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->setStatus($this->playerAt($ladder, 1), 'banana', null);
    }

    #[Test]
    public function move_down_and_up(): void
    {
        $ladder = $this->makeLadder(6);

        $this->service()->movePlayer($ladder, $this->playerAt($ladder, 2), 5);

        $this->assertSame(
            ['Spieler 1' => 1, 'Spieler 3' => 2, 'Spieler 4' => 3, 'Spieler 5' => 4, 'Spieler 2' => 5, 'Spieler 6' => 6],
            $this->positionsByLabel((int) $ladder->id)
        );
        $this->assertRankingIntact((int) $ladder->id);

        $this->service()->movePlayer($ladder, $this->playerAt($ladder, 5), 1);

        $this->assertSame(
            ['Spieler 2' => 1, 'Spieler 1' => 2, 'Spieler 3' => 3, 'Spieler 4' => 4, 'Spieler 5' => 5, 'Spieler 6' => 6],
            $this->positionsByLabel((int) $ladder->id)
        );
        $this->assertRankingIntact((int) $ladder->id);
    }

    #[Test]
    public function move_target_is_clamped(): void
    {
        $ladder = $this->makeLadder(4);

        $this->service()->movePlayer($ladder, $this->playerAt($ladder, 1), 99);

        $this->assertSame('Spieler 1', $this->playerAt($ladder, 4)->label);
        $this->assertRankingIntact((int) $ladder->id);
    }

    #[Test]
    public function update_player_edits_master_data_and_moves(): void
    {
        $ladder = $this->makeLadder(4);
        $player = $this->playerAt($ladder, 4);

        $this->service()->updatePlayer($player, [
            'label' => ' Spieler Neu ', 'itn' => '', 'gender' => 'm', 'age' => '0', 'phone' => '', 'note' => 'n',
        ], 1);

        $row = PyramidPlayer::query()->findOrFail($player->id);
        $this->assertSame('Spieler Neu', $row->label);
        $this->assertSame(1, (int) $row->position);
        $this->assertNull($row->itn);
        $this->assertEquals(10.0, (float) $row->itn_rated);
        $this->assertSame('m', $row->gender);
        $this->assertNull($row->age);
        $this->assertNull($row->phone);
        $this->assertSame('n', $row->note);
        $this->assertRankingIntact((int) $ladder->id);
    }

    #[Test]
    public function update_player_requires_a_label_and_changes_nothing_without_one(): void
    {
        $ladder = $this->makeLadder(4);
        $player = $this->playerAt($ladder, 4);

        $this->assertRuleKey('pyramid::admin.label_required', fn () => $this->service()->updatePlayer($player, [
            'label' => '   ', 'itn' => '5', 'note' => 'x',
        ], 1));

        $row = PyramidPlayer::query()->findOrFail($player->id);
        $this->assertSame('Spieler 4', $row->label);
        $this->assertSame(4, (int) $row->position);
        $this->assertNull($row->note);
    }

    #[Test]
    public function move_writes_null_first_and_only_the_changed_rows(): void
    {
        $ladder = $this->makeLadder(6);
        $ids = [];

        foreach (range(1, 6) as $position) {
            $ids[$position] = (int) $this->playerAt($ladder, $position)->id;
        }

        DB::enableQueryLog();
        $this->service()->movePlayer($ladder, $this->playerAt($ladder, 2), 5);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $updates = array_values(array_filter(
            $queries,
            fn (array $q): bool => str_contains($q['query'], 'update "bs_pyramid_players" set "position"')
        ));

        // Players 2..5 change: four clears, then four new values; players 1 and 6 are never written.
        $this->assertCount(8, $updates);

        foreach (array_slice($updates, 0, 4) as $update) {
            $this->assertNull($update['bindings'][0]);
        }

        foreach (array_slice($updates, 4) as $update) {
            $this->assertNotNull($update['bindings'][0]);
        }

        $expected = [$ids[2], $ids[3], $ids[4], $ids[5]];
        $cleared = array_map(fn (array $q): int => (int) $q['bindings'][1], array_slice($updates, 0, 4));
        $set = array_map(fn (array $q): int => (int) $q['bindings'][1], array_slice($updates, 4));
        sort($cleared);
        sort($set);
        $this->assertSame($expected, $cleared);
        $this->assertSame($expected, $set);
        $this->assertRankingIntact((int) $ladder->id);
    }

    #[Test]
    public function moving_to_the_own_position_writes_nothing(): void
    {
        $ladder = $this->makeLadder(4);
        $player = $this->playerAt($ladder, 3);

        DB::enableQueryLog();
        $this->service()->movePlayer($ladder, $player, 3);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame([], array_values(array_filter(
            $queries,
            fn (array $q): bool => str_contains($q['query'], 'update "bs_pyramid_players"')
        )));
    }

    #[Test]
    public function diagnose_texts_carry_the_counts(): void
    {
        $ladder = $this->makeLadder(4);
        PyramidPlayer::query()->where('ladder_id', $ladder->id)->whereIn('position', [2, 4])->update(['position' => null]);
        PyramidPlayer::query()->where('ladder_id', $ladder->id)->where('position', 3)->update(['position' => 7]);
        PyramidPlayer::factory()->waiting()->create(['ladder_id' => $ladder->id, 'label' => 'Spieler S', 'position' => 9]);

        $messages = array_map(fn ($verdict) => $verdict->message('de'), $this->service()->diagnose($ladder));

        $this->assertSame([
            '2 aktive oder pausierte Teilnehmer ohne Position',
            '1 wartende oder entfernte Teilnehmer mit gesetzter Position',
            'Lücken oder Dubletten in der Positionsfolge',
        ], $messages);
    }

    #[Test]
    public function diagnose_reports_each_problem(): void
    {
        $ladder = $this->makeLadder(3);
        $this->assertSame([], $this->service()->diagnose($ladder));

        PyramidPlayer::query()->where('ladder_id', $ladder->id)->where('position', 2)->update(['position' => null]);
        PyramidPlayer::factory()->waiting()->create(['ladder_id' => $ladder->id, 'label' => 'Spieler S', 'position' => 9]);

        $keys = array_map(fn ($verdict) => $verdict->key, $this->service()->diagnose($ladder));

        $this->assertSame(['pyramid::admin.problem_orphans', 'pyramid::admin.problem_stray', 'pyramid::admin.problem_gaps'], $keys);
    }

    #[Test]
    public function extension_limit_is_measured_from_old_deadline_midnight(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-25 10:00:00'));
        $ladder = $this->makeLadder(6);
        $challenge = $this->app->make(ChallengeService::class)->create($ladder, $this->playerAt($ladder, 5), $this->playerAt($ladder, 3), 42);
        PyramidChallenge::query()->whereKey($challenge->id)->update(['deadline' => '2026-11-01']);

        $this->assertRuleKey('pyramid::admin.extend_limit', fn () => $this->service()->extendDeadline($challenge, '2026-11-29', 'Urlaub', 42));
        $this->assertSame('2026-11-01', PyramidChallenge::query()->findOrFail($challenge->id)->deadline);

        $this->assertSame('2026-11-28', $this->service()->extendDeadline($challenge, '2026-11-28', 'Urlaub', 42));
        $this->assertSame('2026-11-28', PyramidChallenge::query()->findOrFail($challenge->id)->deadline);
        $this->assertSame(['message' => 'Frist verlängert bis 2026-11-28: Urlaub'], $this->lastEventPayload((int) $ladder->id, 'admin'));
    }

    #[Test]
    public function extend_expire_and_forfeit_require_a_note(): void
    {
        $ladder = $this->makeLadder(6);
        $challenge = $this->app->make(ChallengeService::class)->create($ladder, $this->playerAt($ladder, 5), $this->playerAt($ladder, 3), 42);
        $before = $this->positionsByLabel((int) $ladder->id);
        $events = PyramidEvent::query()->count();
        $deadline = PyramidChallenge::query()->findOrFail($challenge->id)->deadline;

        foreach (['', "  \t "] as $note) {
            $this->assertRuleKey('pyramid::admin.note_required', fn () => $this->service()->extendDeadline($challenge, date('Y-m-d'), $note, 42));
            $this->assertRuleKey('pyramid::admin.note_required', fn () => $this->service()->expire($challenge, $note, 42));
            $this->assertRuleKey('pyramid::admin.note_required', fn () => $this->service()->forfeit($challenge, true, $note, 42));
        }

        $row = PyramidChallenge::query()->findOrFail($challenge->id);
        $this->assertSame('open', $row->status);
        $this->assertSame($deadline, $row->deadline);
        $this->assertNull($row->admin_note);
        $this->assertSame($before, $this->positionsByLabel((int) $ladder->id));
        $this->assertSame($events, PyramidEvent::query()->count());
    }

    #[Test]
    public function notes_are_stored_trimmed(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-25 10:00:00'));
        $ladder = $this->makeLadder(8);
        $challenges = $this->app->make(ChallengeService::class);
        $extend = $challenges->create($ladder, $this->playerAt($ladder, 5), $this->playerAt($ladder, 3), 42);
        $expire = $challenges->create($ladder, $this->playerAt($ladder, 8), $this->playerAt($ladder, 6), 42);
        $forfeit = $challenges->create($ladder, $this->playerAt($ladder, 7), $this->playerAt($ladder, 4), 42);

        $this->service()->extendDeadline($extend, '2026-11-02', "  Urlaub \n", 42);
        $this->assertSame(['message' => 'Frist verlängert bis 2026-11-02: Urlaub'], $this->lastEventPayload((int) $ladder->id, 'admin'));

        $this->service()->expire($expire, '  kein Termin ', 42);
        $this->assertSame('kein Termin', PyramidChallenge::query()->findOrFail($expire->id)->admin_note);
        $this->assertSame(['message' => 'Eine Forderung ist ohne Spiel verfallen: kein Termin'], $this->lastEventPayload((int) $ladder->id, 'admin'));

        $this->service()->forfeit($forfeit, true, "\tnicht erschienen  ", 42);
        $this->assertSame('nicht erschienen', PyramidChallenge::query()->findOrFail($forfeit->id)->admin_note);
    }

    #[Test]
    public function expire_closes_the_challenge_without_moving_anyone(): void
    {
        $ladder = $this->makeLadder(6);
        $challenge = $this->app->make(ChallengeService::class)->create($ladder, $this->playerAt($ladder, 5), $this->playerAt($ladder, 3), 42);

        $this->service()->expire($challenge, 'kein Termin', 42);

        $row = PyramidChallenge::query()->findOrFail($challenge->id);
        $this->assertSame('expired', $row->status);
        $this->assertSame('kein Termin', $row->admin_note);
        $this->assertNotNull($row->resolved);
        $this->assertSame(['message' => 'Eine Forderung ist ohne Spiel verfallen: kein Termin'], $this->lastEventPayload((int) $ladder->id, 'admin'));
        $this->assertSame('Spieler 5', $this->playerAt($ladder, 5)->label);
    }

    #[Test]
    public function forfeit_moves_like_a_match(): void
    {
        $ladder = $this->makeLadder(6);
        $challenge = $this->app->make(ChallengeService::class)->create($ladder, $this->playerAt($ladder, 5), $this->playerAt($ladder, 3), 42);

        $this->service()->forfeit($challenge, true, 'nicht erschienen', 42);

        $this->assertSame('forfeited', PyramidChallenge::query()->findOrFail($challenge->id)->status);
        $this->assertSame('Spieler 5', $this->playerAt($ladder, 3)->label);
        $this->assertRankingIntact((int) $ladder->id);
    }

    #[Test]
    public function correction_never_moves_the_ladder(): void
    {
        $ladder = $this->makeLadder(6);
        $challenge = $this->app->make(ChallengeService::class)->create($ladder, $this->playerAt($ladder, 5), $this->playerAt($ladder, 3), 42);

        $this->assertRuleKey('pyramid::admin.correct_unresolved', fn () => $this->service()->correctResult($challenge, (int) $challenge->challenger_id, '6:0 6:0', 'x', 42));

        $this->app->make(ResultService::class)->record($ladder, $challenge, ['s1a' => 6, 's1b' => 4, 's2a' => 6, 's2b' => 3], 42);
        $before = $this->positionsByLabel((int) $ladder->id);
        $fresh = PyramidChallenge::query()->findOrFail($challenge->id);

        $this->assertRuleKey('pyramid::admin.note_required', fn () => $this->service()->correctResult($fresh, (int) $fresh->opponent_id, '7:5 6:4', '  ', 42));

        try {
            $this->service()->correctResult($fresh, 999999, '7:5 6:4', 'Dreher', 42);
            $this->fail('A foreign winner must be refused');
        } catch (\InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->service()->correctResult($fresh, (int) $fresh->opponent_id, '7:5 6:4', 'Dreher', 42);

        $row = PyramidChallenge::query()->findOrFail($challenge->id);
        $this->assertSame((int) $fresh->opponent_id, (int) $row->winner_id);
        $this->assertSame('7:5 6:4', $row->score);
        $this->assertSame('Dreher', $row->admin_note);
        $this->assertSame('played', $row->status);
        $this->assertSame($before, $this->positionsByLabel((int) $ladder->id));
        $event = PyramidEvent::query()->where('type', 'admin')->where('challenge_id', $challenge->id)->firstOrFail();
        $this->assertSame(['message' => 'Ein verbuchtes Ergebnis wurde richtiggestellt: Dreher'], json_decode((string) $event->payload, true));
    }

    #[Test]
    public function config_keeps_a_single_default(): void
    {
        $first = PyramidLadder::factory()->asDefault()->create();
        $second = PyramidLadder::factory()->create();

        $this->service()->configure($second, [
            'days_to_play' => '10', 'winner_protected_days' => '2', 'loser_cooldown_days' => '5',
            'title' => 'Neu', 'category' => '', 'is_default' => '1',
        ]);

        $this->assertSame(0, (int) PyramidLadder::query()->findOrFail($first->id)->is_default);
        $row = PyramidLadder::query()->findOrFail($second->id);
        $this->assertSame(1, (int) $row->is_default);
        $this->assertSame([10, 2, 5], [(int) $row->days_to_play, (int) $row->winner_protected_days, (int) $row->loser_cooldown_days]);
        $this->assertSame('Neu', $row->title);
        $this->assertNull($row->category);

        $this->assertRuleKey('pyramid::admin.days_range', fn () => $this->service()->configure($second, [
            'days_to_play' => '366', 'winner_protected_days' => '2', 'loser_cooldown_days' => '5', 'title' => 'Neu',
        ]));
        $this->assertSame(10, (int) PyramidLadder::query()->findOrFail($second->id)->days_to_play);
    }

    #[Test]
    public function copy_players_dedupes_by_label_and_drops_email(): void
    {
        $target = PyramidLadder::factory()->setup()->create(['itn_default' => '9.0']);
        $source = PyramidLadder::factory()->create();
        $this->waiting($target, 'demo spieler 1', null);
        PyramidPlayer::factory()->create(['ladder_id' => $source->id, 'label' => 'Demo Spieler 1', 'position' => 1, 'status' => 'active']);
        PyramidPlayer::factory()->create([
            'ladder_id' => $source->id, 'label' => 'Demo Spieler 2', 'position' => 2, 'status' => 'active',
            'uid' => 7, 'email' => 'demo02@example.test', 'phone' => '+43 000 000 0002', 'gender' => 'f', 'age' => 30,
        ]);
        PyramidPlayer::factory()->create(['ladder_id' => $source->id, 'label' => 'Demo Spieler 3', 'status' => 'removed', 'position' => null]);
        $this->waiting($source, 'Demo Spieler 4', '6.5');

        $copied = $this->service()->copyPlayers($target, $source);

        $this->assertSame(2, $copied);
        $two = PyramidPlayer::query()->where('ladder_id', $target->id)->where('label', 'Demo Spieler 2')->firstOrFail();
        $this->assertSame('waiting', $two->status);
        $this->assertNull($two->position);
        $this->assertNull($two->email);
        $this->assertSame(7, (int) $two->uid);
        $this->assertSame('+43 000 000 0002', $two->phone);
        $this->assertEquals(9.0, (float) $two->itn_rated);
        $four = PyramidPlayer::query()->where('ladder_id', $target->id)->where('label', 'Demo Spieler 4')->firstOrFail();
        $this->assertEquals(6.5, (float) $four->itn_rated);
        $this->assertSame(3, PyramidPlayer::query()->where('ladder_id', $target->id)->count());

        $this->assertRuleKey('pyramid::admin.copy_source', fn () => $this->service()->copyPlayers($target, $target));
    }

    #[Test]
    public function copy_players_rolls_back_on_duplicate_uid(): void
    {
        $target = PyramidLadder::factory()->setup()->create();
        $source = PyramidLadder::factory()->setup()->create();
        $this->waiting($target, 'A', null, ['uid' => 5]);
        $this->waiting($source, 'C', null);
        $this->waiting($source, 'B', null, ['uid' => 5]);

        $this->assertRuleKey('pyramid::admin.copy_failed', fn () => $this->service()->copyPlayers($target, $source));

        $this->assertSame(1, PyramidPlayer::query()->where('ladder_id', $target->id)->count());
    }

    #[Test]
    public function close_ends_the_season(): void
    {
        $ladder = $this->makeLadder(2);

        $this->service()->close($ladder, 42);

        $this->assertSame('closed', PyramidLadder::query()->findOrFail($ladder->id)->status);
        $this->assertSame(['message' => EventText::CLOSED], $this->lastEventPayload((int) $ladder->id, 'admin'));
    }
}
