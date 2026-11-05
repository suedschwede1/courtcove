<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Preflight;

use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Services\LadderAdminService;
use App\Modules\Pyramid\Support\Verdict;
use App\Modules\TournamentBase\Preflight\ModulePreflight;
use App\Modules\TournamentBase\Preflight\PreflightResult;
use App\Modules\TournamentBase\Preflight\SchemaInspector;
use Illuminate\Support\Facades\DB;

/**
 * Read-only comparison of the live bs_pyramid_* tables with what the Laravel module expects (SELECTs only).
 */
final class PyramidPreflight implements ModulePreflight
{
    /** @var array<string, array<string, array{0: string, 1: bool}>> */
    public const COLUMNS = [
        'bs_pyramid_ladders' => [
            'id' => ['int unsigned', false],
            'title' => ['varchar(160)', false],
            'season' => ['smallint unsigned', false],
            'category' => ['varchar(40)', true],
            'status' => ["enum('setup','running','closed')", false],
            'itn_default' => ['decimal(3,1)', false],
            'days_to_play' => ['smallint unsigned', false],
            'winner_protected_days' => ['smallint unsigned', false],
            'loser_cooldown_days' => ['smallint unsigned', false],
            'is_default' => ['tinyint', false],
            'rules_version' => ['varchar(40)', true],
            'created' => ['datetime', false],
        ],
        'bs_pyramid_players' => [
            'id' => ['int unsigned', false],
            'ladder_id' => ['int unsigned', false],
            'uid' => ['int unsigned', true],
            'label' => ['varchar(120)', false],
            'itn' => ['decimal(3,1)', true],
            'itn_rated' => ['decimal(3,1)', false],
            'position' => ['smallint unsigned', true],
            'status' => ["enum('waiting','entering','active','paused','removed')", false],
            'gender' => ["enum('m','f')", true],
            'age' => ['tinyint unsigned', true],
            'phone' => ['varchar(40)', true],
            'email' => ['varchar(128)', true],
            'joined' => ['datetime', false],
            'note' => ['varchar(160)', true],
        ],
        'bs_pyramid_challenges' => [
            'id' => ['int unsigned', false],
            'ladder_id' => ['int unsigned', false],
            'challenger_id' => ['int unsigned', false],
            'opponent_id' => ['int unsigned', false],
            'challenger_pos' => ['smallint unsigned', true],
            'opponent_pos' => ['smallint unsigned', true],
            'status' => ["enum('open','scheduled','played','declined','withdrawn','forfeited','expired')", false],
            'created' => ['datetime', false],
            'deadline' => ['date', true],
            'scheduled_at' => ['datetime', true],
            'score' => ['varchar(40)', true],
            'winner_id' => ['int unsigned', true],
            'entered_uid' => ['int unsigned', true],
            'entered' => ['datetime', true],
            'decline_reason' => ["enum('other_match','injury')", true],
            'admin_note' => ['varchar(160)', true],
            'resolved' => ['datetime', true],
        ],
        'bs_pyramid_events' => [
            'id' => ['int unsigned', false],
            'ladder_id' => ['int unsigned', false],
            'challenge_id' => ['int unsigned', true],
            'actor_uid' => ['int unsigned', true],
            'type' => ["enum('challenge','schedule','result','join','admin')", false],
            'payload' => ['text', false],
            'created' => ['datetime', false],
        ],
        'bs_pyramid_grants' => [
            'id' => ['int unsigned', false],
            'ladder_id' => ['int unsigned', false],
            'uid' => ['int unsigned', false],
            'role' => ["enum('admin')", false],
            'created' => ['datetime', false],
        ],
        'bs_pyramid_schema_versions' => [
            'version' => ['int unsigned', false],
            'applied' => ['datetime', false],
        ],
    ];

    /** Schema step of the old application that added bs_pyramid_players.email. */
    private const SCHEMA_VERSION = 20260806;

    /** @var list<array{0: string, 1: list<string>}> */
    private const UNIQUES = [
        ['bs_pyramid_players', ['ladder_id', 'uid']],
        ['bs_pyramid_players', ['ladder_id', 'position']],
        ['bs_pyramid_grants', ['ladder_id', 'uid']],
    ];

    /** @var list<array{0: string, 1: string, 2: string, 3: string}> child table, child column, parent table, parent column */
    private const ORPHANS = [
        ['bs_pyramid_players', 'ladder_id', 'bs_pyramid_ladders', 'id'],
        ['bs_pyramid_challenges', 'ladder_id', 'bs_pyramid_ladders', 'id'],
        ['bs_pyramid_events', 'ladder_id', 'bs_pyramid_ladders', 'id'],
        ['bs_pyramid_grants', 'ladder_id', 'bs_pyramid_ladders', 'id'],
        ['bs_pyramid_challenges', 'challenger_id', 'bs_pyramid_players', 'id'],
        ['bs_pyramid_challenges', 'opponent_id', 'bs_pyramid_players', 'id'],
        ['bs_pyramid_challenges', 'winner_id', 'bs_pyramid_players', 'id'],
        ['bs_pyramid_players', 'uid', 'bs_users', 'uid'],
        ['bs_pyramid_grants', 'uid', 'bs_users', 'uid'],
    ];

    public function __construct(
        private readonly SchemaInspector $schema,
        private readonly LadderAdminService $admin,
    ) {}

    public function module(): string
    {
        return 'pyramid';
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
        }

        foreach ($tables as $table) {
            $results[] = $this->charset($table);
        }

        $results[] = $this->schemaVersion();

        foreach (self::UNIQUES as [$table, $columns]) {
            $results[] = $this->schema->checkUnique($table, $columns);
        }

        foreach (self::ORPHANS as [$child, $childColumn, $parent, $parentColumn]) {
            $results[] = $this->orphans($child, $childColumn, $parent, $parentColumn);
        }

        array_push($results, ...$this->rankings());
        $results[] = $this->openPerPlayer();
        $results[] = $this->eventPayloads();

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
        if (! $this->schema->hasColumns('bs_pyramid_schema_versions', ['version'])) {
            return PreflightResult::skip('schema-version', 'version column is absent');
        }

        $max = (int) DB::table('bs_pyramid_schema_versions')->max('version');

        return $max >= self::SCHEMA_VERSION
            ? PreflightResult::pass('schema-version', (string) $max)
            : PreflightResult::warn('schema-version', "latest is {$max}, expected ".self::SCHEMA_VERSION.' (row for the old application\'s email step is missing; the additive migration 090006/090007 writes it)');
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

    /**
     * The unique index only rules out duplicates: gaps and active players without a position need this check.
     *
     * @return list<PreflightResult>
     */
    private function rankings(): array
    {
        if (! $this->schema->hasColumns('bs_pyramid_players', ['ladder_id', 'status', 'position'])) {
            return [PreflightResult::skip('ranking', 'bs_pyramid_players columns are absent')];
        }

        $results = [];

        if (! $this->schema->hasColumns('bs_pyramid_ladders', array_keys(self::COLUMNS['bs_pyramid_ladders']))) {
            return [PreflightResult::fail('ranking', 'bs_pyramid_ladders columns are absent or incomplete')];
        }

        foreach (PyramidLadder::query()->orderBy('id')->get() as $ladder) {
            $problems = array_map(fn (Verdict $v): string => $v->message('de'), $this->admin->diagnose($ladder));
            $check = "ranking:{$ladder->id}";

            $results[] = $problems === []
                ? PreflightResult::pass($check)
                : PreflightResult::warn($check, implode('; ', $problems));
        }

        return $results;
    }

    private function openPerPlayer(): PreflightResult
    {
        $check = 'open-per-player';

        if (! $this->schema->hasColumns('bs_pyramid_challenges', ['challenger_id', 'opponent_id', 'status'])) {
            return PreflightResult::skip($check, 'bs_pyramid_challenges columns are absent');
        }

        // One row per (challenge, player): a challenge with challenger == opponent counts once for that player.
        $open = DB::table('bs_pyramid_challenges')->whereIn('status', ['open', 'scheduled']);
        $sides = (clone $open)->select('challenger_id as player_id')
            ->unionAll((clone $open)->select('opponent_id as player_id')->whereColumn('opponent_id', '<>', 'challenger_id'));

        $doubled = DB::query()
            ->fromSub(DB::query()->fromSub($sides, 'sides')->select('player_id')->groupBy('player_id')->havingRaw('COUNT(*) > 1'), 'doubled')
            ->count();

        return $doubled > 0
            ? PreflightResult::warn($check, "{$doubled} players in more than one open challenge")
            : PreflightResult::pass($check);
    }

    private function eventPayloads(): PreflightResult
    {
        $check = 'json:bs_pyramid_events';

        if (! $this->schema->hasColumns('bs_pyramid_events', ['id', 'payload'])) {
            return PreflightResult::skip($check, 'payload column is absent');
        }

        $total = 0;
        $broken = 0;

        foreach (DB::table('bs_pyramid_events')->orderBy('id')->select(['id', 'payload'])->lazyById(500, 'id') as $row) {
            $total++;

            // json_validate() needs PHP 8.3+ (the project targets 8.4).
            if (! json_validate((string) $row->payload)) {
                $broken++;
            }
        }

        return $broken > 0
            ? PreflightResult::warn($check, "{$broken} of {$total} payloads do not decode")
            : PreflightResult::pass($check, "{$total} payloads");
    }
}
