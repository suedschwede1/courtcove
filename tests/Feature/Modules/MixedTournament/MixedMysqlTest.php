<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\MixedTournament;

use App\Models\User;
use App\Modules\MixedTournament\Domain\EventService;
use App\Modules\MixedTournament\Domain\MixedRuleException;
use App\Modules\MixedTournament\Domain\ParticipantService;
use App\Modules\MixedTournament\Domain\RoundService;
use App\Modules\MixedTournament\Models\MixedAvoidPair;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedEventGrant;
use App\Modules\MixedTournament\Models\MixedMatch;
use App\Modules\MixedTournament\Models\MixedParticipant;
use App\Modules\MixedTournament\Models\MixedRound;
use App\Modules\MixedTournament\Models\MixedRoundPlayer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\MysqlTestCase;

/**
 * Adopts the live bs_mixed_* shape (tests/Fixtures/mixed/live-schema.sql: the old DDL without its comment lines)
 * and runs preflight, row locks, the grant cascade, row order and the old integration script on a real MySQL.
 *
 * No wrapping transaction (DDL commits implicitly in MySQL, and RefreshDatabase would be committed away by the
 * first DROP): the database is migrated fresh once per process, every test starts by dropping the nine Mixed
 * tables, and the fresh shape is rebuilt afterwards for the other MySQL tests.
 */
#[Group('mysql')]
class MixedMysqlTest extends MysqlTestCase
{
    private static bool $migrated = false;

    /** True once setUp passed the MySQL guard (tearDown must never drop anything otherwise). */
    private bool $ready = false;

    private const TABLES = [
        'bs_mixed_events', 'bs_mixed_participants', 'bs_mixed_rounds', 'bs_mixed_round_players', 'bs_mixed_matches',
        'bs_mixed_avoid_pairs', 'bs_mixed_members', 'bs_mixed_event_grants', 'bs_mixed_schema_versions',
    ];

    private const MIGRATIONS = [
        '2026_11_06_100001_create_bs_mixed_events_table.php',
        '2026_11_06_100002_create_bs_mixed_participants_table.php',
        '2026_11_06_100003_create_bs_mixed_rounds_table.php',
        '2026_11_06_100004_create_bs_mixed_round_players_table.php',
        '2026_11_06_100005_create_bs_mixed_matches_table.php',
        '2026_11_06_100006_create_bs_mixed_avoid_pairs_table.php',
        '2026_11_06_100007_create_bs_mixed_members_table.php',
        '2026_11_06_100008_create_bs_mixed_event_grants_table.php',
        '2026_11_06_100009_create_bs_mixed_schema_versions_table.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$migrated) {
            $this->artisan('migrate:fresh');
            self::$migrated = true;
        }

        $this->dropTables();
        $this->ready = true;
    }

    protected function tearDown(): void
    {
        if ($this->ready) {
            $this->dropTables();
            $this->runMigrations();
        }

        parent::tearDown();
    }

    private function dropTables(): void
    {
        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function runMigrations(): void
    {
        foreach (self::MIGRATIONS as $file) {
            $migration = require base_path('app/Modules/MixedTournament/Database/Migrations/'.$file);
            $migration->up();
        }
    }

    private function loadSchema(string $fixture = 'live-schema.sql'): void
    {
        $this->dropTables();

        $sql = (string) file_get_contents(base_path('tests/Fixtures/mixed/'.$fixture));

        foreach (explode(';', $sql) as $statement) {
            $code = trim((string) preg_replace('/^\s*--.*$/m', '', $statement));

            if ($code !== '') {
                DB::unprepared($code);
            }
        }
    }

    /** The live tables after go-live: legacy DDL, then the guarded migrations (which leave it untouched). */
    private function adoptLiveShape(): void
    {
        $this->loadSchema();
        $this->runMigrations();
    }

    /**
     * @return array{int, string}
     */
    private function preflight(): array
    {
        $code = Artisan::call('phase2:preflight', ['module' => 'mixed']);

        return [$code, Artisan::output()];
    }

    private function rounds(): RoundService
    {
        return $this->app->make(RoundService::class);
    }

    private function newRound(MixedEvent $event): MixedRound
    {
        return DB::transaction(fn (): MixedRound => $this->rounds()->createRound($event, '4_only', 'random'));
    }

    private function assertRule(string $key, callable $call, string $message = ''): void
    {
        try {
            $call();
        } catch (MixedRuleException $e) {
            $this->assertSame($key, $e->key, $message);

            return;
        }

        $this->fail("MixedRuleException {$key} expected".($message === '' ? '' : ": {$message}"));
    }

    // Adoption and preflight

    #[Test]
    public function guarded_migrations_adopt_the_live_shape(): void
    {
        $this->loadSchema();

        $eventId = DB::table('bs_mixed_events')->insertGetId([
            'title' => 'Demo-Mixed', 'event_date' => '2026-08-01', 'created' => '2026-07-01 10:00:00',
        ]);
        $label = "Spieler \u{1F3BE}";
        DB::table('bs_mixed_participants')->insert(['event_id' => $eventId, 'label' => $label, 'gender' => 'f', 'itn' => '5.5']);

        $this->runMigrations();

        $this->assertSame(1, DB::table('bs_mixed_events')->count());
        $this->assertSame(1, DB::table('bs_mixed_participants')->count());
        $this->assertSame($label, DB::table('bs_mixed_participants')->value('label'), '4-byte label intact');
        $this->assertSame('americano', DB::table('bs_mixed_events')->value('mode'));

        $this->assertContains('event_uid', array_column(Schema::getIndexes('bs_mixed_participants'), 'name'));
        $this->assertContains('event_uid', array_column(Schema::getIndexes('bs_mixed_event_grants'), 'name'));
        $this->assertContains('event_round', array_column(Schema::getIndexes('bs_mixed_rounds'), 'name'));
        $this->assertContains('round_participant', array_column(Schema::getIndexes('bs_mixed_round_players'), 'name'));
        $this->assertContains('fk_mixed_grant_user', array_column(Schema::getForeignKeys('bs_mixed_event_grants'), 'name'));

        $this->assertSame([20260803], DB::table('bs_mixed_schema_versions')->pluck('version')->map(fn ($v): int => (int) $v)->all());

        // Running them again changes nothing.
        $this->runMigrations();
        $this->assertSame(1, DB::table('bs_mixed_schema_versions')->count());
        $this->assertSame(1, DB::table('bs_mixed_participants')->count());
    }

    #[Test]
    public function preflight_passes_on_the_adopted_live_shape(): void
    {
        $this->adoptLiveShape();

        [$code, $output] = $this->preflight();

        $this->assertSame(0, $code, $output);
        $this->assertStringNotContainsString('[FAIL]', $output);
        $this->assertStringContainsString('[PASS] fk:bs_mixed_event_grants.uid: fk_mixed_grant_user', $output);
        $this->assertStringContainsString('[PASS] charset:bs_mixed_events: utf8mb4_unicode_ci', $output);
        // The live DDL gives the versions table only a charset; MySQL picks its default utf8mb4 collation.
        $this->assertStringContainsString('[PASS] charset:bs_mixed_schema_versions: utf8mb4', $output);
        $this->assertStringContainsString('[PASS] no-unique:bs_mixed_round_players', $output);
        $this->assertStringContainsString('[PASS] unique:bs_mixed_rounds', $output);
        $this->assertStringNotContainsString('[SKIP] fk:', $output);
        $this->assertStringNotContainsString('[SKIP] charset:', $output);
    }

    #[Test]
    public function legacy_shape_passes_preflight_with_a_warning(): void
    {
        $this->loadSchema('live-schema-legacy.sql');
        $this->runMigrations();

        $this->assertTrue(Schema::hasColumn('bs_mixed_participants', 'birth_date'), 'the fixture is the legacy shape');

        [$code, $output] = $this->preflight();

        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('[WARN] legacy:bs_mixed_participants.birth_date', $output);
        $this->assertStringNotContainsString('[FAIL]', $output);
    }

    #[Test]
    public function preflight_spec_matches_fresh_migrations(): void
    {
        $this->runMigrations();

        [$code, $output] = $this->preflight();

        $this->assertSame(0, $code, $output);
        $this->assertStringNotContainsString('[FAIL]', $output);
        $this->assertStringNotContainsString('[WARN] columns', $output);
        $this->assertStringContainsString('[PASS] fk:bs_mixed_event_grants.uid: fk_mixed_grant_user', $output);
        $this->assertStringContainsString('[PASS] charset:bs_mixed_round_players', $output);
    }

    #[Test]
    public function utf8mb3_table_fails_charset(): void
    {
        $this->adoptLiveShape();
        DB::statement('ALTER TABLE bs_mixed_members CONVERT TO CHARACTER SET utf8mb3');

        [$code, $output] = $this->preflight();

        $this->assertSame(1, $code, $output);
        $this->assertStringContainsString('[FAIL] charset:bs_mixed_members', $output);
        $this->assertStringContainsString('[PASS] charset:bs_mixed_participants', $output);
    }

    #[Test]
    public function missing_grant_fk_fails(): void
    {
        $this->adoptLiveShape();
        DB::statement('ALTER TABLE bs_mixed_event_grants DROP FOREIGN KEY fk_mixed_grant_user');

        [$code, $output] = $this->preflight();

        $this->assertSame(1, $code, $output);
        $this->assertStringContainsString('[FAIL] fk:bs_mixed_event_grants.uid', $output);
    }

    #[Test]
    public function grant_cascades_on_user_delete(): void
    {
        $this->adoptLiveShape();
        $event = MixedEvent::factory()->create();
        $keep = User::factory()->create();
        $gone = User::factory()->create();
        $events = $this->app->make(EventService::class);

        $events->setGrant($event, (int) $keep->uid, 'admin');
        $events->setGrant($event, (int) $gone->uid, 'score');
        $this->assertSame(2, MixedEventGrant::query()->count());

        DB::table('bs_users')->where('uid', $gone->uid)->delete();

        $this->assertNull($events->grant($event, (int) $gone->uid), 'fk_mixed_grant_user removes the grant');
        $this->assertSame('admin', $events->grant($event, (int) $keep->uid)?->role);

        // A grant for a user that does not exist is refused by the same key.
        try {
            DB::table('bs_mixed_event_grants')->insert([
                'event_id' => $event->id, 'uid' => (int) $gone->uid, 'role' => 'score', 'created' => '2026-08-01 10:00:00',
            ]);
            $this->fail('the foreign key must refuse an unknown user');
        } catch (QueryException $e) {
            $this->assertSame(1452, $e->errorInfo[1]);
        }
    }

    // Locking and keys

    #[Test]
    public function create_round_locks_the_event_row(): void
    {
        $this->adoptLiveShape();
        $event = MixedEvent::factory()->create();

        /** @var list<string> $queries */
        $queries = [];
        DB::listen(function (QueryExecuted $q) use (&$queries): void {
            $queries[] = $q->sql;
        });

        $round = $this->newRound($event);

        $lock = $this->firstIndex($queries, '/^select \* from `bs_mixed_events` where `bs_mixed_events`\.`id` = \? limit 1 for update$/');
        $insert = $this->firstIndex($queries, '/^insert into `bs_mixed_rounds`/');
        $this->assertNotNull($lock, 'event row locked: '.implode(' | ', $queries));
        $this->assertNotNull($insert, 'round inserted');
        $this->assertLessThan($insert, $lock, 'the lock comes before the insert');
        $this->assertSame(1, (int) $round->round_no);

        // Every write on the round locks the event row first, then the round row.
        $queries = [];
        $this->rounds()->updateConfig($round, '4_and_5', 'itn');
        $locks = array_values(array_filter($queries, fn (string $sql): bool => str_ends_with($sql, 'for update')));
        $this->assertCount(2, $locks, implode(' | ', $locks));
        $this->assertStringStartsWith('select * from `bs_mixed_events`', $locks[0]);
        $this->assertStringStartsWith('select * from `bs_mixed_rounds`', $locks[1]);
    }

    /** @param  list<string>  $queries */
    private function firstIndex(array $queries, string $pattern): ?int
    {
        foreach ($queries as $i => $sql) {
            if (preg_match($pattern, $sql) === 1) {
                return $i;
            }
        }

        return null;
    }

    #[Test]
    public function duplicate_round_number_is_rejected_by_the_unique_key(): void
    {
        $this->adoptLiveShape();
        $event = MixedEvent::factory()->create();
        $round = $this->newRound($event);

        try {
            DB::table('bs_mixed_rounds')->insert(['event_id' => $event->id, 'round_no' => $round->round_no]);
            $this->fail('event_round must refuse a second round 1');
        } catch (QueryException $e) {
            $this->assertSame(1062, $e->errorInfo[1]);
            $this->assertStringContainsString('event_round', $e->getMessage());
        }

        $this->assertSame(2, (int) $this->newRound($event)->round_no, 'the service numbers past it');
    }

    #[Test]
    public function update_config_accepts_an_unchanged_config_on_mysql(): void
    {
        $this->adoptLiveShape();
        $event = MixedEvent::factory()->create();
        $round = $this->newRound($event);

        // The trap itself: MySQL reports CHANGED rows, so an identical update affects 0 rows.
        $this->assertSame(0, MixedRound::query()->whereKey($round->id)->update(['group_size_mode' => '4_only', 'distribution' => 'random']));

        $this->rounds()->updateConfig($round, '4_and_5', 'itn');
        $this->rounds()->updateConfig($round, '4_and_5', 'itn');

        $fresh = $round->fresh();
        $this->assertSame(['4_and_5', 'itn', 'proposed'], [$fresh->group_size_mode, $fresh->distribution, $fresh->status]);

        // Status transitions keep their affected-row check: a second confirm is refused.
        $this->rounds()->persistProposal($round, [[1, 2, 3, 4]], [
            ['group' => 0, 'match_no' => 0, 'team_a' => [1, 2], 'team_b' => [3, 4], 'is_extra' => false],
        ]);
        $this->rounds()->confirm($round);
        $this->assertRule('round_state', fn () => $this->rounds()->confirm($round));
    }

    // Row order (Review Focus 2)

    #[Test]
    public function regenerated_matches_follow_row_id_order_on_mysql(): void
    {
        $this->adoptLiveShape();
        $event = MixedEvent::factory()->create();
        foreach ([10, 20, 30, 40, 50, 60, 70, 80] as $id) {
            DB::table('bs_mixed_participants')->insert(['id' => $id, 'event_id' => $event->id, 'label' => "Spieler {$id}"]);
        }
        $round = $this->newRound($event);

        // Group 0 rows inserted with DESCENDING participant ids, so row-id order and participant order disagree.
        foreach ([[40, 0], [30, 0], [20, 0], [10, 0], [50, 1], [60, 1], [70, 1], [80, 1]] as [$participantId, $group]) {
            DB::table('bs_mixed_round_players')->insert(['round_id' => $round->id, 'participant_id' => $participantId, 'group_no' => $group]);
        }
        foreach ([[0, 40, 30, 20, 10], [1, 50, 60, 70, 80]] as [$group, $a1, $a2, $b1, $b2]) {
            DB::table('bs_mixed_matches')->insert([
                'round_id' => $round->id, 'group_no' => $group, 'match_no' => 0,
                'team_a_p1' => $a1, 'team_a_p2' => $a2, 'team_b_p1' => $b1, 'team_b_p2' => $b2, 'is_extra' => 0,
            ]);
        }

        $this->rounds()->swapPlayers($round, 30, 50);

        $matches = MixedMatch::query()->where('round_id', $round->id)
            ->orderBy('group_no')->orderBy('match_no')->orderBy('id')->get();
        $first = fn (int $group): array => (fn (MixedMatch $m): array => [(int) $m->team_a_p1, (int) $m->team_a_p2, (int) $m->team_b_p1, (int) $m->team_b_p2])(
            $matches->first(fn (MixedMatch $m): bool => (int) $m->group_no === $group && (int) $m->match_no === 0)
        );

        // Group 0 by row id: 40, 20, 10, then 50 (row 5 now carries group 0); by participant id it would be 10, 20, 40, 50.
        $this->assertSame([40, 20, 10, 50], $first(0));
        // Group 1 by row id: 30 (row 2 now carries group 1), 60, 70, 80.
        $this->assertSame([30, 60, 70, 80], $first(1));
        $this->assertSame(6, $matches->count(), 'both groups rebuilt to their full 4-player rotation');

        // The service reads round players in row order too.
        $this->assertSame([40, 30, 20, 10, 50, 60, 70, 80], $this->rounds()->roundParticipantIds($round));
    }

    // The old modulex/tests/mixed-integration-db.php against MySQL

    #[Test]
    public function integration_script_scenarios(): void
    {
        $this->adoptLiveShape();

        $events = $this->app->make(EventService::class);
        $participants = $this->app->make(ParticipantService::class);
        $rounds = $this->rounds();

        // Events and grants
        $event = $events->create('Testevent', '2026-08-01', 'americano');
        $this->assertGreaterThan(0, $event->id);
        $second = $events->create('Zweitevent', '2026-08-08', 'americano');

        $user = User::factory()->create();
        $uid = (int) $user->uid;
        $events->setGrant($event, $uid, 'score');
        $this->assertSame('score', $events->grant($event, $uid)?->role);
        $this->assertNull($events->grant($second, $uid), 'a grant is per event');
        $events->setGrant($event, $uid, 'admin');
        $this->assertSame('admin', $events->grant($event, $uid)?->role);
        $this->assertSame(1, MixedEventGrant::query()->where('event_id', $event->id)->count(), 'a role change is an update');
        $events->removeGrant($event, $uid);
        $this->assertNull($events->grant($event, $uid));

        $events->setDefault($event);
        $this->assertSame($event->id, $events->defaultEvent()?->id);
        $events->setDefault($second);
        $this->assertSame($second->id, $events->defaultEvent()?->id);
        $this->assertSame(1, MixedEvent::query()->where('is_default', 1)->count(), 'setting a new default clears the old one');

        // Participants
        $ids = [];
        for ($i = 1; $i <= 8; $i++) {
            $ids[] = (int) $participants->add($event, ['label' => 'Spieler '.$i, 'gender' => $i % 2 === 0 ? 'm' : 'f', 'itn' => 5.0])->id;
        }
        $this->assertCount(8, array_unique($ids));

        $participants->update(MixedParticipant::query()->findOrFail($ids[0]), ['label' => 'Spieler 1 (neu)', 'gender' => 'f', 'itn' => 6.0]);
        $updated = MixedParticipant::query()->findOrFail($ids[0]);
        $this->assertSame('Spieler 1 (neu)', $updated->label);
        $this->assertSame('active', $updated->status, 'new participants are active');

        $participants->setStatus(MixedParticipant::query()->findOrFail($ids[1]), 'paused');
        $this->assertSame('paused', MixedParticipant::query()->findOrFail($ids[1])->status);

        $participants->remove(MixedParticipant::query()->findOrFail($ids[7]));
        $this->assertSame(7, MixedParticipant::query()->where('event_id', $event->id)->count());

        // Avoid pairs
        $participants->addAvoidPair($event, $ids[0], $ids[1]);
        $participants->addAvoidPair($event, $ids[1], $ids[0]);
        $this->assertSame(1, MixedAvoidPair::query()->where('event_id', $event->id)->count(), 'a reversed pair is normalised');
        $foreign = (int) $participants->add($second, ['label' => 'Fremd'])->id;
        $this->assertRule('avoid_pair_invalid', fn () => $participants->addAvoidPair($event, $ids[0], $foreign));
        $participants->removeAvoidPair($event, (int) MixedAvoidPair::query()->where('event_id', $event->id)->value('id'));
        $this->assertSame(0, MixedAvoidPair::query()->where('event_id', $event->id)->count());

        // Rounds: proposal with an extra-group filler (seven[0] in both groups)
        $round = $this->newRound($event);
        $this->assertGreaterThan(0, $round->id);
        $seven = array_slice($ids, 0, 7);
        $groups = [array_slice($seven, 0, 4), [...array_slice($seven, 4, 3), $seven[0]]];
        $proposal = [
            ['group' => 0, 'match_no' => 0, 'team_a' => [$groups[0][0], $groups[0][1]], 'team_b' => [$groups[0][2], $groups[0][3]], 'is_extra' => false],
            ['group' => 1, 'match_no' => 0, 'team_a' => [$groups[1][0], $groups[1][1]], 'team_b' => [$groups[1][2], $groups[1][3]], 'is_extra' => true],
        ];

        $rounds->persistProposal($round, $groups, $proposal);
        $this->assertSame('proposed', $round->fresh()->status);
        $this->assertSame(2, MixedMatch::query()->where('round_id', $round->id)->count());
        $rounds->persistProposal($round, $groups, $proposal);
        $this->assertSame(2, MixedMatch::query()->where('round_id', $round->id)->count(), 'a new proposal replaces the old one');
        $this->assertSame(2, MixedRoundPlayer::query()->where('round_id', $round->id)->where('participant_id', $seven[0])->count(), 'the filler keeps two rows');
        $this->assertRule('participant_in_use', fn () => $participants->remove(MixedParticipant::query()->findOrFail($seven[0])));

        $rounds->swapPlayers($round, $groups[0][1], $groups[1][0]);
        $group0 = [];
        foreach (MixedMatch::query()->where('round_id', $round->id)->where('group_no', 0)->orderBy('match_no')->orderBy('id')->get() as $m) {
            array_push($group0, (int) $m->team_a_p1, (int) $m->team_a_p2, (int) $m->team_b_p1, (int) $m->team_b_p2);
        }
        $this->assertContains($groups[1][0], $group0, 'the swap rebuilds the group matches');

        $rounds->confirm($round);
        $this->assertSame('confirmed', $round->fresh()->status);
        $this->assertRule('round_state', fn () => $rounds->persistProposal($round, $groups, $proposal));
        $this->assertRule('delete_confirmed', fn () => $rounds->delete($round));

        $firstMatchId = (int) MixedMatch::query()->where('round_id', $round->id)->orderBy('group_no')->orderBy('match_no')->orderBy('id')->value('id');
        $this->assertRule('invalid_result', fn () => $rounds->recordScore($round, $firstMatchId, '4:4'));
        $this->assertRule('scores_missing', fn () => $rounds->complete($round));

        $foreignRound = $this->newRound($second);
        try {
            $rounds->recordScore($foreignRound, $firstMatchId, '3:1');
            $this->fail('a match cannot be scored through another round');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }
        $rounds->delete($foreignRound);

        $rounds->recordScore($round, $firstMatchId, '3:1');
        $this->assertNotSame([], $rounds->standings($event));
        $this->assertCount(6, $rounds->pastMatches($event), 'after the swap both groups hold their full rotation');
        $this->assertNotSame([], $rounds->preview($round)['match_counts']);

        $empty = $this->newRound($event);
        $this->assertRule('empty_round_confirm', fn () => $rounds->confirm($empty));
        $rounds->persistProposal($empty, [array_slice($seven, 0, 4)], [
            ['group' => 0, 'match_no' => 0, 'team_a' => [$seven[0], $seven[1]], 'team_b' => [$seven[2], $seven[3]], 'is_extra' => false],
        ]);
        $rounds->delete($empty);
        $this->assertNull(MixedRound::query()->find($empty->id));
        $this->assertSame(0, MixedMatch::query()->where('round_id', $empty->id)->count());
        $this->assertSame(0, MixedRoundPlayer::query()->where('round_id', $empty->id)->count());

        // Mexicano: sitters, the open-round block and sit-out counts
        $mexicano = $events->create('Mexicano-Test', '2026-08-01', 'mexicano');
        $this->assertSame('mexicano', $mexicano->fresh()->mode);
        try {
            $events->create('Ungültiger Modus', '2026-08-01', 'unsinn');
            $this->fail('an unknown mode is refused');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $mx = [];
        for ($i = 1; $i <= 5; $i++) {
            $mx[] = (int) $participants->add($mexicano, ['label' => 'Mexicano-Spieler '.$i, 'gender' => $i % 2 === 0 ? 'm' : 'f', 'itn' => 5.0])->id;
        }
        $sitter = $mx[4];

        $mxRound = $this->newRound($mexicano);
        $rounds->persistProposal($mxRound, [array_slice($mx, 0, 4)], [
            ['group' => 0, 'match_no' => 0, 'team_a' => [$mx[0], $mx[1]], 'team_b' => [$mx[2], $mx[3]], 'is_extra' => false],
        ], [$sitter]);

        $this->assertSame([$sitter], $rounds->roundSitters($mxRound), 'group_no NULL written and read back');
        $this->assertRule('mexicano_round_open', fn () => $this->newRound($mexicano), 'proposed blocks the next round');
        $this->assertSame(0, $rounds->sitOutCounts($mexicano)[$sitter], 'a proposed round does not count');

        $rounds->confirm($mxRound);
        $this->assertRule('mexicano_round_open', fn () => $this->newRound($mexicano), 'confirmed blocks the next round');

        foreach (MixedMatch::query()->where('round_id', $mxRound->id)->orderBy('id')->pluck('id') as $matchId) {
            $rounds->recordScore($mxRound, (int) $matchId, '4:0');
        }
        $this->assertRule('mexicano_round_open', fn () => $this->newRound($mexicano), 'confirmed with all scores still blocks');
        $this->assertSame(0, $rounds->sitOutCounts($mexicano)[$sitter], 'confirmed with scores does not count yet');

        $rounds->complete($mxRound);
        $this->assertSame(1, $rounds->completedRoundCount($mexicano));
        $this->assertSame(1, $rounds->sitOutCounts($mexicano)[$sitter], 'a completed round counts the sitter');
        $this->assertNull($rounds->openRound($mexicano));

        $late = (int) $participants->add($mexicano, ['label' => 'Nachzügler', 'gender' => 'f', 'itn' => 5.0])->id;
        $this->assertSame(0, $rounds->sitOutCounts($mexicano)[$late], 'not selected is not an automatic sitter');

        $next = $this->newRound($mexicano);
        $this->assertSame(2, (int) $next->round_no);
        $this->assertSame($next->id, $rounds->openRound($mexicano)?->id);
    }
}
