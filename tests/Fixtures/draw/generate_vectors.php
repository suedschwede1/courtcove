<?php

use Draw\Service\DrawService;
use Draw\Service\TokenService;
use Tournament\Service\ScoreService;

/*
 * Generates golden vectors from the framework-free services of the old
 * application, so the port can be checked byte for byte.
 *
 * Usage: php tests/Fixtures/draw/generate_vectors.php <old-repo-root> <out-dir>
 *
 * Plain PHP, no Laravel. Inputs are synthetic (fictional names, no real data).
 */

if ($argc !== 3) {
    fwrite(STDERR, "usage: php generate_vectors.php <old-repo-root> <out-dir>\n");
    exit(1);
}

$old = rtrim($argv[1], '/\\');
$out = rtrim($argv[2], '/\\');

$files = [
    'modulex/Draw/src/Draw/Service/DrawService.php',
    'modulex/Draw/src/Draw/Service/TokenService.php',
    'modulex/Tournament/src/Tournament/Service/ScoreService.php',
];

$status = shell_exec('git -C '.escapeshellarg($old).' status --porcelain -- modulex/Draw modulex/Tournament/src/Tournament/Service/ScoreService.php 2>&1');
if (trim((string) $status) !== '') { // shell_exec() returns null for empty output
    fwrite(STDERR, "old repo has uncommitted changes in the sources (or git failed):\n".$status);
    exit(1);
}

foreach ($files as $f) {
    require_once $old.'/'.$f;
}

$draw = new DrawService;
$token = new TokenService;
$score = new ScoreService;

$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

/** Runs a callable that must throw and returns the exception class and message. */
$capture = function (callable $fn): array {
    try {
        $fn();
    } catch (Throwable $e) {
        return ['class' => get_class($e), 'message' => $e->getMessage()];
    }
    throw new LogicException('expected an exception');
};

$drawCase = function (string $name, array $payload, ?string $server = null, ?string $beacon = null) use ($draw, $flags): array {
    $server ??= hash('sha256', 'vector-server-'.$name);
    $beacon ??= hash('sha256', 'vector-beacon-'.$name);
    $res = $draw->execute($payload, $server, $beacon);

    return [
        'name' => $name,
        'payload' => $payload,
        'server_seed' => $server,
        'beacon_value' => $beacon,
        'canonical' => $draw->canonical($payload),
        'commitment' => $draw->commit($payload, $server),
        'final_seed' => $res['final_seed'],
        'shuffled' => $res['shuffled'],
        'assignment' => $res['assignment'],
        'assignment_json' => json_encode($res['assignment'], $flags),
    ];
};

$rule = 'Gruppenkoepfe';
$slotsFor = fn (string $p, int $n) => array_map(fn ($i) => $p.$i, range(1, $n));
$n24slots = [];
foreach (range('A', 'F') as $l) {
    foreach (range(1, 4) as $p) {
        $n24slots[] = $l.$p;
    }
}

$tokenNames = $token->tokenNames(6, 'AC');
$tokenSlots = ['A2', 'B2', 'C2', 'A3', 'B3', 'C3'];
$tokenPayload = $token->payload($tokenNames, $tokenSlots, 'Tokenziehung AC');
$n3 = ['entries' => [101, 102, 103], 'slots' => ['A2', 'B2', 'C2'], 'rule' => $rule];

$drawCases = [
    $drawCase('runphp-strings', ['entries' => ['Q1', 'Q2', 'Q3'], 'slots' => ['KO_A:3', 'KO_A:5', 'KO_A:7'], 'rule' => 'test'], str_repeat('ab', 32), str_repeat('cd', 32)),
    $drawCase('n2-int', ['entries' => [101, 102], 'slots' => ['A2', 'B2'], 'rule' => $rule]),
    $drawCase('n3-int', $n3),
    $drawCase('n16-int', ['entries' => range(201, 216), 'slots' => $slotsFor('KO_A:', 16), 'rule' => $rule]),
    $drawCase('n24-int', ['entries' => range(301, 324), 'slots' => $n24slots, 'rule' => $rule]),
    $drawCase('mixed-int-string', ['entries' => ['Q1', 7, 'S2'], 'slots' => ['A2', 'B2', 'C2'], 'rule' => $rule]),
    $drawCase('numeric-strings', ['entries' => ['1', '2', '3', '4'], 'slots' => ['A2', 'B2', 'C2', 'D2'], 'rule' => $rule]),
    $drawCase('unicode-slash-rule', ['entries' => [101, 102, 103], 'slots' => ['A2', 'B2', 'C2'], 'rule' => 'Gruppen A/B – Setzliste für Damen & Herren "2027" ✓']),
    $drawCase('emoji-rule', ['entries' => [101, 102, 103], 'slots' => ['A2', 'B2', 'C2'], 'rule' => 'Losglück 😀']),
    $drawCase('line-separator-rule', ['entries' => [101, 102, 103], 'slots' => ['A2', 'B2', 'C2'], 'rule' => "a\u{2028}b"]),
    $drawCase('numeric-slots', ['entries' => [11, 12, 13], 'slots' => ['0', '1', '2'], 'rule' => $rule]),
    $drawCase('token-ac6', $tokenPayload),
    $drawCase('beacon-non-hex', $n3, null, 'drand-randomness-not-hex'),
];

$shuffleSeed = hash('sha256', 'vector-shuffle');
$shuffle = [];
foreach ([[], [5], [['tpid' => 3, 'uid' => 30], ['tpid' => 1, 'uid' => 10], ['tpid' => 2, 'uid' => 20]], range(1, 10)] as $items) {
    $shuffle[] = ['items' => $items, 'seed' => $shuffleSeed, 'result' => $draw->shuffle($items, $shuffleSeed)];
}

$goodSeed = str_repeat('ab', 32);
$goodBeacon = str_repeat('cd', 32);
$errors = [];
$addError = function (string $call, array $args, callable $fn) use (&$errors, $capture): void {
    $errors[] = ['call' => $call, 'args' => $args] + $capture($fn);
};
$execError = function (array $payload) use ($addError, $draw, $goodSeed, $goodBeacon): void {
    $addError(
        'execute',
        ['payload' => $payload, 'server_seed' => $goodSeed, 'beacon_value' => $goodBeacon],
        fn () => $draw->execute($payload, $goodSeed, $goodBeacon)
    );
};
$execError(['entries' => [1, 2], 'slots' => ['A', 'B', 'C'], 'rule' => 'r']);
$execError(['entries' => [1, 2, 3], 'slots' => ['A', 'B'], 'rule' => 'r']);
$execError(['entries' => [1, '1'], 'slots' => ['A', 'B'], 'rule' => 'r']);
$execError(['entries' => [1, 2], 'slots' => ['A', 'B']]);
$upper = str_repeat('AB', 32);
$commitPayload = ['entries' => [1, 2], 'slots' => ['A', 'B'], 'rule' => 'r'];
$addError('commit', ['payload' => $commitPayload, 'server_seed' => $upper], fn () => $draw->commit($commitPayload, $upper));
$addError('finalSeed', ['server_seed' => $goodSeed, 'beacon_value' => ''], fn () => $draw->finalSeed($goodSeed, ''));

$tokenNamesCases = [];
foreach ([[3, 'T'], [12, 'DG'], [0, 'T'], [100, 'X']] as [$count, $prefix]) {
    $tokenNamesCases[] = ['count' => $count, 'prefix' => $prefix, 'result' => $token->tokenNames($count, $prefix)];
}

$tokenPayloadCases = [[
    'token_names' => $tokenNames,
    'slots' => $tokenSlots,
    'rule' => 'Tokenziehung AC',
    'result' => $tokenPayload,
]];

$assignTokens = [['dtid' => 7, 'position' => 2], ['dtid' => 5, 'position' => 1], ['dtid' => 9, 'position' => 3]];
$assignSlots = [
    [
        'tokens' => $assignTokens,
        'slots' => ['C2', 'A2', 'B2'],
        'result' => $token->assignSlots($assignTokens, ['C2', 'A2', 'B2']),
    ],
    [
        'tokens' => $assignTokens,
        'slots' => ['C2', 'A2'],
        'error' => $capture(fn () => $token->assignSlots($assignTokens, ['C2', 'A2'])),
    ],
];

$fallback = [];
$fb = function (array $free, array $non, ?string $seed = null, array $partners = [], array $fixed = []) use (&$fallback, $token, $draw): void {
    $case = ['free_tokens' => $free, 'non_pickers' => $non, 'final_seed' => $seed, 'partner_map' => $partners, 'fixed_groups' => $fixed];
    try {
        $case['result'] = $token->fallbackAssignment($free, $non, $seed, $seed ? $draw : null, $partners, $fixed);
    } catch (Throwable $e) {
        $case['error'] = ['class' => get_class($e), 'message' => $e->getMessage()];
    }
    $fallback[] = $case;
};
$fb([['dtid' => 2, 'position' => 2], ['dtid' => 1, 'position' => 1]], [['tpid' => 9, 'uid' => 90], ['tpid' => 4, 'uid' => 40]]);
$fb([['dtid' => 1, 'position' => 1, 'group' => 'D'], ['dtid' => 2, 'position' => 2, 'group' => 'E']], [['tpid' => 1, 'uid' => 10], ['tpid' => 2, 'uid' => 20]], null, [1 => 9, 9 => 1], [9 => 'D']);
$fb([['dtid' => 3, 'position' => 1, 'group' => 'D']], [['tpid' => 1, 'uid' => 10]], null, [1 => 9, 9 => 1], [9 => 'D']);
$fb(
    [['dtid' => 11, 'position' => 1, 'group' => 'D'], ['dtid' => 12, 'position' => 2, 'group' => 'D'], ['dtid' => 13, 'position' => 3, 'group' => 'E']],
    [['tpid' => 1, 'uid' => 10], ['tpid' => 2, 'uid' => 20], ['tpid' => 3, 'uid' => 30]],
    null, [1 => 91, 2 => 92, 3 => 93], [91 => 'D', 92 => 'D', 93 => 'E']
);
$fb(
    [
        ['dtid' => 21, 'position' => 1, 'group' => 'D'], ['dtid' => 22, 'position' => 2, 'group' => 'D'],
        ['dtid' => 23, 'position' => 3, 'group' => 'E'], ['dtid' => 24, 'position' => 4, 'group' => 'E'],
        ['dtid' => 25, 'position' => 5, 'group' => 'F'], ['dtid' => 26, 'position' => 6, 'group' => 'F'],
    ],
    [['tpid' => 4, 'uid' => 40], ['tpid' => 2, 'uid' => 20], ['tpid' => 6, 'uid' => 60], ['tpid' => 1, 'uid' => 10]],
    hash('sha256', 'vector-fallback'), [2 => 4, 4 => 2, 6 => 8, 8 => 6], [8 => 'E']
);
$fb(
    [['dtid' => 1, 'position' => 1], ['dtid' => 2, 'position' => 2]],
    [['tpid' => 1, 'uid' => 10], ['tpid' => 2, 'uid' => 20], ['tpid' => 3, 'uid' => 30]]
);

$parseLines = [];
foreach (["a\n# c\n\n b # x\r\nc\r", ''] as $text) {
    $parseLines[] = ['text' => $text, 'result' => $draw->parseLines($text)];
}

$keys = ['s1a', 's1b', 's2a', 's2b', 'mtba', 'mtbb'];
$scoreInputs = [
    [6, 4, 3, 6, 10, 8], [6, 4, 6, 3, 10, 8], [6, 4, 3, 6, 10, 9], [6, 4, 3, 6, 12, 10], [6, 4, 3, 6, 13, 10],
    [6, 4, 3, 6, 9, 7], [6, 4, 3, 6, 10, 0], [6, 4, 3, 6, 11, 9], [6, 4, 3, 6, 10, 10],
    [6, 0, 6, 0], [7, 6, 7, 5], [4, 6, 3, 6], [6, 5, 6, 0], [8, 6, 6, 0], [6, 4, 3, 6],
    [6, 4, 3, 6, 10, ''], [6, 4, '', ''],
    ['6', '4', '6', '3'], [' 6 ', '4', '6', '3'], ['x', '4', '6', '3'], [6, 4, 3, 6, '-1', 10],
];
$scoreEvaluate = [];
foreach ($scoreInputs as $vals) {
    $input = array_combine(array_slice($keys, 0, count($vals)), $vals);
    $case = ['input' => $input];
    try {
        $case['result'] = $score->evaluate($input);
    } catch (Throwable $e) {
        $case['error'] = ['class' => get_class($e), 'message' => $e->getMessage()];
    }
    $scoreEvaluate[] = $case;
}

$describeInputs = [
    [[], false], [[], true],
    [['s1a' => 6, 's1b' => 4], false],
    [['s1a' => 6, 's1b' => 4, 's2a' => 6, 's2b' => 3], false],
    [['s1a' => 6, 's1b' => 4, 's2a' => 6, 's2b' => 3, 'mtba' => 10, 'mtbb' => 8], false],
    [['s1a' => 6, 's1b' => 4, 's2a' => 3, 's2b' => 6], false],
    [['s1a' => 6, 's1b' => 4, 's2a' => 3, 's2b' => 6, 'mtba' => 10, 'mtbb' => 9], false],
    [['s1a' => 6, 's1b' => 4, 's2a' => 3, 's2b' => 6, 'mtba' => 10, 'mtbb' => 8], false],
    [['s1a' => 6, 's1b' => 5, 's2a' => 6, 's2b' => 3], false],
];
$scoreDescribe = [];
foreach ($describeInputs as [$input, $hasSchedule]) {
    $scoreDescribe[] = ['input' => $input, 'has_schedule' => $hasSchedule, 'result' => $score->describe($input, $hasSchedule)];
}

$vectors = [
    'meta' => [
        'old_repo_commit' => trim((string) shell_exec('git -C '.escapeshellarg($old).' rev-parse HEAD')),
        'php_version' => PHP_VERSION,
        'generated_at' => date('c'),
        'files' => $files,
    ],
    'draw' => $drawCases,
    'shuffle' => $shuffle,
    'errors' => $errors,
    'token_names' => $tokenNamesCases,
    'token_payload' => $tokenPayloadCases,
    'assign_slots' => $assignSlots,
    'fallback' => $fallback,
    'parse_lines' => $parseLines,
    'score_evaluate' => $scoreEvaluate,
    'score_describe' => $scoreDescribe,
];

$write = function (string $path, array $data) use ($flags): void {
    $json = json_encode($data, JSON_PRETTY_PRINT | $flags | JSON_THROW_ON_ERROR);
    file_put_contents($path, $json."\n");
};

if (! is_dir($out.'/synthetic-records') && ! mkdir($out.'/synthetic-records', 0775, true)) {
    fwrite(STDERR, "cannot create output directory\n");
    exit(1);
}
foreach (glob($out.'/synthetic-records/*.json') as $stale) {
    unlink($stale);
}

$write($out.'/vectors.json', $vectors);

foreach ($drawCases as $c) {
    $write($out.'/synthetic-records/'.$c['name'].'.json', [
        'title' => $c['name'],
        'status' => 'executed',
        'payload' => $c['payload'],
        'commitment' => $c['commitment'],
        'beacon_source' => 'drand:round:1',
        'created' => '2026-01-01 00:00:00',
        'discarded' => null,
        'discarded_reason' => null,
        'server_seed' => $c['server_seed'],
        'beacon_value' => $c['beacon_value'],
        'final_seed' => $c['final_seed'],
        'assignment' => $c['assignment'],
        'executed' => '2026-01-01 00:00:00',
    ]);
}

echo 'wrote '.count($drawCases)." draw cases to $out\n";
