<?php

declare(strict_types=1);

namespace App\Modules\Draw\Preflight;

use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Services\DrawVerifier;
use App\Modules\TournamentBase\Preflight\ModulePreflight;
use App\Modules\TournamentBase\Preflight\PreflightResult;
use App\Modules\TournamentBase\Preflight\SchemaInspector;

final class DrawPreflight implements ModulePreflight
{
    /** @var array<string, array<string, array{0: string, 1: bool}>> */
    public const COLUMNS = [
        'bs_draws' => [
            'did' => ['int unsigned', false],
            'tid' => ['int unsigned', true],
            'slug' => ['varchar(32)', false],
            'title' => ['varchar(160)', false],
            'status' => ["enum('committed','picking','closed','executed')", false],
            'mode' => ["enum('direct','token')", false],
            'payload' => ['mediumtext', false],
            'commitment' => ['char(64)', false],
            'beacon_source' => ['varchar(255)', false],
            'pick_deadline' => ['datetime', true],
            'closed' => ['datetime', true],
            'server_seed' => ['char(64)', true],
            'beacon_value' => ['varchar(255)', true],
            'final_seed' => ['char(64)', true],
            'result' => ['mediumtext', true],
            'created_uid' => ['int unsigned', false],
            'created' => ['datetime', false],
            'executed' => ['datetime', true],
            'discarded' => ['datetime', true],
            'discarded_reason' => ['varchar(160)', true],
            'applied_at' => ['datetime', true],
            'applied_uid' => ['int unsigned', true],
        ],
        'bs_draw_tokens' => [
            'dtid' => ['int unsigned', false],
            'did' => ['int unsigned', false],
            'position' => ['smallint unsigned', false],
            'token' => ['varchar(16)', false],
            'slot' => ['varchar(8)', true],
            'claimed_tpid' => ['int unsigned', true],
            'claimed_uid' => ['int unsigned', true],
            'claimed' => ['datetime', true],
            'assigned_by' => ["enum('pick','fallback')", true],
        ],
        'bs_draw_eligible' => [
            'deid' => ['int unsigned', false],
            'did' => ['int unsigned', false],
            'tpid' => ['int unsigned', false],
        ],
    ];

    /** @var list<array{0: string, 1: list<string>}> */
    private const UNIQUES = [
        ['bs_draws', ['slug']],
        ['bs_draw_tokens', ['did', 'token']],
        ['bs_draw_tokens', ['did', 'position']],
        ['bs_draw_tokens', ['did', 'claimed_tpid']],
        ['bs_draw_eligible', ['did', 'tpid']],
    ];

    /** @var list<array{0: string, 1: string, 2: string, 3: string}> child table, child column, parent table, parent column */
    private const ORPHANS = [
        ['bs_draw_tokens', 'did', 'bs_draws', 'did'],
        ['bs_draw_eligible', 'did', 'bs_draws', 'did'],
        ['bs_draws', 'tid', 'bs_tournaments', 'tid'],
        ['bs_draw_eligible', 'tpid', 'bs_tournament_players', 'tpid'],
        ['bs_draw_tokens', 'claimed_tpid', 'bs_tournament_players', 'tpid'],
    ];

    public function __construct(
        private readonly SchemaInspector $schema,
        private readonly DrawVerifier $verifier,
    ) {}

    public function module(): string
    {
        return 'draw';
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
            $results[] = $this->unique($table, $columns);
        }

        foreach (self::ORPHANS as [$child, $childColumn, $parent, $parentColumn]) {
            $results[] = $this->orphans($child, $childColumn, $parent, $parentColumn);
        }

        array_push($results, ...$this->verifyDraws());

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

    /**
     * @param  list<string>  $columns
     */
    private function unique(string $table, array $columns): PreflightResult
    {
        $check = "unique:{$table}(".implode(',', $columns).')';

        if (! $this->schema->hasUniqueIndex($table, $columns)) {
            return PreflightResult::fail($check, 'unique index missing');
        }

        if (! $this->schema->hasColumns($table, $columns)) {
            return PreflightResult::fail($check, 'column missing');
        }

        $groups = $this->schema->duplicateGroups($table, $columns);

        return $groups > 0
            ? PreflightResult::fail($check, "{$groups} duplicate groups")
            : PreflightResult::pass($check);
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
     * @return list<PreflightResult>
     */
    private function verifyDraws(): array
    {
        $results = [];
        $count = 0;

        foreach (Draw::query()->orderBy('did')->cursor() as $draw) {
            $count++;
            $verification = $this->verifier->verify($draw);
            $check = "verify:{$verification->slug}";

            $failures = $verification->executed()
                ? $verification->failures()
                : (($verification->checks['canonical'] ?? null) === false ? ['canonical'] : []);

            $results[] = $failures === []
                ? PreflightResult::pass($check, $verification->executed() ? 'recomputed' : 'canonical')
                : PreflightResult::fail($check, implode(', ', $failures));
        }

        $results[] = PreflightResult::pass('verify', "{$count} draws checked");

        return $results;
    }
}
