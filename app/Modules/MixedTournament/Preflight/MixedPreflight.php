<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Preflight;

use App\Modules\TournamentBase\Preflight\ModulePreflight;
use App\Modules\TournamentBase\Preflight\PreflightResult;
use App\Modules\TournamentBase\Preflight\SchemaInspector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only comparison of the live bs_mixed_* tables with what the Laravel module expects (SELECTs only).
 */
final class MixedPreflight implements ModulePreflight
{
    /** @var array<string, array<string, array{0: string, 1: bool}>> */
    public const COLUMNS = [
        'bs_mixed_events' => [
            'id' => ['int unsigned', false],
            'title' => ['varchar(160)', false],
            'event_date' => ['date', false],
            'status' => ["enum('draft','running','done')", false],
            'is_default' => ['tinyint', false],
            'is_test' => ['tinyint', false],
            'court_count' => ['smallint unsigned', false],
            'mode' => ["enum('americano','mexicano')", false],
            'created' => ['datetime', false],
        ],
        'bs_mixed_participants' => [
            'id' => ['int unsigned', false],
            'event_id' => ['int unsigned', false],
            'uid' => ['int unsigned', true],
            'label' => ['varchar(120)', false],
            'gender' => ["enum('m','f')", true],
            'itn' => ['decimal(3,1)', true],
            'age' => ['tinyint unsigned', true],
            'status' => ["enum('active','paused')", false],
            'no_extra_match' => ['tinyint', false],
        ],
        'bs_mixed_rounds' => [
            'id' => ['int unsigned', false],
            'event_id' => ['int unsigned', false],
            'round_no' => ['smallint unsigned', false],
            'group_size_mode' => ["enum('4_only','4_and_5')", false],
            'distribution' => ["enum('random','itn','itn_balanced','age','ladies_apart')", false],
            'status' => ["enum('proposed','confirmed','completed')", false],
        ],
        'bs_mixed_round_players' => [
            'id' => ['int unsigned', false],
            'round_id' => ['int unsigned', false],
            'participant_id' => ['int unsigned', false],
            'group_no' => ['tinyint unsigned', true],
        ],
        'bs_mixed_matches' => [
            'id' => ['int unsigned', false],
            'round_id' => ['int unsigned', false],
            'group_no' => ['tinyint unsigned', false],
            'match_no' => ['tinyint unsigned', false],
            'team_a_p1' => ['int unsigned', false],
            'team_a_p2' => ['int unsigned', false],
            'team_b_p1' => ['int unsigned', false],
            'team_b_p2' => ['int unsigned', false],
            'games_a' => ['tinyint unsigned', true],
            'games_b' => ['tinyint unsigned', true],
            'is_extra' => ['tinyint', false],
        ],
        'bs_mixed_avoid_pairs' => [
            'id' => ['int unsigned', false],
            'event_id' => ['int unsigned', false],
            'participant_a' => ['int unsigned', false],
            'participant_b' => ['int unsigned', false],
        ],
        'bs_mixed_members' => [
            'id' => ['int unsigned', false],
            'label' => ['varchar(120)', false],
            'gender' => ["enum('m','f')", true],
            'itn' => ['decimal(3,1)', true],
            'birth_date' => ['date', true],
        ],
        'bs_mixed_event_grants' => [
            'id' => ['int unsigned', false],
            'event_id' => ['int unsigned', false],
            'uid' => ['int unsigned', false],
            'role' => ["enum('score','admin')", false],
            'created' => ['datetime', false],
        ],
        'bs_mixed_schema_versions' => [
            'version' => ['int unsigned', false],
            'applied' => ['datetime', false],
        ],
    ];

    /** Schema step of the old application that the module expects (the baseline row). */
    private const SCHEMA_VERSION = 20260803;

    /** @var list<array{0: string, 1: list<string>}> */
    private const UNIQUES = [
        ['bs_mixed_rounds', ['event_id', 'round_no']],
        ['bs_mixed_participants', ['event_id', 'uid']],
        ['bs_mixed_event_grants', ['event_id', 'uid']],
        ['bs_mixed_avoid_pairs', ['event_id', 'participant_a', 'participant_b']],
    ];

    /** @var list<array{0: string, 1: string, 2: string, 3: string}> child table, child column, parent table, parent column */
    private const ORPHANS = [
        ['bs_mixed_participants', 'event_id', 'bs_mixed_events', 'id'],
        ['bs_mixed_rounds', 'event_id', 'bs_mixed_events', 'id'],
        ['bs_mixed_avoid_pairs', 'event_id', 'bs_mixed_events', 'id'],
        ['bs_mixed_event_grants', 'event_id', 'bs_mixed_events', 'id'],
        ['bs_mixed_round_players', 'round_id', 'bs_mixed_rounds', 'id'],
        ['bs_mixed_round_players', 'participant_id', 'bs_mixed_participants', 'id'],
        ['bs_mixed_matches', 'round_id', 'bs_mixed_rounds', 'id'],
        ['bs_mixed_matches', 'team_a_p1', 'bs_mixed_participants', 'id'],
        ['bs_mixed_matches', 'team_a_p2', 'bs_mixed_participants', 'id'],
        ['bs_mixed_matches', 'team_b_p1', 'bs_mixed_participants', 'id'],
        ['bs_mixed_matches', 'team_b_p2', 'bs_mixed_participants', 'id'],
        ['bs_mixed_avoid_pairs', 'participant_a', 'bs_mixed_participants', 'id'],
        ['bs_mixed_avoid_pairs', 'participant_b', 'bs_mixed_participants', 'id'],
        ['bs_mixed_event_grants', 'uid', 'bs_users', 'uid'],
    ];

    public function __construct(private readonly SchemaInspector $schema) {}

    public function module(): string
    {
        return 'mixed';
    }

    public function run(): array
    {
        $tables = array_keys(self::COLUMNS);
        $missing = array_values(array_filter($tables, fn (string $t): bool => ! $this->schema->hasTable($t)));

        if ($missing !== []) {
            return [PreflightResult::fail('tables', 'missing '.implode(', ', $missing))];
        }

        $results = [PreflightResult::pass('tables', implode(', ', $tables))];

        foreach (self::COLUMNS as $table => $columns) {
            array_push($results, ...$this->schema->compareColumns($table, $columns));

            // The old application's participants table carried a birth_date column; the module never reads it.
            if ($table === 'bs_mixed_participants' && Schema::hasColumn($table, 'birth_date')) {
                $results[] = PreflightResult::warn('legacy:bs_mixed_participants.birth_date', 'tolerated');
            }
        }

        foreach ($tables as $table) {
            $results[] = $this->charset($table);
        }

        $results[] = $this->schemaVersion();

        foreach (self::UNIQUES as [$table, $columns]) {
            $results[] = $this->schema->checkUnique($table, $columns);
        }

        $results[] = $this->noUniqueOnRoundPlayers();
        $results[] = $this->grantForeignKey();

        foreach (self::ORPHANS as [$child, $childColumn, $parent, $parentColumn]) {
            $results[] = $this->orphans($child, $childColumn, $parent, $parentColumn);
        }

        $results[] = $this->defaultEvents();
        $results[] = $this->scoreSums();
        $results[] = $this->roundPlayerRows();
        $results[] = $this->mexicanoMatches();

        return $results;
    }

    private function charset(string $table): PreflightResult
    {
        $check = "charset:{$table}";

        if (! $this->schema->isMysql()) {
            return PreflightResult::skip($check, 'not MySQL');
        }

        $collation = $this->schema->collation($table);

        return $collation !== null && str_starts_with($collation, 'utf8mb4')
            ? PreflightResult::pass($check, $collation)
            : PreflightResult::fail($check, 'collation is '.($collation ?? 'unknown').', expected utf8mb4');
    }

    private function schemaVersion(): PreflightResult
    {
        $check = 'version:bs_mixed_schema_versions';

        if (! $this->schema->hasColumns('bs_mixed_schema_versions', ['version'])) {
            return PreflightResult::fail($check, 'version column is absent');
        }

        $max = (int) DB::table('bs_mixed_schema_versions')->max('version');

        return $max >= self::SCHEMA_VERSION
            ? PreflightResult::pass($check, (string) $max)
            : PreflightResult::fail($check, "latest is {$max}, expected at least ".self::SCHEMA_VERSION);
    }

    /** An extra-group filler has two rows and a Mexicano sitter one row without group: any unique key breaks that. */
    private function noUniqueOnRoundPlayers(): PreflightResult
    {
        $check = 'no-unique:bs_mixed_round_players';
        $offending = [];

        foreach (Schema::getIndexes('bs_mixed_round_players') as $index) {
            if ($index['unique'] && ! $index['primary']) {
                $offending[] = implode(', ', $index['columns']);
            }
        }

        return $offending === []
            ? PreflightResult::pass($check)
            : PreflightResult::fail($check, 'unique index on '.implode('; ', $offending));
    }

    private function grantForeignKey(): PreflightResult
    {
        $check = 'fk:bs_mixed_event_grants.uid';

        if (! $this->schema->isMysql()) {
            return PreflightResult::skip($check, 'not MySQL');
        }

        $fk = $this->schema->foreignKey('bs_mixed_event_grants', 'uid');

        if ($fk === null) {
            return PreflightResult::fail($check, 'fk_mixed_grant_user is missing');
        }

        if ($fk['table'] !== 'bs_users' || $fk['column'] !== 'uid' || $fk['delete_rule'] !== 'CASCADE') {
            return PreflightResult::fail(
                $check,
                "{$fk['name']} references {$fk['table']}.{$fk['column']} ON DELETE {$fk['delete_rule']}, expected bs_users.uid ON DELETE CASCADE"
            );
        }

        return $fk['name'] === 'fk_mixed_grant_user'
            ? PreflightResult::pass($check, $fk['name'])
            : PreflightResult::warn($check, "constraint is named {$fk['name']}, expected fk_mixed_grant_user");
    }

    private function orphans(string $child, string $childColumn, string $parent, string $parentColumn): PreflightResult
    {
        $check = "orphans:{$child}.{$childColumn}->{$parent}.{$parentColumn}";

        if (! $this->schema->hasColumns($child, [$childColumn])) {
            return PreflightResult::skip($check, "{$child}.{$childColumn} is absent");
        }

        if (! $this->schema->hasColumns($parent, [$parentColumn])) {
            return PreflightResult::skip($check, "{$parent} is absent");
        }

        $count = $this->schema->orphanCount($child, $childColumn, $parent, $parentColumn);

        return $count > 0
            ? PreflightResult::warn($check, "{$count} orphaned rows")
            : PreflightResult::pass($check);
    }

    private function defaultEvents(): PreflightResult
    {
        $check = 'data:default-events';

        if (! $this->schema->hasColumns('bs_mixed_events', ['is_default'])) {
            return PreflightResult::skip($check, 'is_default is absent');
        }

        $count = DB::table('bs_mixed_events')->where('is_default', 1)->count();

        return $count > 1
            ? PreflightResult::warn($check, "{$count} events are marked as default")
            : PreflightResult::pass($check);
    }

    private function scoreSums(): PreflightResult
    {
        $check = 'data:score-sum';

        if (! $this->schema->hasColumns('bs_mixed_matches', ['games_a', 'games_b'])) {
            return PreflightResult::skip($check, 'games columns are absent');
        }

        $count = DB::table('bs_mixed_matches')
            ->whereNotNull('games_a')
            ->whereNotNull('games_b')
            ->whereRaw('games_a + games_b <> 4')
            ->count();

        return $count > 0
            ? PreflightResult::warn($check, "{$count} matches do not add up to 4 games")
            : PreflightResult::pass($check);
    }

    private function roundPlayerRows(): PreflightResult
    {
        $check = 'data:round-player-rows';

        if (! $this->schema->hasColumns('bs_mixed_round_players', ['round_id', 'participant_id'])) {
            return PreflightResult::skip($check, 'round player columns are absent');
        }

        $count = DB::query()
            ->fromSub(
                DB::table('bs_mixed_round_players')->select('round_id', 'participant_id')->groupBy('round_id', 'participant_id')->havingRaw('COUNT(*) > 2'),
                'rows_per_player'
            )
            ->count();

        return $count > 0
            ? PreflightResult::warn($check, "{$count} participants with more than two rows in one round")
            : PreflightResult::pass($check);
    }

    /** A Mexicano round plays exactly one match per group; sitters have no group. */
    private function mexicanoMatches(): PreflightResult
    {
        $check = 'data:mexicano-matches';

        if (
            ! $this->schema->hasColumns('bs_mixed_events', ['mode'])
            || ! $this->schema->hasColumns('bs_mixed_rounds', ['event_id'])
            || ! $this->schema->hasColumns('bs_mixed_matches', ['round_id'])
            || ! $this->schema->hasColumns('bs_mixed_round_players', ['round_id', 'group_no'])
        ) {
            return PreflightResult::skip($check, 'columns are absent');
        }

        $matches = DB::table('bs_mixed_matches')->selectRaw('round_id, COUNT(*) as n')->groupBy('round_id')
            ->pluck('n', 'round_id');
        $groups = DB::table('bs_mixed_round_players')->whereNotNull('group_no')
            ->selectRaw('round_id, COUNT(DISTINCT group_no) as n')->groupBy('round_id')
            ->pluck('n', 'round_id');

        $wrong = 0;
        $roundIds = DB::table('bs_mixed_rounds')
            ->join('bs_mixed_events', 'bs_mixed_events.id', '=', 'bs_mixed_rounds.event_id')
            ->where('bs_mixed_events.mode', 'mexicano')
            ->orderBy('bs_mixed_rounds.id')
            ->pluck('bs_mixed_rounds.id');

        foreach ($roundIds as $roundId) {
            if ((int) ($matches[$roundId] ?? 0) !== (int) ($groups[$roundId] ?? 0)) {
                $wrong++;
            }
        }

        return $wrong > 0
            ? PreflightResult::warn($check, "{$wrong} Mexicano rounds whose match count differs from the group count")
            : PreflightResult::pass($check);
    }
}
