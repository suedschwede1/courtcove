<?php

declare(strict_types=1);

/**
 * Generates tests/Fixtures/tournament/vectors.json (and formats/reference.json) from the framework-free
 * services of the old ZF2 install: FormatService, StandingsService, GeneratorService, ResolverService and
 * TournamentManager (over in-memory table stubs, with the real old DrawService for the lot tie-break).
 *
 * Usage: php tests/Fixtures/tournament/generate_vectors.php <old-repo-root> <out-dir>
 *
 * Plain PHP, no Laravel. Reads the old repository only (never modifies it) and refuses to run unless
 * modulex/Tournament and modulex/Draw equal the reference commit (line endings ignored, no untracked files).
 * <out-dir>/formats/{small-two-groups,three-groups-with-bye}.json are hand-written inputs and must exist.
 *
 * All data is fictional: players are "Demo Spieler NN", tpid 1..24, seed = tpid.
 */
const OLD_REPO_COMMIT = '6b780d3324936bab3749237183c2dab161873612';
const CHECKED_PATHS = ['modulex/Tournament', 'modulex/Draw'];
const SOURCE_FILES = [
    'modulex/Tournament/src/Tournament/Service/FormatService.php',
    'modulex/Tournament/src/Tournament/Service/StandingsService.php',
    'modulex/Tournament/src/Tournament/Service/GeneratorService.php',
    'modulex/Tournament/src/Tournament/Service/ResolverService.php',
    'modulex/Tournament/src/Tournament/Service/ScoreService.php',
    'modulex/Tournament/src/Tournament/Manager/TournamentManager.php',
    'modulex/Tournament/src/Tournament/Table/TournamentTable.php',
    'modulex/Tournament/src/Tournament/Table/PlayerTable.php',
    'modulex/Tournament/src/Tournament/Table/MatchTable.php',
    'modulex/Tournament/src/Tournament/Table/LineTable.php',
    'modulex/Draw/src/Draw/Service/DrawService.php',
    'modulex/Draw/src/Draw/Table/DrawTable.php',
];

if ($argc !== 3) {
    fwrite(STDERR, "usage: php generate_vectors.php <old-repo-root> <out-dir>\n");
    exit(2);
}

$old = rtrim($argv[1], '/\\');
$outDir = rtrim($argv[2], '/\\');

function git(string $old, string $args, ?int &$code = null): string
{
    exec('git -C '.escapeshellarg($old).' '.$args.' 2>&1', $lines, $code);

    return trim(implode("\n", $lines));
}

git($old, 'cat-file -e '.OLD_REPO_COMMIT.'^{commit}', $code);
if ($code !== 0) {
    fwrite(STDERR, 'reference commit '.OLD_REPO_COMMIT." not found in $old\n");
    exit(1);
}
$paths = implode(' ', CHECKED_PATHS);
git($old, 'diff --ignore-cr-at-eol --quiet '.OLD_REPO_COMMIT.' -- '.$paths, $code);
if ($code !== 0) {
    fwrite(STDERR, "$paths differ from ".OLD_REPO_COMMIT." (ignoring CR at EOL)\n");
    exit(1);
}
if (git($old, 'ls-files --others --exclude-standard -- '.$paths) !== '') {
    fwrite(STDERR, "$paths contain untracked files\n");
    exit(1);
}

error_reporting(E_ALL & ~E_DEPRECATED);
require $old.'/vendor/autoload.php';
foreach ([
    'modulex/Draw/src/Draw/Service/DrawService.php',
    'modulex/Draw/src/Draw/Table/DrawTable.php',
    'modulex/Tournament/src/Tournament/Service/ScoreService.php',
    'modulex/Tournament/src/Tournament/Service/FormatService.php',
    'modulex/Tournament/src/Tournament/Service/StandingsService.php',
    'modulex/Tournament/src/Tournament/Service/GeneratorService.php',
    'modulex/Tournament/src/Tournament/Service/ResolverService.php',
    'modulex/Tournament/src/Tournament/Table/TournamentTable.php',
    'modulex/Tournament/src/Tournament/Table/PlayerTable.php',
    'modulex/Tournament/src/Tournament/Table/MatchTable.php',
    'modulex/Tournament/src/Tournament/Table/LineTable.php',
    'modulex/Tournament/src/Tournament/Manager/TournamentManager.php',
] as $file) {
    require_once $old.'/'.$file;
}

use Draw\Service\DrawService;
use Draw\Table\DrawTable;
use Tournament\Manager\TournamentManager;
use Tournament\Service\FormatService;
use Tournament\Service\GeneratorService;
use Tournament\Service\ResolverService;
use Tournament\Service\ScoreService;
use Tournament\Service\StandingsService;
use Tournament\Table\MatchTable;
use Tournament\Table\PlayerTable;
use Tournament\Table\TournamentTable;

// ------------------------------------------------------------------ in-memory table stubs

/** Minimal stand-in for Zend\Db\Sql\Select as used by the old manager (where array/string, order, limit). */
class StubSelect
{
    /** @var array<string, mixed> */
    public array $filters = [];

    public string $order = '';

    public ?int $limit = null;

    public function where($where)
    {
        if (is_array($where)) {
            $this->filters = array_merge($this->filters, $where);
        } elseif (preg_match('/^(\w+) = (\d+)$/', (string) $where, $m)) {
            $this->filters[$m[1]] = (int) $m[2];
        } else {
            throw new RuntimeException('unsupported where clause '.$where);
        }

        return $this;
    }

    public function order($order)
    {
        $this->order = (string) $order;

        return $this;
    }

    public function limit($limit)
    {
        $this->limit = (int) $limit;

        return $this;
    }
}

/** Result set with the two accessors the old code uses: iteration and current(). */
class StubResult implements IteratorAggregate
{
    public function __construct(private array $rows) {}

    public function current()
    {
        return $this->rows[0] ?? null;
    }

    public function getIterator(): Iterator
    {
        return new ArrayIterator($this->rows);
    }
}

function stub_select(array $rows, $where): StubResult
{
    $select = new StubSelect;
    if ($where instanceof Closure) {
        $where($select);
    } elseif (is_array($where)) {
        $select->where($where);
    }

    foreach ($select->filters as $column => $value) {
        $rows = array_values(array_filter($rows, fn ($row) => (string) $row->$column === (string) $value));
    }

    if ($select->order !== '') {
        $keys = [];
        foreach (explode(',', $select->order) as $part) {
            $bits = preg_split('/\s+/', trim($part));
            $keys[] = [$bits[0], strtoupper($bits[1] ?? 'ASC') === 'DESC' ? -1 : 1];
        }
        usort($rows, function ($a, $b) use ($keys) {
            foreach ($keys as [$column, $dir]) {
                $cmp = $a->$column <=> $b->$column;
                if ($cmp !== 0) {
                    return $cmp * $dir;
                }
            }

            return 0;
        });
    }

    if ($select->limit !== null) {
        $rows = array_slice($rows, 0, $select->limit);
    }

    return new StubResult($rows);
}

class StubTournamentTable extends TournamentTable
{
    public array $rows = [];

    public function __construct() {}

    public function select($where = null)
    {
        return stub_select($this->rows, $where);
    }
}

class StubPlayerTable extends PlayerTable
{
    public array $rows = [];

    public function __construct() {}

    public function select($where = null)
    {
        return stub_select($this->rows, $where);
    }
}

class StubMatchTable extends MatchTable
{
    public array $rows = [];

    public function __construct() {}

    public function select($where = null)
    {
        return stub_select($this->rows, $where);
    }
}

class StubDrawTable extends DrawTable
{
    public array $rows = [];

    public function __construct() {}

    public function select($where = null)
    {
        return stub_select($this->rows, $where);
    }
}

// ------------------------------------------------------------------ helpers

function capture(callable $fn): array
{
    try {
        return ['ok' => $fn()];
    } catch (Throwable $e) {
        return ['error' => $e->getMessage(), 'exception' => get_class($e)];
    }
}

function row(int $a, int $b, ?int $winner, int $sa, int $sb, int $ga, int $gb, array $extra = []): array
{
    return array_merge([
        'tpid_a' => $a, 'tpid_b' => $b, 'winner' => $winner,
        'sets_a' => $sa, 'sets_b' => $sb, 'games_a' => $ga, 'games_b' => $gb,
    ], $extra);
}

/** A played match whose set/game numbers come from the old ScoreService (so MTB counts 1:0). */
function scored(int $a, int $b, array $score): array
{
    $r = (new ScoreService)->evaluate($score);
    $winner = $r['winner_side'] === 'a' ? $a : $b;

    return row($a, $b, $winner, $r['sets_a'], $r['sets_b'], $r['games_a'], $r['games_b'], ['score_input' => $score]);
}

function demo_players(): array
{
    $players = [];
    for ($i = 1; $i <= 24; $i++) {
        $players[] = ['tpid' => $i, 'label' => sprintf('Demo Spieler %02d', $i), 'seed' => $i, 'points_adjust' => 0];
    }

    return $players;
}

function players_of(array $tpids, array $adjust = []): array
{
    return array_map(fn (int $t) => [
        'tpid' => $t, 'label' => sprintf('Demo Spieler %02d', $t), 'seed' => $t, 'points_adjust' => $adjust[$t] ?? 0,
    ], $tpids);
}

function standings_service(array $format, array $overrides = []): StandingsService
{
    $f = new FormatService($format);
    $ranking = array_merge($f->ranking(), $overrides);

    return (new StandingsService)
        ->setPointsPerWin($ranking['points_per_win'])
        ->setTiebreakScope($ranking['tiebreak_scope'])
        ->setRunnerUpMode($overrides['runner_up'] ?? $f->runnerUpMode());
}

// ------------------------------------------------------------------ reference format

$sql = file_get_contents($old.'/sql/tournament_schema_04_format.sql');
if (! preg_match("/SET format = '(\{.*\})'\s*WHERE season/s", (string) $sql, $m)) {
    fwrite(STDERR, "format JSON not found in tournament_schema_04_format.sql\n");
    exit(1);
}
$reference = json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
// sql/tournament_schema_10_ko_b_pre.sql: phases[3].draw.timing = 'pre'
if (($reference['phases'][3]['key'] ?? null) !== 'KO_B') {
    fwrite(STDERR, "phases[3] is not KO_B\n");
    exit(1);
}
$reference['phases'][3]['draw']['timing'] = 'pre';

$prettyFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
@mkdir($outDir.'/formats', 0777, true);
file_put_contents($outDir.'/formats/reference.json', json_encode($reference, $prettyFlags)."\n");

$formats = [];
foreach (['reference', 'small-two-groups', 'three-groups-with-bye'] as $name) {
    $path = $outDir.'/formats/'.$name.'.json';
    if (! is_file($path)) {
        fwrite(STDERR, "missing $path\n");
        exit(1);
    }
    $formats[$name] = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
}

// ------------------------------------------------------------------ format[]

$vectors = [];
$formatVectors = [];
foreach ($formats as $name => $format) {
    $f = new FormatService($format);
    $entry = [
        'name' => $name,
        'format' => $format,
        'validate' => capture(fn () => $f->validate()),
        'match_format' => $f->matchFormat(),
        'ranking' => $f->ranking(),
        'runner_up_mode' => $f->runnerUpMode(),
        'phases' => $f->phases(),
        'group_phases' => array_column($f->groupPhases(), 'key'),
        'bracket_phases' => array_column($f->bracketPhases(), 'key'),
        'phase_lookup_error' => capture(fn () => $f->phase('NOPE')),
        'pairings' => [2 => $f->pairings(2), 3 => $f->pairings(3), 4 => $f->pairings(4), 5 => $f->pairings(5)],
        'first_round_pairs' => [8 => $f->firstRoundPairs(8), 16 => $f->firstRoundPairs(16)],
        'rounds' => [2 => $f->rounds(2), 4 => $f->rounds(4), 8 => $f->rounds(8), 16 => $f->rounds(16), 32 => $f->rounds(32)],
        'group_slots' => [],
        'draw_slots' => [],
        'group_matches' => [],
        'line_map' => [],
    ];
    foreach ($f->groupPhases() as $p) {
        $entry['group_slots'][$p['key']] = $f->groupSlots($p['key']);
        $entry['draw_slots'][$p['key']] = $f->drawSlots($p['key']);
        $entry['group_matches'][$p['key']] = $f->groupMatches($p['key']);
    }
    foreach ($f->bracketPhases() as $p) {
        $entry['line_map'][$p['key']] = $f->lineMap($p['key']);
    }
    $formatVectors[] = $entry;
}

$variant = function (string $name, callable $mutate) use ($reference): array {
    $format = $reference;
    $mutate($format);
    $f = new FormatService($format);

    return [
        'name' => $name,
        'invalid' => true,
        'format' => $format,
        'validate' => capture(fn () => $f->validate()),
        'phase_lookup_error' => capture(fn () => @$f->phase('NOPE')),
    ];
};

$formatVectors[] = $variant('invalid-bracket-size-12', function (&$fmt) {
    $fmt['phases'][2]['size'] = 12;
});
$formatVectors[] = $variant('invalid-group-size-5', function (&$fmt) {
    $fmt['phases'][0]['groups'][0]['size'] = 5;
});
$formatVectors[] = $variant('invalid-pool-violates-round-1-protection', function (&$fmt) {
    $fmt['phases'][2]['lines']['pools'] = ['q' => [3, 4, 7, 10, 11, 13], 'ac' => [2, 5, 6, 12, 14, 15]];
});
$formatVectors[] = $variant('invalid-unknown-phase-type', function (&$fmt) {
    $fmt['phases'][1]['type'] = 'swiss';
});
$formatVectors[] = $variant('invalid-duplicate-phase-key', function (&$fmt) {
    $fmt['phases'][1]['key'] = 'AC';
});
$formatVectors[] = $variant('invalid-phase-without-key', function (&$fmt) {
    unset($fmt['phases'][1]['key']);
});
$formatVectors[] = $variant('invalid-bracket-line-left-unassigned', function (&$fmt) {
    $fmt['phases'][3]['lines']['pools']['rest'] = [2, 3, 4, 5, 6, 6];
});
$formatVectors[] = $variant('invalid-bracket-line-assigned-twice-by-bye', function (&$fmt) {
    $fmt['phases'][3]['lines']['bye'] = [2];
});
$formatVectors[] = $variant('invalid-heads-leave-nothing-to-draw', function (&$fmt) {
    $fmt['phases'][0]['heads'] = 3;
});
$formatVectors[] = $variant('invalid-no-phases', function (&$fmt) {
    $fmt['phases'] = [];
});
$vectors['format'] = $formatVectors;

// ------------------------------------------------------------------ generator[]

$gen = new GeneratorService;
$refF = new FormatService($reference);
$smallF = new FormatService($formats['small-two-groups']);
$byeF = new FormatService($formats['three-groups-with-bye']);
$all24 = demo_players();
$shuffled24 = array_reverse($all24);

$byGroupFull = [
    'A' => [1, 4, 5], 'B' => [2, 6, 7], 'C' => [3, 8, 9, 10],
];
$groupPlayers = function (array $assignment): array {
    $out = [];
    foreach ($assignment as $grp => $tpids) {
        foreach ($tpids as $i => $tpid) {
            if ($tpid === null) {
                continue;
            }
            $out[$grp][] = ['tpid' => $tpid, 'grp' => $grp, 'grp_pos' => $i + 1];
        }
    }

    return $out;
};

$heads2 = $formats['small-two-groups'];
$heads2['phases'][0]['heads'] = 2;
$heads4 = $formats['small-two-groups'];
$heads4['phases'][0]['heads'] = 3;

$generatorVectors = [
    ['op' => 'assignHeads', 'name' => 'reference-AC', 'format' => 'reference', 'phase' => 'AC', 'players' => $shuffled24,
        'result' => capture(fn () => $gen->assignHeads($refF, 'AC', $shuffled24))],
    ['op' => 'assignHeads', 'name' => 'reference-DG', 'format' => 'reference', 'phase' => 'DG', 'players' => $shuffled24,
        'result' => capture(fn () => $gen->assignHeads($refF, 'DG', $shuffled24))],
    ['op' => 'assignHeads', 'name' => 'two-heads-per-group', 'format' => 'small-two-groups', 'format_override' => $heads2, 'phase' => 'G1',
        'players' => array_slice($all24, 0, 6), 'result' => capture(fn () => $gen->assignHeads(new FormatService($heads2), 'G1', array_slice($all24, 0, 6)))],
    ['op' => 'assignHeads', 'name' => 'not-enough-players', 'format' => 'reference', 'phase' => 'DG', 'players' => array_slice($all24, 0, 12),
        'result' => capture(fn () => $gen->assignHeads($refF, 'DG', array_slice($all24, 0, 12)))],
    ['op' => 'assignHeads', 'name' => 'no-heads-configured', 'format' => 'small-two-groups', 'format_override' => ($nh = array_replace_recursive($formats['small-two-groups'], ['phases' => [0 => ['heads' => 0]]])), 'phase' => 'G1',
        'players' => array_slice($all24, 0, 6), 'result' => capture(fn () => $gen->assignHeads(new FormatService($nh), 'G1', array_slice($all24, 0, 6)))],
];

$partial = ['A' => [1, 4, 5], 'B' => [2, 6, null], 'C' => [3, null, 9, null]];
$partialNoPos = $groupPlayers($byGroupFull);
$partialNoPos['B'][1]['grp_pos'] = null;
foreach ([
    ['full-empty-existing', $groupPlayers($byGroupFull), []],
    ['partial-existing-mkeys', $groupPlayers($byGroupFull), ['A-0', 'C-1', 'C-5']],
    ['half-drawn-groups', $groupPlayers($partial), []],
    ['missing-group-and-unpositioned-player', array_diff_key($partialNoPos, ['C' => 1]), []],
    ['position-order-independent', array_map('array_reverse', $groupPlayers($byGroupFull)), []],
] as [$caseName, $byGroup, $existing]) {
    $generatorVectors[] = [
        'op' => 'groupMatches', 'name' => $caseName, 'format' => 'reference', 'phase' => 'AC',
        'players_by_group' => $byGroup, 'existing' => $existing,
        'result' => $gen->groupMatches($refF, 'AC', $byGroup, $existing),
    ];
}

foreach (['reference' => [$refF, ['KO_A', 'KO_B']], 'small-two-groups' => [$smallF, ['KO']], 'three-groups-with-bye' => [$byeF, ['KO']]] as $fname => [$fs, $phaseKeys]) {
    foreach ($phaseKeys as $pk) {
        $generatorVectors[] = ['op' => 'bracketLines', 'name' => "$fname-$pk", 'format' => $fname, 'phase' => $pk,
            'result' => $gen->bracketLines($fs, $pk)];
        $generatorVectors[] = ['op' => 'bracketMatches', 'name' => "$fname-$pk", 'format' => $fname, 'phase' => $pk, 'existing' => [],
            'result' => $gen->bracketMatches($fs, $pk)];
    }
}
$generatorVectors[] = ['op' => 'bracketMatches', 'name' => 'reference-KO_A-partial-existing', 'format' => 'reference', 'phase' => 'KO_A',
    'existing' => ['KO_A-R1-1', 'KO_A-R1-8', 'KO_A-R3-2', 'KO_A-R4-1'], 'result' => $gen->bracketMatches($refF, 'KO_A', ['KO_A-R1-1', 'KO_A-R1-8', 'KO_A-R3-2', 'KO_A-R4-1'])];

foreach ([1, 2, 3, 4] as $round) {
    foreach (range(1, 8) as $slot) {
        $generatorVectors[] = ['op' => 'advance', 'name' => "KO_A-r$round-s$slot", 'format' => 'reference', 'phase' => 'KO_A',
            'round' => $round, 'slot' => $slot, 'result' => $gen->advance($refF, 'KO_A', $round, $slot)];
    }
}
foreach ([1, 2, 3] as $round) {
    foreach (range(1, 4) as $slot) {
        $generatorVectors[] = ['op' => 'advance', 'name' => "KO_B-r$round-s$slot", 'format' => 'reference', 'phase' => 'KO_B',
            'round' => $round, 'slot' => $slot, 'result' => $gen->advance($refF, 'KO_B', $round, $slot)];
    }
}

$generatorVectors[] = ['op' => 'poolPlaceholders', 'name' => 'reference-KO_A', 'format' => 'reference', 'phase' => 'KO_A',
    'prefixes' => ['q' => 'Q', 'ac' => 'AC'], 'result' => capture(fn () => $gen->poolPlaceholders($refF, 'KO_A', ['q' => 'Q', 'ac' => 'AC']))];
$generatorVectors[] = ['op' => 'poolPlaceholders', 'name' => 'reference-KO_B', 'format' => 'reference', 'phase' => 'KO_B',
    'prefixes' => ['rest' => 'REST'], 'result' => capture(fn () => $gen->poolPlaceholders($refF, 'KO_B', ['rest' => 'REST']))];
$generatorVectors[] = ['op' => 'poolPlaceholders', 'name' => 'missing-prefix', 'format' => 'reference', 'phase' => 'KO_A',
    'prefixes' => ['q' => 'Q'], 'result' => capture(fn () => $gen->poolPlaceholders($refF, 'KO_A', ['q' => 'Q']))];
$vectors['generator'] = $generatorVectors;

// ------------------------------------------------------------------ standings[]

$stdFormat = $reference;
$rankCase = function (string $name, array $tpids, array $matches, array $cfg = [], array $adjust = []) use ($stdFormat): array {
    $svc = standings_service($stdFormat, $cfg);
    $players = players_of($tpids, $adjust);

    return [
        'op' => 'rank', 'name' => $name, 'config' => $cfg + ['points_per_win' => 2, 'tiebreak_scope' => 'tied'],
        'players' => $players, 'matches' => $matches, 'result' => $svc->rank($players, $matches),
    ];
};

$plain = [row(1, 2, 1, 2, 0, 12, 6), row(1, 3, 1, 2, 0, 12, 4), row(2, 3, 2, 2, 1, 14, 12)];
$cycle = [
    row(1, 2, 1, 2, 0, 12, 6), row(2, 3, 2, 2, 0, 12, 6), row(3, 1, 3, 2, 1, 15, 13),
    row(1, 4, 1, 2, 1, 14, 12), row(2, 4, 2, 2, 1, 13, 12), row(3, 4, 3, 2, 0, 12, 0),
];
$unmet = [row(1, 3, 1, 2, 0, 12, 4), row(2, 4, 2, 2, 1, 14, 12)];
$gameDiffMatches = [row(1, 3, 1, 2, 0, 12, 4), row(2, 4, 2, 2, 0, 12, 8)];
$mtb = [
    scored(1, 3, ['s1a' => 6, 's1b' => 4, 's2a' => 3, 's2b' => 6, 'mtba' => 10, 'mtbb' => 8]),
    scored(2, 4, ['s1a' => 3, 's1b' => 6, 's2a' => 6, 's2b' => 4, 'mtba' => 10, 'mtbb' => 2]),
];
$mtbDecides = [
    scored(1, 3, ['s1a' => 6, 's1b' => 4, 's2a' => 3, 's2b' => 6, 'mtba' => 10, 'mtbb' => 8]),
    scored(2, 4, ['s1a' => 6, 's1b' => 3, 's2a' => 3, 's2b' => 6, 'mtba' => 10, 'mtbb' => 8]),
];
$tripleTie = [
    row(1, 2, 1, 2, 0, 12, 6), row(2, 3, 2, 2, 0, 12, 6), row(3, 1, 3, 2, 0, 12, 6),
    row(1, 4, 1, 2, 0, 12, 6), row(2, 4, 2, 2, 0, 12, 6), row(3, 4, 3, 2, 0, 12, 6),
];
$walkover = [
    row(1, 2, 2, 0, 2, 0, 12, ['retired' => 'wo_a']), row(1, 3, 1, 2, 0, 12, 6), row(2, 3, 3, 0, 2, 0, 12, ['retired' => 'wo_a']),
];
$withOpen = [
    row(1, 2, 1, 2, 0, 12, 6), row(1, 3, null, 0, 0, 0, 0), row(2, 3, 0, 2, 1, 14, 12),
    ['tpid_a' => 2, 'tpid_b' => 3, 'winner' => null, 'sets_a' => null, 'sets_b' => null, 'games_a' => null, 'games_b' => null],
];

$standingsVectors = [
    $rankCase('plain-points', [1, 2, 3], $plain),
    $rankCase('points-adjust-creates-a-tie', [1, 2, 3], $plain, [], [1 => 2]),
    $rankCase('head-to-head-pair-decides-against-better-overall-diff', [1, 2, 3, 4], [
        row(1, 2, 1, 2, 0, 12, 6), row(1, 3, 1, 2, 0, 12, 10), row(4, 1, 4, 2, 0, 12, 10),
        row(2, 3, 2, 2, 0, 12, 0), row(2, 4, 2, 2, 0, 12, 0), row(3, 4, 3, 2, 0, 12, 4),
    ]),
    $rankCase('triple-tie-scope-tied', [1, 2, 3, 4], $cycle, ['tiebreak_scope' => 'tied']),
    $rankCase('triple-tie-scope-all', [1, 2, 3, 4], $cycle, ['tiebreak_scope' => 'all']),
    $rankCase('unmet-pair-scope-tied-needs-lot', [1, 2, 3, 4], $unmet, ['tiebreak_scope' => 'tied']),
    $rankCase('set-diff-decides-unmet-pair-scope-all', [1, 2, 3, 4], $unmet, ['tiebreak_scope' => 'all']),
    $rankCase('game-diff-decides-unmet-pair-scope-all', [1, 2, 3, 4], $gameDiffMatches, ['tiebreak_scope' => 'all']),
    $rankCase('match-tiebreak-counts-one-game-and-leaves-lot', [1, 2, 3, 4], $mtb, ['tiebreak_scope' => 'all']),
    $rankCase('match-tiebreak-counts-one-game-decides', [1, 2, 3, 4], $mtbDecides, ['tiebreak_scope' => 'all']),
    $rankCase('triple-tie-needs-lot', [1, 2, 3, 4], $tripleTie),
    $rankCase('walkover-counts-as-12-0', [1, 2, 3], $walkover),
    $rankCase('matches-without-winner-are-ignored', [1, 2, 3], $withOpen),
    $rankCase('three-points-per-win', [1, 2, 3], $plain, ['points_per_win' => 3]),
    $rankCase('no-matches', [1, 2, 3, 4], []),
];

// resolveLot on the triple-tie result
$tieSvc = standings_service($stdFormat);
$tieRows = $tieSvc->rank(players_of([1, 2, 3, 4]), $tripleTie);
foreach ([
    ['order-resolves-the-tie', [3, 1, 2]],
    ['order-with-duplicates', [3, 3, 1, 2]],
    ['order-missing-a-player', [3, 1]],
    ['order-with-foreign-player', [3, 1, 2, 4]],
] as [$caseName, $order]) {
    $standingsVectors[] = [
        'op' => 'resolveLot', 'name' => $caseName, 'rows' => $tieRows, 'order' => $order,
        'result' => capture(fn () => $tieSvc->resolveLot($tieRows, $order)),
    ];
}
// KNOWN ZF2 QUIRK, pinned on purpose (not a target behaviour): two separate needs_lot blocks at different
// points in one group (1 beat 3, 2 beat 4: {1,2} at 2 points, {3,4} at 0) get ONE derived lot order over all
// four players, and resolveLot's comparator then orders across the blocks (here 1(2) 3(0) 2(2) 4(0)). Only
// mid-season tables are affected; changing it is a rules change for the club admin.
$twoBlocks = [row(1, 3, 1, 2, 0, 12, 6), row(2, 4, 2, 2, 0, 12, 6)];
$twoBlockRows = $tieSvc->rank(players_of([1, 2, 3, 4]), $twoBlocks);
$standingsVectors[] = [
    'op' => 'resolveLot', 'name' => 'zf2-quirk-two-separate-ties-ordered-across-blocks', 'rows' => $twoBlockRows, 'order' => [1, 3, 2, 4],
    'result' => capture(fn () => $tieSvc->resolveLot($twoBlockRows, [1, 3, 2, 4])),
];
$vectors['standings'] = $standingsVectors;

// ------------------------------------------------------------------ runner_up[]

$secondsRun = [
    ['tpid' => 2, 'grp' => 'D', 'seed' => 2, 'points' => 2, 'wins' => 1, 'losses' => 1, 'played' => 2, 'set_diff' => 0, 'game_diff' => 1],
    ['tpid' => 6, 'grp' => 'F', 'seed' => 6, 'points' => 4, 'wins' => 2, 'losses' => 1, 'played' => 3, 'set_diff' => 1, 'game_diff' => 5],
];
$tablesRun = [
    'D' => [['tpid' => 1, 'rank' => 1, 'needs_lot' => false], ['tpid' => 2, 'rank' => 2, 'needs_lot' => false], ['tpid' => 3, 'rank' => 3, 'needs_lot' => false]],
    'F' => [['tpid' => 5, 'rank' => 1, 'needs_lot' => false], ['tpid' => 6, 'rank' => 2, 'needs_lot' => false], ['tpid' => 7, 'rank' => 3, 'needs_lot' => false], ['tpid' => 8, 'rank' => 4, 'needs_lot' => false]],
];
$matchesRun = [row(1, 2, 1, 2, 1, 12, 10), row(2, 3, 2, 2, 0, 12, 6), row(5, 6, 5, 2, 0, 12, 7), row(6, 7, 6, 2, 1, 11, 9), row(6, 8, 6, 2, 0, 12, 2)];
$tablesLotOpen = $tablesRun;
$tablesLotOpen['F'][3]['needs_lot'] = true;

$runCase = function (string $name, string $mode, array $seconds, array $tables, array $matches) use ($stdFormat): array {
    $svc = standings_service($stdFormat, ['runner_up' => $mode]);

    return [
        'op' => 'rankRunnersUp', 'name' => $name, 'mode' => $mode, 'seconds' => $seconds, 'group_standings' => $tables,
        'matches' => $matches, 'result' => capture(fn () => $svc->rankRunnersUp($seconds, $tables, $matches)),
    ];
};

$perMatchRows = [
    ['tpid' => 11, 'grp' => 'D', 'seed' => 11, 'points' => 4, 'wins' => 2, 'losses' => 1, 'played' => 3, 'set_diff' => 2, 'game_diff' => 6],
    ['tpid' => 12, 'grp' => 'E', 'seed' => 12, 'points' => 2, 'wins' => 1, 'losses' => 0, 'played' => 1, 'set_diff' => 2, 'game_diff' => 6],
    ['tpid' => 13, 'grp' => 'F', 'seed' => 13, 'points' => 2, 'wins' => 1, 'losses' => 0, 'played' => 1, 'set_diff' => 2, 'game_diff' => 6],
];
$vectors['runner_up'] = [
    $runCase('drop-last-run-php-case', 'drop_last', $secondsRun, $tablesRun, $matchesRun),
    $runCase('drop-last-open-lot-for-last-place-throws', 'drop_last', $secondsRun, $tablesLotOpen, $matchesRun),
    $runCase('drop-last-group-standings-missing-throws', 'drop_last', $secondsRun, ['D' => $tablesRun['D']], $matchesRun),
    $runCase('points-mode', 'points', $secondsRun, $tablesRun, $matchesRun),
    $runCase('points-mode-equal-rows-need-lot', 'points', $perMatchRows, [], []),
    $runCase('per-match-mode', 'per_match', $perMatchRows, [], []),
    $runCase('lot-mode-marks-everyone', 'lot', $secondsRun, [], []),
];

// ------------------------------------------------------------------ shared group datasets

/** deterministic result: the lower tpid wins 2:0, games 12:(4 + (5a + 3b^2) % 8) */
$autoMatch = function (int $a, int $b): array {
    [$lo, $hi] = $a < $b ? [$a, $b] : [$b, $a];
    $g = 4 + (($a * 5 + $b * $b * 3) % 8);

    return $lo === $a ? row($a, $b, $a, 2, 0, 12, $g) : row($a, $b, $b, 0, 2, $g, 12);
};
$groupsAC = ['A' => [1, 4, 5], 'B' => [2, 6, 7], 'C' => [3, 8, 9, 10]];
$groupsDG = ['D' => [11, 15, 16], 'E' => [12, 17, 18], 'F' => [13, 19, 20, 21], 'G' => [14, 22, 23, 24]];

/** all pairings of a group in the format's order as match rows with mkey/tmid */
$tmid = 0;
$groupMatchRows = function (FormatService $f, array $assignment, callable $result) use (&$tmid): array {
    $rows = [];
    foreach ($assignment as $grp => $tpids) {
        foreach ($f->pairings(count($tpids)) as $index => $pair) {
            $a = $tpids[$pair[0]];
            $b = $tpids[$pair[1]];
            $rows[] = array_merge([
                'tmid' => ++$tmid, 'tid' => 1, 'phase' => 'group', 'mkey' => $grp.'-'.$index, 'grp' => $grp, 'rnd' => null,
                'slot' => $index + 1, 'deadline' => null, 'played' => null, 'played_time' => null, 'court' => null, 'score' => null,
                's1a' => null, 's1b' => null, 's2a' => null, 's2b' => null, 'mtba' => null, 'mtbb' => null,
                'sets_a' => null, 'sets_b' => null, 'games_a' => null, 'games_b' => null, 'retired' => 'none', 'winner' => null,
                'entered_uid' => null, 'entered' => null, 'confirmed_uid' => null, 'confirmed' => null,
                'tpid_a' => $a, 'tpid_b' => $b,
            ], $result($a, $b, $grp, $index));
        }
    }

    return $rows;
};
$played = fn (array $r) => $r + ['played' => '2027-05-02', 'score' => '6:3 6:2'];

$ranked = function (array $assignment, array $matches, array $cfg = []) use ($stdFormat): array {
    $svc = standings_service($stdFormat, $cfg);
    $out = [];
    foreach ($assignment as $grp => $tpids) {
        $out[$grp] = $svc->rank(players_of($tpids), array_values(array_filter($matches, fn ($m) => ($m['grp'] ?? $grp) === $grp && $m['winner'])));
    }

    return $out;
};

// complete, no ties
$completeAC = array_map(fn ($r) => $r, $groupMatchRows($refF, $groupsAC, fn ($a, $b) => $played($autoMatch($a, $b))));
$completeDG = $groupMatchRows($refF, $groupsDG, fn ($a, $b) => $played($autoMatch($a, $b)));
$stAC = $ranked($groupsAC, $completeAC);
$stDG = $ranked($groupsDG, $completeDG);

// A and B identical -> cross-group ties for places 1 and 2
$tiedResult = function (int $a, int $b, string $grp) use ($played): array {
    return $played(['winner' => $a < $b ? $a : $b, 'sets_a' => $a < $b ? 2 : 0, 'sets_b' => $a < $b ? 0 : 2, 'games_a' => $a < $b ? 12 : 6, 'games_b' => $a < $b ? 6 : 12]);
};
$tiedAC = $groupMatchRows($refF, $groupsAC, fn ($a, $b, $grp) => $grp === 'C' ? $played($autoMatch($a, $b)) : $tiedResult($a, $b, $grp));
$stTied = $ranked($groupsAC, $tiedAC);

$stripped = fn (array $matches) => array_map(fn ($m) => array_intersect_key($m, array_flip(['tpid_a', 'tpid_b', 'winner', 'sets_a', 'sets_b', 'games_a', 'games_b', 'grp'])), $matches);
$resolverFor = function (array $cfg = []) use ($stdFormat): ResolverService {
    return new ResolverService(standings_service($stdFormat, $cfg));
};

// ------------------------------------------------------------------ resolver[]

$resolverVectors = [];
$byPlace = function (string $name, int $place, array $standings, array $matches, ?string $phaseKey, array $lotOrders = [], ?string $lotResolver = null) use ($resolverFor, $stripped): array {
    $calls = [];
    $r = $resolverFor();
    $r->setLotOrders($lotOrders);
    if ($lotResolver !== null) {
        $r->setLotResolver(function ($scope, array $tied) use (&$calls, $lotResolver) {
            $calls[] = ['scope' => $scope, 'tied' => $tied];

            return $lotResolver === 'reverse' ? array_reverse($tied) : $tied;
        });
    }

    return [
        'op' => 'byPlace', 'name' => $name, 'place' => $place, 'standings' => $standings, 'matches' => $stripped($matches),
        'phase_key' => $phaseKey, 'lot_orders' => $lotOrders, 'lot_resolver' => $lotResolver,
        'result' => capture(fn () => $r->byPlace($place, $standings, $stripped($matches), $phaseKey)), 'lot_resolver_calls' => $calls,
    ];
};

foreach ([1, 2, 3] as $place) {
    $resolverVectors[] = $byPlace("complete-AC-place-$place", $place, $stAC, $completeAC, 'AC');
}
$resolverVectors[] = $byPlace('tied-AC-place-1-needs-lot', 1, $stTied, $tiedAC, 'AC');
$resolverVectors[] = $byPlace('tied-AC-place-1-manual-lot-order', 1, $stTied, $tiedAC, 'AC', ['phase:AC:place:1' => [2, 1]]);
$resolverVectors[] = $byPlace('tied-AC-place-2-lot-resolver', 2, $stTied, $tiedAC, 'AC', [], 'reverse');
$resolverVectors[] = $byPlace('tied-AC-place-2-manual-wins-over-resolver', 2, $stTied, $tiedAC, 'AC', ['phase:AC:place:2' => [4, 6]], 'reverse');
$resolverVectors[] = $byPlace('tied-AC-place-2-empty-manual-falls-back-to-resolver', 2, $stTied, $tiedAC, 'AC', ['phase:AC:place:2' => []], 'reverse');
$resolverVectors[] = $byPlace('tied-AC-no-phase-key-no-resolver-call', 1, $stTied, $tiedAC, null, [], 'reverse');
$resolverVectors[] = $byPlace('group-needs-lot-for-place-throws', 2, (function () {
    $a = ['C' => standings_service(['phases' => []])->rank(players_of([3, 8, 9, 10]), [
        row(3, 8, 3, 2, 0, 12, 6), row(3, 9, 3, 2, 0, 12, 6), row(3, 10, 3, 2, 0, 12, 6),
        row(8, 9, 8, 2, 0, 12, 6), row(9, 10, 9, 2, 0, 12, 6), row(10, 8, 10, 2, 0, 12, 6),
    ])];

    return $a;
})(), [], 'AC');

$codesAll = function (string $name, array $standings, array $matches, string $sp, string $pp, int $seedCount, ?string $phaseKey, array $lotOrders = [], ?string $lotResolver = null) use ($resolverFor, $stripped): array {
    $calls = [];
    $r = $resolverFor();
    $r->setLotOrders($lotOrders);
    if ($lotResolver !== null) {
        $r->setLotResolver(function ($scope, array $tied) use (&$calls) {
            $calls[] = ['scope' => $scope, 'tied' => $tied];

            return array_reverse($tied);
        });
    }

    return [
        'op' => 'codesAllAdvance', 'name' => $name, 'standings' => $standings, 'matches' => $stripped($matches), 'seed_prefix' => $sp,
        'pool_prefix' => $pp, 'seed_count' => $seedCount, 'phase_key' => $phaseKey, 'lot_orders' => $lotOrders, 'lot_resolver' => $lotResolver,
        'result' => capture(fn () => $r->codesAllAdvance($standings, $stripped($matches), $sp, $pp, $seedCount, $phaseKey)), 'lot_resolver_calls' => $calls,
    ];
};
$resolverVectors[] = $codesAll('complete-AC-four-seeds', $stAC, $completeAC, 'S', 'AC', 4, 'AC');
$resolverVectors[] = $codesAll('tied-AC-no-lot-throws', $stTied, $tiedAC, 'S', 'AC', 4, 'AC');
$resolverVectors[] = $codesAll('tied-AC-with-manual-lot-orders', $stTied, $tiedAC, 'S', 'AC', 4, 'AC', ['phase:AC:place:1' => [2, 1], 'phase:AC:place:2' => [6, 4], 'phase:AC:place:3' => [7, 5]]);
$resolverVectors[] = $codesAll('tied-AC-with-lot-resolver', $stTied, $tiedAC, 'S', 'AC', 4, 'AC', [], 'reverse');
$runPhpTied = ['A' => [
    ['tpid' => 1, 'rank' => 1, 'grp' => 'A', 'points' => 4, 'wins' => 2, 'played' => 2, 'set_diff' => 2, 'game_diff' => 4, 'seed' => 1, 'needs_lot' => true],
    ['tpid' => 2, 'rank' => 2, 'points' => 2, 'wins' => 1, 'played' => 2, 'set_diff' => 0, 'game_diff' => 0, 'seed' => 2, 'needs_lot' => false],
]];
$resolverVectors[] = $codesAll('run-php-tie-throws', $runPhpTied, [], 'S', 'AC', 1, null);

$resolverVectors[] = (function () use ($resolverFor, $stripped, $stDG, $completeDG) {
    $r = $resolverFor();

    return ['op' => 'codesPromoted', 'name' => 'complete-DG-two-seconds', 'standings' => $stDG, 'matches' => $stripped($completeDG), 'prefix' => 'Q', 'seconds_count' => 2, 'phase_key' => 'DG',
        'result' => capture(fn () => $r->codesPromoted($stDG, $stripped($completeDG), 'Q', 2, 'DG'))];
})();
$resolverVectors[] = (function () use ($resolverFor, $stripped, $stTied, $tiedAC) {
    $r = $resolverFor();

    return ['op' => 'codesPromoted', 'name' => 'tied-AC-seconds-throw', 'standings' => $stTied, 'matches' => $stripped($tiedAC), 'prefix' => 'Q', 'seconds_count' => 1, 'phase_key' => 'AC',
        'result' => capture(fn () => $r->codesPromoted($stTied, $stripped($tiedAC), 'Q', 1, 'AC'))];
})();

$promotedDG = array_map(fn ($e) => $e['tpid'], array_values($resolverFor()->codesPromoted($stDG, $stripped($completeDG), 'Q', 2, 'DG')));
$resolverVectors[] = (function () use ($resolverFor, $stripped, $stDG, $completeDG, $promotedDG) {
    $r = $resolverFor();

    return ['op' => 'remainder', 'name' => 'complete-DG', 'standings' => $stDG, 'matches' => $stripped($completeDG), 'promoted' => $promotedDG, 'prefix' => 'REST', 'phase_key' => 'DG',
        'result' => capture(fn () => $r->remainder($stDG, $stripped($completeDG), $promotedDG, 'REST', 'DG'))];
})();
$resolverVectors[] = (function () use ($resolverFor, $stripped, $stDG, $completeDG, $promotedDG) {
    $r = $resolverFor();

    return ['op' => 'codesRemainderSeeded', 'name' => 'complete-DG-two-seeds', 'standings' => $stDG, 'matches' => $stripped($completeDG), 'promoted' => $promotedDG, 'prefix' => 'REST', 'seed_count' => 2, 'phase_key' => 'DG',
        'result' => capture(fn () => $r->codesRemainderSeeded($stDG, $stripped($completeDG), $promotedDG, 'REST', 2, 'DG'))];
})();

// resolveLines + firstRoundPlayers on the KO_A / KO_B lines of the reference format and the bye format
$lineRows = function (FormatService $f, string $phase, array $placeholders = [], array $tpids = []): array {
    $rows = [];
    $generated = (new GeneratorService)->bracketLines($f, $phase);
    foreach ($generated as $l) {
        $rows[$l['line']] = [
            'tlid' => $l['line'], 'tid' => 1, 'phase' => $phase, 'line' => $l['line'], 'kind' => $l['kind'],
            'seed_no' => $l['seed_no'], 'pool' => $l['pool'], 'placeholder' => $placeholders[$l['line']] ?? null, 'tpid' => $tpids[$l['line']] ?? null,
        ];
    }

    return $rows;
};
$koAcodes = array_merge(
    $resolverFor()->codesAllAdvance($stAC, $stripped($completeAC), 'S', 'AC', 4, 'AC'),
    $resolverFor()->codesPromoted($stDG, $stripped($completeDG), 'Q', 2, 'DG'),
);
$koAplaceholders = [3 => 'Q1', 5 => 'Q2', 7 => 'Q3', 10 => 'Q4', 11 => 'Q5', 13 => 'Q6', 2 => 'AC5', 4 => 'AC6', 6 => 'AC7', 12 => 'AC8', 14 => 'AC9', 15 => 'AC10'];
$koArows = $lineRows($refF, 'KO_A', $koAplaceholders);
$resolvedA = $resolverFor()->resolveLines($koArows, $koAcodes);
$resolverVectors[] = ['op' => 'resolveLines', 'name' => 'KO_A-all-placeholders-drawn', 'lines' => $koArows, 'codes' => $koAcodes, 'seed_prefix' => 'S', 'result' => $resolvedA];
$resolverVectors[] = ['op' => 'resolveLines', 'name' => 'KO_A-no-placeholders-only-seeds', 'lines' => $lineRows($refF, 'KO_A'), 'codes' => $koAcodes, 'seed_prefix' => 'S',
    'result' => $resolverFor()->resolveLines($lineRows($refF, 'KO_A'), $koAcodes)];
$restCodes = $resolverFor()->codesRemainderSeeded($stDG, $stripped($completeDG), $promotedDG, 'REST', 2, 'DG');
$koBrows = $lineRows($refF, 'KO_B', [2 => 'REST3', 3 => 'REST4', 4 => 'REST5', 5 => 'REST6', 6 => 'REST7', 7 => 'REST8']);
$resolverVectors[] = ['op' => 'resolveLines', 'name' => 'KO_B-rest-codes', 'lines' => $koBrows, 'codes' => $restCodes, 'seed_prefix' => 'S',
    'result' => $resolverFor()->resolveLines($koBrows, $restCodes)];
$resolverVectors[] = ['op' => 'resolveLines', 'name' => 'custom-seed-prefix-and-unknown-placeholder', 'lines' => $koBrows, 'codes' => ['X1' => ['tpid' => 99], 'X2' => ['tpid' => 98], 'REST9' => ['tpid' => 97]], 'seed_prefix' => 'X',
    'result' => $resolverFor()->resolveLines($koBrows, ['X1' => ['tpid' => 99], 'X2' => ['tpid' => 98], 'REST9' => ['tpid' => 97]], 'X')];

$byeRows = $lineRows($byeF, 'KO', [3 => 'W1', 6 => 'W2', 4 => 'R1', 7 => 'R2']);
$byeCodes = ['S1' => ['tpid' => 1], 'S2' => ['tpid' => 2], 'S3' => ['tpid' => 3], 'W1' => ['tpid' => 4], 'W2' => ['tpid' => 5], 'R1' => ['tpid' => 6]];
$byeResolved = $resolverFor()->resolveLines($byeRows, $byeCodes);
$resolverVectors[] = ['op' => 'resolveLines', 'name' => 'bye-bracket-skips-bye-line', 'lines' => $byeRows, 'codes' => $byeCodes, 'seed_prefix' => 'S', 'result' => $byeResolved];
$resolverVectors[] = ['op' => 'firstRoundPlayers', 'name' => 'bye-bracket-sole-player-advances', 'lines' => $byeRows, 'resolved' => $byeResolved, 'size' => 8,
    'result' => $resolverFor()->firstRoundPlayers($byeRows, $byeResolved, 8)];
$resolverVectors[] = ['op' => 'firstRoundPlayers', 'name' => 'KO_A-fully-resolved', 'lines' => $koArows, 'resolved' => $resolvedA, 'size' => 16,
    'result' => $resolverFor()->firstRoundPlayers($koArows, $resolvedA, 16)];
$resolverVectors[] = ['op' => 'firstRoundPlayers', 'name' => 'KO_A-partly-resolved', 'lines' => $koArows, 'resolved' => array_slice($resolvedA, 0, 5, true), 'size' => 16,
    'result' => $resolverFor()->firstRoundPlayers($koArows, array_slice($resolvedA, 0, 5, true), 16)];
$vectors['resolver'] = $resolverVectors;

// ------------------------------------------------------------------ lot[]

$seedFor = fn (int $did) => hash('sha256', 'demo-lot-seed-'.$did);
$lotCases = [];
$lotCase = function (string $name, ?string $lotOrdersJson, array $draws, string $scope, array $rows) use (&$lotCases, $reference, $seedFor): void {
    $tournaments = new StubTournamentTable;
    $tournaments->rows = [(object) [
        'tid' => 1, 'title' => 'Demo-Meisterschaft', 'season' => 2027, 'is_test' => 0, 'active' => 0,
        'format' => json_encode($reference), 'lot_orders' => $lotOrdersJson,
    ]];
    $drawRows = new StubDrawTable;
    $seeds = [];
    foreach ($draws as $d) {
        $seed = $d['final_seed'] ? $seedFor($d['did']) : null;
        $drawRows->rows[] = (object) ['did' => $d['did'], 'tid' => 1, 'final_seed' => $seed, 'discarded' => $d['discarded'] ? '2027-03-01 10:00:00' : null];
        if ($seed !== null && ! $d['discarded']) {
            $seeds[$d['did']] = $seed;
        }
    }
    $manager = new TournamentManager($tournaments, new StubPlayerTable, new StubMatchTable, new StandingsService, $drawRows, new DrawService);
    $order = $manager->lotOrder(1, $scope, $rows);
    $expectedSeed = $seeds ? $seeds[min(array_keys($seeds))] : null;

    $lotCases[] = [
        'name' => $name, 'lot_orders_json' => $lotOrdersJson,
        'draws' => array_map(fn ($d) => ['did' => $d['did'], 'final_seed' => $d['final_seed'] ? $seedFor($d['did']) : null, 'discarded' => $d['discarded']], $draws),
        'scope' => $scope, 'rows' => $rows,
        'expected_order' => $order, 'expected_seed' => $expectedSeed,
        'expected_hmac' => $expectedSeed !== null ? hash_hmac('sha256', $scope, $expectedSeed) : null,
    ];
};
$tied3 = [['tpid' => 2, 'needs_lot' => true], ['tpid' => 3, 'needs_lot' => true], ['tpid' => 5, 'needs_lot' => true], ['tpid' => 6, 'needs_lot' => false]];
$exec = fn (int $did, bool $discarded = false) => ['did' => $did, 'final_seed' => true, 'discarded' => $discarded];
$committed = fn (int $did) => ['did' => $did, 'final_seed' => false, 'discarded' => false];
$lotCase('first-executed', null, [$exec(5), $exec(3)], 'group:A', $tied3);
$lotCase('first-discarded', null, [$exec(3, true), $committed(4), $exec(5)], 'group:A', $tied3);
$lotCase('all-discarded', null, [$exec(3, true), $exec(5, true)], 'group:A', $tied3);
$lotCase('no-draw', null, [], 'group:A', $tied3);
$lotCase('only-committed', null, [$committed(3), $committed(4)], 'group:A', $tied3);
$lotCase('single-tied', null, [$exec(3)], 'group:A', [['tpid' => 4, 'needs_lot' => true], ['tpid' => 5, 'needs_lot' => false]]);
$lotCase('unsorted-tied', null, [$exec(3)], 'group:A', [['tpid' => 12, 'needs_lot' => true], ['tpid' => 3, 'needs_lot' => true], ['tpid' => 7, 'needs_lot' => true]]);
$lotCase('manual-wins', json_encode(['group:A' => [3, 2, 5]]), [$exec(3)], 'group:A', $tied3);
$lotCase('manual-empty-list-falls-back', '{"group:A":[]}', [$exec(3)], 'group:A', $tied3);
$lotCase('scope-group', null, [$exec(3)], 'group:A', $tied3);
$lotCase('scope-place-1', null, [$exec(3)], 'phase:AC:place:1', $tied3);
$lotCase('scope-place-2', null, [$exec(3)], 'phase:DG:place:2', $tied3);
$lotCase('garbage-lot-orders', 'not json', [$exec(3)], 'group:A', $tied3);
$lotCase('manual-for-other-scope-is-ignored', json_encode(['group:B' => [3, 2, 5]]), [$exec(3)], 'group:A', $tied3);
$lotCase('non-list-manual-entry-is-ignored', json_encode(['group:A' => 'x']), [$exec(3)], 'group:A', $tied3);
$vectors['lot'] = $lotCases;

// ------------------------------------------------------------------ manager[]

$managerScenario = function (string $name, bool $withDraw) use ($reference, $refF, $groupsAC, $groupsDG, $groupMatchRows, $autoMatch, $played, $seedFor): array {
    $players = [];
    $groupOf = [];
    foreach (array_merge($groupsAC, $groupsDG) as $grp => $tpids) {
        foreach ($tpids as $i => $t) {
            $groupOf[$t] = [$grp, $i + 1];
        }
    }
    for ($i = 1; $i <= 24; $i++) {
        $drawn = isset($groupOf[$i]) && ($i <= 10 || $i >= 11 && $i <= 14);
        $players[] = (object) [
            'tpid' => $i, 'tid' => 1, 'uid' => null, 'label' => sprintf('Demo Spieler %02d', $i), 'gender' => null, 'itn' => null,
            'itn_rated' => '10.0', 'seed' => $i, 'grp' => $drawn ? $groupOf[$i][0] : null, 'grp_pos' => $drawn ? $groupOf[$i][1] : null,
            'points_adjust' => 0, 'phone' => null, 'partner_tpid' => null, 'note' => null,
        ];
    }

    $matches = $groupMatchRows($refF, $groupsAC, function ($a, $b, $grp, $index) use ($played, $autoMatch) {
        if ($grp === 'A') {
            return $played($autoMatch($a, $b));
        }
        if ($grp === 'B') {
            // half played: first match played, second scheduled, third open
            return match ($index) {
                0 => $played($autoMatch($a, $b)),
                1 => ['played' => '2027-05-20'],
                default => [],
            };
        }
        // C: complete, players 8, 9, 10 beat each other in a circle with identical lines; 3 beats everyone
        if ($a === 3 || $b === 3) {
            return $played($a === 3 ? row($a, $b, 3, 2, 0, 12, 6) : row($a, $b, 3, 0, 2, 6, 12));
        }
        $winner = ['8-9' => 8, '9-10' => 9, '8-10' => 10][min($a, $b).'-'.max($a, $b)] ?? null;

        return $played($winner === $a ? row($a, $b, $a, 2, 0, 12, 6) : row($a, $b, $b, 0, 2, 6, 12));
    });

    $tournaments = new StubTournamentTable;
    $tournaments->rows = [(object) [
        'tid' => 1, 'title' => 'Demo-Meisterschaft', 'season' => 2027, 'is_test' => 0, 'cloned_from' => null, 'status' => 'setup', 'active' => 1,
        'format' => json_encode($reference), 'lot_orders' => null, 'itn_default' => '10.0', 'points_per_win' => 2,
        'tiebreak_scope' => 'tied', 'runnerup_mode' => 'drop_last', 'created' => '2027-01-15 10:00:00',
    ]];
    $playerTable = new StubPlayerTable;
    $playerTable->rows = $players;
    $matchTable = new StubMatchTable;
    $matchTable->rows = array_map(fn ($m) => (object) $m, $matches);
    $drawTable = new StubDrawTable;
    $drawRows = $withDraw ? [['did' => 2, 'tid' => 1, 'final_seed' => $seedFor(2), 'discarded' => null]] : [];
    $drawTable->rows = array_map(fn ($d) => (object) $d, $drawRows);

    $mgr = new TournamentManager($tournaments, $playerTable, $matchTable, new StandingsService, $drawTable, new DrawService);

    $tables = [];
    $complete = [];
    foreach (array_merge($groupsAC, $groupsDG) as $grp => $_) {
        $tables[$grp] = $mgr->table(1, $grp);
        $complete[$grp] = $mgr->groupComplete(1, $grp);
    }
    $states = [];
    foreach ($matches as $m) {
        $states[] = ['tmid' => $m['tmid'], 'mkey' => $m['mkey'], 'state' => $mgr->matchState($m)];
    }

    return [
        'name' => $name,
        'tournament' => (array) $tournaments->rows[0],
        'players' => array_map(fn ($p) => (array) $p, $players),
        'matches' => $matches,
        'draws' => $drawRows,
        'expected' => [
            'tables' => $tables,
            'group_complete' => $complete,
            'phase_complete' => ['AC' => $mgr->phaseComplete(1, 'AC'), 'DG' => $mgr->phaseComplete(1, 'DG')],
            'promotion_AC' => capture(fn () => $mgr->promotion(1, 'AC')),
            'promotion_AC_one_second' => capture(fn () => $mgr->promotion(1, 'AC', 1)),
            'phase_standings_AC' => capture(fn () => $mgr->phaseStandings(1, 'AC')),
            'phase_matches_AC' => count($mgr->phaseMatches(1, 'AC')),
            'progress' => capture(fn () => $mgr->progress(1)),
            'match_states' => $states,
            'draw_pool_DG' => $mgr->drawPool(1, 'DG'),
            'draw_pool_AC' => $mgr->drawPool(1, 'AC'),
            'group_players_C' => $mgr->groupPlayers(1, 'C'),
            'player_matches_8' => array_map(fn ($m) => $m['tmid'], $mgr->playerMatches(1, 8)),
            'current_tid' => (int) $mgr->current()->tid,
        ],
    ];
};
$vectors['manager'] = [$managerScenario('with-executed-draw', true), $managerScenario('without-draw', false)];

// ------------------------------------------------------------------ output

$files = [];
foreach (SOURCE_FILES as $file) {
    $files[$file] = hash('sha256', str_replace("\r\n", "\n", (string) file_get_contents($old.'/'.$file)));
}

$result = ['meta' => [
    'old_repo_commit' => OLD_REPO_COMMIT,
    'php_version' => PHP_VERSION,
    'generated_at' => gmdate('c'),
    'files' => $files,
]] + $vectors;

$json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
file_put_contents($outDir.'/vectors.json', $json."\n");

fwrite(STDOUT, sprintf("wrote %s (%d bytes); sections: %s\n", $outDir.'/vectors.json', strlen($json), implode(', ', array_map(fn ($k) => $k.'='.count($vectors[$k]), array_keys($vectors)))));
