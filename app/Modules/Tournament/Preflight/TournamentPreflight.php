<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Preflight;

use App\Modules\Tournament\Services\FormatService;
use App\Modules\TournamentBase\Preflight\ModulePreflight;
use App\Modules\TournamentBase\Preflight\PreflightResult;
use App\Modules\TournamentBase\Preflight\SchemaInspector;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Read-only comparison of the live bs_tournament* tables with what the Laravel module expects, plus the
 * data the ported services rely on: decodable formats and lot orders, unique match keys, no orphans
 * (SELECTs only).
 */
final class TournamentPreflight implements ModulePreflight
{
    /** @var array<string, array<string, array{0: string, 1: bool}>> */
    public const COLUMNS = [
        'bs_tournaments' => [
            'tid' => ['int unsigned', false],
            'title' => ['varchar(160)', false],
            'season' => ['smallint unsigned', false],
            'is_test' => ['tinyint', false],
            'cloned_from' => ['int unsigned', true],
            'status' => ["enum('setup','drawn','running','finished')", false],
            'active' => ['tinyint', false],
            'format' => ['mediumtext', true],
            'lot_orders' => ['mediumtext', true],
            'itn_default' => ['decimal(3,1)', false],
            'points_per_win' => ['tinyint unsigned', false],
            'tiebreak_scope' => ["enum('tied','all')", false],
            'runnerup_mode' => ["enum('drop_last','points','per_match','lot')", false],
            'created' => ['datetime', false],
        ],
        'bs_tournament_players' => [
            'tpid' => ['int unsigned', false],
            'tid' => ['int unsigned', false],
            'uid' => ['int unsigned', true],
            'label' => ['varchar(120)', false],
            'gender' => ["enum('m','w')", true],
            'itn' => ['decimal(3,1)', true],
            'itn_rated' => ['decimal(3,1)', false],
            'seed' => ['smallint unsigned', true],
            'grp' => ['char(1)', true],
            'grp_pos' => ['tinyint unsigned', true],
            'points_adjust' => ['tinyint', false],
            'phone' => ['varchar(40)', true],
            'partner_tpid' => ['int unsigned', true],
            'note' => ['varchar(160)', true],
        ],
        'bs_tournament_matches' => [
            'tmid' => ['int unsigned', false],
            'tid' => ['int unsigned', false],
            'phase' => ['varchar(16)', false],
            'mkey' => ['varchar(12)', true],
            'grp' => ['char(1)', true],
            'rnd' => ['tinyint unsigned', true],
            'slot' => ['smallint unsigned', true],
            'tpid_a' => ['int unsigned', true],
            'tpid_b' => ['int unsigned', true],
            'deadline' => ['date', true],
            'played' => ['date', true],
            'played_time' => ['time', true],
            'court' => ["enum('1','2','3')", true],
            'score' => ['varchar(40)', true],
            's1a' => ['tinyint unsigned', true],
            's1b' => ['tinyint unsigned', true],
            's2a' => ['tinyint unsigned', true],
            's2b' => ['tinyint unsigned', true],
            'mtba' => ['tinyint unsigned', true],
            'mtbb' => ['tinyint unsigned', true],
            'sets_a' => ['tinyint unsigned', true],
            'sets_b' => ['tinyint unsigned', true],
            'games_a' => ['smallint unsigned', true],
            'games_b' => ['smallint unsigned', true],
            'retired' => ["enum('none','ret_a','ret_b','wo_a','wo_b')", false],
            'winner' => ['int unsigned', true],
            'entered_uid' => ['int unsigned', true],
            'entered' => ['datetime', true],
            'confirmed_uid' => ['int unsigned', true],
            'confirmed' => ['datetime', true],
        ],
        'bs_tournament_lines' => [
            'tlid' => ['int unsigned', false],
            'tid' => ['int unsigned', false],
            'phase' => ['varchar(16)', false],
            'line' => ['smallint unsigned', false],
            'kind' => ["enum('seed','bye','pool')", false],
            'seed_no' => ['tinyint unsigned', true],
            'pool' => ['varchar(16)', true],
            'placeholder' => ['varchar(16)', true],
            'tpid' => ['int unsigned', true],
        ],
    ];

    /** @var list<array{0: string, 1: list<string>}> */
    private const UNIQUES = [
        ['bs_tournament_players', ['tid', 'uid']],
        ['bs_tournament_lines', ['tid', 'phase', 'line']],
    ];

    /** @var list<array{0: string, 1: string, 2: string, 3: string}> child table, child column, parent table, parent column */
    private const ORPHANS = [
        ['bs_tournament_players', 'tid', 'bs_tournaments', 'tid'],
        ['bs_tournament_matches', 'tid', 'bs_tournaments', 'tid'],
        ['bs_tournament_lines', 'tid', 'bs_tournaments', 'tid'],
        ['bs_tournament_matches', 'tpid_a', 'bs_tournament_players', 'tpid'],
        ['bs_tournament_matches', 'tpid_b', 'bs_tournament_players', 'tpid'],
        ['bs_tournament_matches', 'winner', 'bs_tournament_players', 'tpid'],
        ['bs_tournament_players', 'partner_tpid', 'bs_tournament_players', 'tpid'],
        ['bs_draws', 'tid', 'bs_tournaments', 'tid'],
    ];

    public function __construct(private readonly SchemaInspector $schema) {}

    public function module(): string
    {
        return 'tournament';
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

        foreach (self::UNIQUES as [$table, $columns]) {
            $results[] = $this->schema->checkUnique($table, $columns);
        }

        $results[] = $this->matchKeys();

        array_push($results, ...$this->formats());
        array_push($results, ...$this->lotOrders());

        foreach (self::ORPHANS as [$child, $childColumn, $parent, $parentColumn]) {
            $results[] = $this->orphans($child, $childColumn, $parent, $parentColumn);
        }

        $results[] = $this->statuses();
        $results[] = $this->activeTournament();

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

    /** UNIQUE (tid, mkey) with the duplicate groups of the old preflight script, listing their tmids. */
    private function matchKeys(): PreflightResult
    {
        $check = 'unique:bs_tournament_matches(tid,mkey)';

        if (! $this->schema->hasColumns('bs_tournament_matches', ['tid', 'mkey'])) {
            return PreflightResult::fail($check, 'column missing');
        }

        $groups = DB::table('bs_tournament_matches')
            ->select('tid', 'mkey')
            ->whereNotNull('mkey')
            ->groupBy('tid', 'mkey')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('tid')
            ->orderBy('mkey')
            ->get();

        $details = [];
        foreach ($groups as $group) {
            $tmids = DB::table('bs_tournament_matches')
                ->where('tid', $group->tid)->where('mkey', $group->mkey)
                ->orderBy('tmid')->pluck('tmid')->map(fn ($id): int => (int) $id)->all();
            $details[] = "tid {$group->tid} {$group->mkey}: tmids ".implode(', ', $tmids);
        }

        $index = $this->schema->hasUniqueIndex('bs_tournament_matches', ['tid', 'mkey']);

        if ($details !== []) {
            return PreflightResult::fail($check, ($index ? '' : 'unique index missing, ').count($details).' duplicate groups ('.implode('; ', $details).')');
        }

        return $index ? PreflightResult::pass($check) : PreflightResult::fail($check, 'unique index missing');
    }

    /** @return list<PreflightResult> */
    private function formats(): array
    {
        $failed = [];
        $count = 0;

        foreach (DB::table('bs_tournaments')->orderBy('tid')->get(['tid', 'format']) as $row) {
            $count++;

            if ($row->format === null || $row->format === '') {
                $failed[] = PreflightResult::fail('format:'.$row->tid, 'no format');

                continue;
            }

            try {
                (new FormatService((string) $row->format))->validate();
            } catch (Throwable $e) {
                $failed[] = PreflightResult::fail('format:'.$row->tid, $e->getMessage());
            }
        }

        return [...$failed, $failed === [] ? PreflightResult::pass('format', "{$count} formats valid") : PreflightResult::fail('format', count($failed)." of {$count} formats invalid")];
    }

    /**
     * lot_orders is NULL or json_encode(array<scope, list<int>>): an object of scope => tpid list (an empty
     * array encodes as []).
     *
     * @return list<PreflightResult>
     */
    private function lotOrders(): array
    {
        $failed = [];

        foreach (DB::table('bs_tournaments')->whereNotNull('lot_orders')->orderBy('tid')->get(['tid', 'lot_orders']) as $row) {
            $orders = json_decode((string) $row->lot_orders, true);
            $problem = null;

            if (! is_array($orders)) {
                $problem = 'not JSON';
            } elseif ($orders !== [] && array_is_list($orders)) {
                $problem = 'a list, expected an object of scopes';
            } else {
                foreach ($orders as $scope => $tpids) {
                    if (! is_array($tpids) || ! array_is_list($tpids) || array_filter($tpids, fn ($tpid): bool => ! is_int($tpid)) !== []) {
                        $problem = "scope {$scope} is not a list of player ids";
                        break;
                    }
                }
            }

            if ($problem !== null) {
                $failed[] = PreflightResult::fail('lot_orders:'.$row->tid, $problem);
            }
        }

        return $failed === [] ? [PreflightResult::pass('lot_orders')] : $failed;
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

        // SchemaInspector::orphanCount() cannot join a table with itself (the column names are not aliased).
        $count = $child === $parent
            ? DB::table($child.' as child')
                ->whereNotNull('child.'.$childColumn)
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from($parent.' as parent')->whereColumn('parent.'.$parentColumn, 'child.'.$childColumn))
                ->count()
            : $this->schema->orphanCount($child, $childColumn, $parent, $parentColumn);

        return $count > 0
            ? PreflightResult::warn($check, "{$count} orphaned rows")
            : PreflightResult::pass($check);
    }

    /** The status ENUM is not maintained (only setup is written): reported for information. */
    private function statuses(): PreflightResult
    {
        $counts = DB::table('bs_tournaments')->selectRaw('status, COUNT(*) AS n')->groupBy('status')->orderBy('status')->get();
        $parts = $counts->map(fn ($row): string => $row->status.' '.(int) $row->n)->all();

        return PreflightResult::pass('data:status', ($parts === [] ? 'no tournaments' : implode(', ', $parts)).' (informational, status is unmaintained)');
    }

    private function activeTournament(): PreflightResult
    {
        $check = 'data:active-tournament';
        $active = DB::table('bs_tournaments')->where('is_test', 0)->where('active', 1)->orderBy('tid')->pluck('tid')->all();

        return count($active) === 1
            ? PreflightResult::pass($check, 'tid '.$active[0])
            : PreflightResult::warn($check, count($active).' real tournaments are active, expected exactly 1'.($active === [] ? '' : ' (tids '.implode(', ', $active).')'));
    }
}
