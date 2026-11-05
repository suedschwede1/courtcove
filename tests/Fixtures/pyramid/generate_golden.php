<?php

use Pyramid\Service\RuleService;
use Pyramid\Service\SeedService;

/*
 * Generates golden vectors from the framework-free Pyramid services of the
 * old application, so the port can be checked value for value.
 *
 * Usage: php tests/Fixtures/pyramid/generate_golden.php <old-repo-root> <out-dir>
 *
 * Plain PHP, no Laravel. Inputs are synthetic (fictional labels, no real data).
 */

if ($argc !== 3) {
    fwrite(STDERR, "usage: php generate_golden.php <old-repo-root> <out-dir>\n");
    exit(1);
}

$old = rtrim($argv[1], '/'.chr(92));
$out = rtrim($argv[2], '/'.chr(92));

$files = [
    'modulex/Pyramid/src/Pyramid/Service/RuleService.php',
    'modulex/Pyramid/src/Pyramid/Service/SeedService.php',
];

$status = shell_exec('git -C '.escapeshellarg($old).' status --porcelain -- modulex/Pyramid 2>&1');
if (trim((string) $status) !== '') { // shell_exec() returns null for empty output
    fwrite(STDERR, "old repo has uncommitted changes in modulex/Pyramid (or git failed):\n".$status);
    exit(1);
}

$commit = trim((string) shell_exec('git -C '.escapeshellarg($old).' rev-parse HEAD 2>&1'));

foreach ($files as $f) {
    require_once $old.'/'.$f;
}

// ZF2 never sets a locale, so strcoll() compares bytes.
setlocale(LC_ALL, 'C');

$rule = new RuleService;
$seeder = new SeedService;

/** Runs a callable and returns ['result' => ...] or ['error' => {class, message}]. */
$run = function (callable $fn): array {
    try {
        return ['result' => $fn()];
    } catch (Throwable $e) {
        return ['error' => ['class' => get_class($e), 'message' => $e->getMessage()]];
    }
};

$ladder = fn (int $n): array => $n === 0 ? [] : array_combine(range(101, 100 + $n), range(1, $n));

// rows
$rows = [];
foreach (range(1, 66) as $p) {
    $rows[] = ['position' => $p, 'row' => $rule->rowOf($p), 'col' => $rule->colOf($p)];
}
$firstLast = [];
foreach (range(1, 11) as $r) {
    $firstLast[] = ['row' => $r, 'firstOfRow' => $rule->firstOfRow($r), 'lastOfRow' => $rule->lastOfRow($r)];
}

// challengeable
$challengeable = [];
foreach (range(1, 28) as $p) {
    foreach ([null, ...range(1, 30)] as $count) {
        $challengeable[] = ['position' => $p, 'count' => $count] + $run(fn () => $rule->challengeable($p, $count));
    }
}

// shift_win
$shiftWin = [];
$winCase = function (string $name, array $positions, $w, $l) use (&$shiftWin, $run, $rule): void {
    $shiftWin[] = ['name' => $name, 'positions' => $positions, 'winnerPos' => $w, 'loserPos' => $l]
        + $run(fn () => $rule->shiftForWin($positions, $w, $l));
};
foreach (range(1, 8) as $n) {
    foreach (range(1, $n) as $w) {
        foreach (range(1, $n) as $l) {
            $winCase("n{$n}", $ladder($n), $w, $l);
        }
    }
}
$gapped = [201 => 1, 202 => 4, 203 => 5];
foreach ($gapped as $w) {
    foreach ($gapped as $l) {
        $winCase('gapped', $gapped, $w, $l);
    }
}
$winCase('string-positions', [1 => '1', 2 => '2', 3 => '3'], 3, 1);
$winCase('missing-loser', $ladder(3), 9, 1);
$winCase('missing-winner', $ladder(3), 1, 9);

// shift_entry
$shiftEntry = [];
foreach (range(0, 8) as $n) {
    foreach (range(1, $n + 1) as $t) {
        $shiftEntry[] = ['name' => "n{$n}", 'positions' => $ladder($n), 'loserPos' => $t]
            + $run(fn () => $rule->shiftForEntry($ladder($n), $t));
    }
}
foreach (range(1, 6) as $t) {
    $shiftEntry[] = ['name' => 'gapped', 'positions' => $gapped, 'loserPos' => $t]
        + $run(fn () => $rule->shiftForEntry($gapped, $t));
}

// pyramid_rows
$pyramidRows = [];
foreach (range(0, 16) as $n) {
    $players = [];
    foreach (range(1, $n) as $p) {
        if ($n > 0) {
            $players[] = ['position' => $p];
        }
    }
    $res = $rule->pyramidRows($players);
    $pyramidRows[] = ['n' => $n, 'result' => array_map('count', array_values($res))];
}

// seed
$seedCase = function (string $name, array $players, $default) use ($run, $seeder): array {
    return ['name' => $name, 'players' => $players, 'itnDefault' => $default]
        + $run(fn () => $seeder->seed($players, $default));
};
$mk = fn (array $labels, array $itns) => array_map(fn ($l, $i) => ['label' => $l, 'itn' => $i], $labels, $itns);
$seed = [
    $seedCase('five-players', $mk(['Zeta', 'Alpha', 'Mu', 'Beta', 'Omega'], [9.5, null, 7.1, 5.7, null]), 10.0),
    $seedCase('null-itn-byte-order', $mk(['Öl', 'Zebra', 'Äpfel', 'Apfel'], [null, null, null, null]), 10.0),
    $seedCase('equal-itn-float-vs-string', $mk(['b', 'B', 'a'], [5.7, '5.7', 5.7]), 10.0),
    $seedCase('itn-strings', $mk(['p1', 'p2', 'p3', 'p4'], ['7.1', '7,1', '', '0']), 10.0),
    $seedCase('default-as-string', $mk(['Zeta', 'Alpha', 'Mu', 'Beta', 'Omega'], [9.5, null, 7.1, 5.7, null]), '10.0'),
    $seedCase('empty', [], 10.0),
];

$golden = [
    'meta' => [
        'old_repo_commit' => $commit,
        'php_version' => PHP_VERSION,
        'generated_at' => gmdate('c'),
        'files' => $files,
    ],
    'rows' => [
        'positions' => $rows,
        'rows' => $firstLast,
        'errors' => [
            ['call' => 'rowOf', 'arg' => 0] + $run(fn () => $rule->rowOf(0)),
            ['call' => 'rowOf', 'arg' => -3] + $run(fn () => $rule->rowOf(-3)),
            ['call' => 'firstOfRow', 'arg' => 0] + $run(fn () => $rule->firstOfRow(0)),
        ],
    ],
    'challengeable' => $challengeable,
    'shift_win' => $shiftWin,
    'shift_entry' => $shiftEntry,
    'pyramid_rows' => $pyramidRows,
    'seed' => $seed,
];

$json = json_encode($golden, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
file_put_contents($out.'/golden.json', $json."\n");
echo "wrote {$out}/golden.json (old commit {$commit})\n";
