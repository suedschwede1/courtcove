<?php

declare(strict_types=1);

/**
 * Generates tests/Fixtures/mixed/golden.json from the framework-free services of the old ZF2 install.
 *
 * Usage: php tests/Fixtures/mixed/generate_golden.php <old-repo-root> <out-dir>
 *
 * Plain PHP, no Laravel. Reads the old repository only (never modifies it) and refuses to run unless the
 * module directory content equals the reference commit (line endings ignored, no untracked files).
 */

use MixedTournament\Service\GroupingService;
use MixedTournament\Service\HistoryService;
use MixedTournament\Service\MexicanoService;
use MixedTournament\Service\RotationService;
use MixedTournament\Service\RoundGenerationService;
use MixedTournament\Service\ScorecardTemplateService;
use MixedTournament\Service\StandingsService;
use MixedTournament\Service\WaveService;

const OLD_REPO_COMMIT = '6b780d3324936bab3749237183c2dab161873612';
const SERVICE_FILES = [
    'RotationService', 'HistoryService', 'GroupingService', 'RoundGenerationService',
    'MexicanoService', 'StandingsService', 'WaveService', 'ScorecardTemplateService',
];

if ($argc !== 3) {
    fwrite(STDERR, "usage: php generate_golden.php <old-repo-root> <out-dir>\n");
    exit(2);
}

$old = rtrim($argv[1], '/\\');
$outDir = rtrim($argv[2], '/\\');
$module = 'modulex/MixedTournament';

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
git($old, 'diff --ignore-cr-at-eol --quiet '.OLD_REPO_COMMIT.' -- '.$module, $code);
if ($code !== 0) {
    fwrite(STDERR, "$module differs from ".OLD_REPO_COMMIT." (ignoring CR at EOL)\n");
    exit(1);
}
if (git($old, 'ls-files --others --exclude-standard -- '.$module) !== '') {
    fwrite(STDERR, "$module contains untracked files\n");
    exit(1);
}

$serviceDir = $old.'/'.$module.'/src/MixedTournament/Service/';
foreach (SERVICE_FILES as $file) {
    require $serviceDir.$file.'.php';
}

// ---- deterministic callables (named in args, rebuilt by Tests\Support\Mixed\DeterministicCallables) ----

function shuffleBy(string $name): callable
{
    return match ($name) {
        'identity' => fn (array $items): array => $items,
        'reverse' => fn (array $items): array => array_reverse($items),
        'rotate1' => function (array $items): array {
            $items = array_values($items);
            if ($items) {
                $items[] = array_shift($items);
            }

            return $items;
        },
    };
}

/** @param array{sequence?: list<int>, constant?: string} $spec */
function randomIntBy(array $spec): callable
{
    if (($spec['constant'] ?? null) === 'min') {
        return fn (int $min, int $max): int => $min;
    }
    $seq = $spec['sequence'];
    $i = 0;

    return function (int $min, int $max) use ($seq, &$i): int {
        $value = $seq[$i % count($seq)];
        $i++;

        return $min + ($value % ($max - $min + 1));
    };
}

function kase(string $name, array $args, callable $fn): array
{
    try {
        return ['name' => $name, 'args' => $args, 'result' => $fn()];
    } catch (Throwable $e) {
        return ['name' => $name, 'args' => $args, 'error' => ['class' => get_class($e), 'message' => $e->getMessage()]];
    }
}

function emptyMap(array $a): array|stdClass
{
    return $a === [] ? new stdClass : $a;
}

function hist(array $partners = [], array $opponents = []): array
{
    return ['partners' => emptyMap($partners), 'opponents' => emptyMap($opponents)];
}

function fromHist(array $h): array
{
    return ['partners' => (array) $h['partners'], 'opponents' => (array) $h['opponents']];
}

$rotation = new RotationService;
$history = new HistoryService;
$grouping = new GroupingService($rotation, $history);
$roundGen = new RoundGenerationService($grouping, $rotation);
$mexicano = new MexicanoService;
$standingsService = new StandingsService;
$waves = new WaveService;
$scorecard = new ScorecardTemplateService($rotation, $mexicano);

$shuffles = ['identity', 'reverse', 'rotate1'];
$g = [];

// ---- rotation ----
$g['rotation'] = [];
foreach ([4, 5, 3, 6] as $n) {
    $g['rotation'][] = kase("rotation_$n", ['group_size' => $n], fn () => $rotation->matches($n));
}

// ---- group_sizes ----
$g['group_sizes'] = [];
foreach (['4_only', '4_and_5'] as $mode) {
    foreach (range(0, 30) as $n) {
        $g['group_sizes'][] = kase("group_sizes_{$n}_$mode", ['count' => $n, 'mode' => $mode], fn () => $grouping->groupSizes($n, $mode));
    }
}

// ---- participant sets ----
$p8 = [];
foreach ([1 => ['f', 5.0], 2 => ['m', 4.0], 3 => ['f', 3.0], 4 => ['m', 2.0], 5 => ['f', 6.0], 6 => ['m', 1.0], 7 => ['f', 7.0], 8 => ['m', 8.0]] as $id => [$gender, $itn]) {
    $p8[] = ['id' => $id, 'gender' => $gender, 'itn' => $itn];
}
$p8age = [];
foreach ([1 => ['f', 30], 2 => ['m', 45], 3 => ['f', 22], 4 => ['m', 60], 5 => ['f', 25], 6 => ['m', null], 7 => ['f', 33], 8 => ['m', 19]] as $id => [$gender, $age]) {
    $p8age[] = ['id' => $id, 'gender' => $gender, 'itn' => null, 'age' => $age];
}
$itn13 = [5.0, null, 3.5, 7.1, null, 2.0, 6.2, 4.4, null, 8.0, 1.5, 5.0, 3.3];
$age13 = [30, null, 22, 61, 45, 19, null, 33, 27, 50, 70, null, 41];
$p13 = [];
foreach (range(1, 13) as $id) {
    $p13[] = ['id' => $id, 'gender' => $id % 2 === 1 ? 'f' : 'm', 'itn' => $itn13[$id - 1], 'age' => $age13[$id - 1]];
}
$p18 = [];
foreach (range(1, 18) as $id) {
    $p18[] = ['id' => $id, 'gender' => $id % 2 === 1 ? 'f' : 'm', 'itn' => $id % 7, 'age' => in_array($id, [4, 11], true) ? null : 20 + 3 * $id];
}
$sets = ['p8' => $p8, 'p8age' => $p8age, 'p13' => $p13, 'p18' => $p18];

// ---- initial_assignment ----
$g['initial_assignment'] = [];
foreach ($sets as $setName => $participants) {
    $seen = [];
    foreach (['4_only', '4_and_5'] as $sizeMode) {
        $sizing = $grouping->groupSizes(count($participants), $sizeMode);
        $sizes = $sizing['sizes'];
        if (in_array($sizes, $seen, true)) {
            continue;
        }
        $seen[] = $sizes;
        // Like RoundGenerationService: leftover players are not part of the regular groups.
        $used = array_slice($participants, 0, array_sum($sizes));
        foreach (['random', 'itn', 'itn_balanced', 'age', 'ladies_apart'] as $mode) {
            foreach ($shuffles as $sh) {
                $args = ['set' => $setName, 'size_mode' => $sizeMode, 'participants' => $used, 'sizes' => $sizes, 'mode' => $mode, 'shuffle' => $sh];
                $g['initial_assignment'][] = kase("initial_{$setName}_{$sizeMode}_{$mode}_$sh", $args, fn () => $grouping->initialAssignment($used, $sizes, $mode, shuffleBy($sh)));
            }
        }
    }
}

// ---- form_extra_group ----
$g['form_extra_group'] = [];
$extraCases = [
    'two_groups_one_leftover_error' => [[[1, 2, 3, 4], [5, 6, 7, 8]], [9], []],
    'one_group_three_leftover' => [[[1, 2, 3, 4]], [9, 10, 11], []],
    'three_groups_one_leftover' => [[[1, 2, 3, 4], [5, 6, 7, 8], [9, 10, 11, 12]], [13], []],
    'two_groups_two_leftover' => [[[1, 2, 3, 4], [5, 6, 7, 8]], [9, 10], []],
    'excluded_first_members' => [[[1, 2, 3, 4], [5, 6, 7, 8], [9, 10, 11, 12]], [13], [1, 5]],
    'excluded_whole_first_group_falls_through' => [[[1, 2, 3, 4], [5, 6, 7, 8], [9, 10, 11, 12], [13, 14, 15, 16]], [17], [1, 2, 3, 4]],
    'excluded_whole_first_group_too_few_groups_error' => [[[1, 2, 3, 4], [5, 6, 7, 8], [9, 10, 11, 12]], [13], [1, 2, 3, 4]],
    'excluded_everything_error' => [[[1, 2, 3, 4], [5, 6, 7, 8], [9, 10, 11, 12]], [13], [1, 2, 3, 4, 5, 6, 7, 8]],
];
foreach ($extraCases as $name => [$groups, $leftover, $excluded]) {
    $g['form_extra_group'][] = kase("extra_$name", ['groups' => $groups, 'leftover_ids' => $leftover, 'excluded_filler_ids' => $excluded], fn () => $grouping->formExtraGroup($groups, $leftover, $excluded));
}

// ---- penalty ----
$g['penalty'] = [];
$repeatHistory = hist(['1:2' => 1]);
$penaltyCases = [
    'repeat_pairing' => [[[1, 2, 3, 4]], $repeatHistory, []],
    'fresh_pairing' => [[[1, 3, 5, 6], [2, 4, 7, 8]], $repeatHistory, []],
    'avoided_pair' => [[[1, 2, 3, 4]], hist(), [['a' => 1, 'b' => 2]]],
];
$matrixHistory = hist(['1:2' => 2, '3:4' => 1], ['1:3' => 3]);
foreach ([[[1, 2, 3, 4], [5, 6, 7, 8]], [[1, 3, 5, 7], [2, 4, 6, 8]], [[1, 2, 3, 4, 5], [6, 7, 8, 9]]] as $i => $groups) {
    $penaltyCases["matrix_grouping_$i"] = [$groups, $matrixHistory, [['a' => 1, 'b' => 3]]];
}
foreach ($penaltyCases as $name => [$groups, $h, $avoid]) {
    $g['penalty'][] = kase("penalty_$name", ['groups' => $groups, 'history' => $h, 'avoid_pairs' => $avoid], fn () => $grouping->penalty($groups, fromHist($h), $avoid));
}

// ---- repair ----
$g['repair'] = [];
$repairHistories = [
    'empty' => hist(),
    'busy' => hist(['1:2' => 2, '5:6' => 1, '9:10' => 1], ['1:5' => 2]),
];
$repairAvoids = ['none' => [], 'pair12' => [['a' => 1, 'b' => 2]]];
$repairGroupings = [
    'fours' => [[1, 2, 3, 4], [5, 6, 7, 8], [9, 10, 11, 12]],
    'fives' => [[1, 2, 3, 4, 5], [6, 7, 8, 9], [10, 11, 12, 13]],
];
foreach ($repairGroupings as $gn => $groups) {
    foreach ($repairHistories as $hn => $h) {
        foreach ($repairAvoids as $an => $avoid) {
            foreach ([[0, 1, 0, 1, 1, 0], [2, 0, 1, 3, 0, 2, 1], [1, 1, 2, 0, 3, 1, 0, 2]] as $si => $seq) {
                foreach ([10, 200] as $max) {
                    $args = ['groups' => $groups, 'history' => $h, 'avoid_pairs' => $avoid, 'random_int' => ['sequence' => $seq], 'max_iterations' => $max];
                    $g['repair'][] = kase("repair_{$gn}_{$hn}_{$an}_seq{$si}_$max", $args, fn () => $grouping->repair($groups, fromHist($h), $avoid, randomIntBy(['sequence' => $seq]), $max));
                }
            }
        }
    }
}

// ---- propose ----
$g['propose'] = [];
$thirteen = [];
foreach (range(1, 13) as $id) {
    $thirteen[] = ['id' => $id, 'gender' => $id % 2 === 0 ? 'm' : 'f', 'itn' => 5.0];
}
$seven = [];
foreach (range(1, 7) as $id) {
    $seven[] = ['id' => $id, 'gender' => $id % 2 === 0 ? 'm' : 'f', 'itn' => null];
}
$nine = [];
foreach (range(1, 9) as $id) {
    $nine[] = ['id' => $id, 'gender' => $id % 2 === 0 ? 'm' : 'f', 'itn' => null];
}
$proposeSpecs = [
    'a_13_4only_random' => [$thirteen, '4_only', 'random', hist(), [], 'identity', ['constant' => 'min'], []],
    'b_p13_4and5_itn' => [$p13, '4_and_5', 'itn', hist(), [], 'reverse', ['sequence' => [1, 2, 0]], []],
];
$toHistoryMatches = fn (array $matches): array => array_map(fn ($m) => [
    'team_a_p1' => $m['team_a'][0], 'team_a_p2' => $m['team_a'][1],
    'team_b_p1' => $m['team_b'][0], 'team_b_p2' => $m['team_b'][1],
], $matches);
$bResult = $roundGen->propose($p13, '4_and_5', 'itn', fromHist(hist()), [], shuffleBy('reverse'), randomIntBy(['sequence' => [1, 2, 0]]));
$bHistory = $history->build($toHistoryMatches($bResult['matches']));
$proposeSpecs['c_p13_4only_itn_balanced_history'] = [$p13, '4_only', 'itn_balanced', hist($bHistory['partners'], $bHistory['opponents']), [['a' => 2, 'b' => 7]], 'rotate1', ['sequence' => [2, 0, 1, 3]], []];
$proposeSpecs['d_p18_4only_age_excluded'] = [$p18, '4_only', 'age', hist(), [], 'identity', ['sequence' => [0, 1, 0, 1, 1, 0]], [1, 5]];
$proposeSpecs['e_7_4and5_ladies_apart'] = [$seven, '4_and_5', 'ladies_apart', hist(), [], 'identity', ['sequence' => [0, 1, 0, 1, 1, 0]], []];
$proposeSpecs['f_9_4only_error'] = [$nine, '4_only', 'random', hist(), [], 'identity', ['sequence' => [0]], []];
foreach ($proposeSpecs as $name => [$participants, $sizeMode, $dist, $h, $avoid, $sh, $ri, $excluded]) {
    $args = ['participants' => $participants, 'group_size_mode' => $sizeMode, 'distribution' => $dist, 'history' => $h, 'avoid_pairs' => $avoid, 'shuffle' => $sh, 'random_int' => $ri, 'excluded_filler_ids' => $excluded];
    $g['propose'][] = kase("propose_$name", $args, fn () => $roundGen->propose($participants, $sizeMode, $dist, fromHist($h), $avoid, shuffleBy($sh), randomIntBy($ri), $excluded));
}
$proposeA = $roundGen->propose($thirteen, '4_only', 'random', fromHist(hist()), [], shuffleBy('identity'), randomIntBy(['constant' => 'min']));

// ---- history ----
$g['history'] = [];
$buildCase = [
    ['team_a_p1' => 1, 'team_a_p2' => 2, 'team_b_p1' => 3, 'team_b_p2' => 4],
    ['team_a_p1' => 1, 'team_a_p2' => 3, 'team_b_p1' => 2, 'team_b_p2' => 4],
];
$g['history'][] = kase('history_build_two_matches', ['matches' => $buildCase], fn () => $history->build($buildCase));
$proposeAMatches = $toHistoryMatches($proposeA['matches']);
$g['history'][] = kase('history_build_propose_a', ['matches' => $proposeAMatches], fn () => $history->build($proposeAMatches));
$g['history'][] = kase('history_build_empty', ['matches' => []], fn () => $history->build([]));
$g['history'][] = kase('history_key_reversed', ['a' => 7, 'b' => 3], fn () => $history->key(7, 3));

// ---- mixing_score ----
$g['mixing_score'] = [];
$allPairs = [];
$ids5 = range(1, 5);
for ($i = 0; $i < 5; $i++) {
    for ($j = $i + 1; $j < 5; $j++) {
        $allPairs[] = $history->key($ids5[$i], $ids5[$j]);
    }
}
$twice = array_fill_keys($allPairs, 2);
$oneRepeated = array_fill_keys($allPairs, 1);
$oneRepeated[$allPairs[0]] = 2;
$threeRepeated = array_fill_keys($allPairs, 1);
foreach (array_slice($allPairs, 0, 3) as $k) {
    $threeRepeated[$k] = 2;
}
$aCounts = $history->build($proposeAMatches)['partners'];
$mixCases = [
    'propose_a_13' => [$aCounts, range(1, 13)],
    'empty_counts' => [[], range(1, 5)],
    'all_pairs_twice_poor' => [$twice, $ids5],
    'one_of_ten_repeated_good' => [$oneRepeated, $ids5],
    'three_of_ten_repeated_ok' => [$threeRepeated, $ids5],
    'single_participant' => [[], [1]],
];
foreach ($mixCases as $name => [$counts, $ids]) {
    $g['mixing_score'][] = kase("mixing_$name", ['partner_counts' => emptyMap($counts), 'participant_ids' => $ids], fn () => $history->mixingScore($counts, $ids));
}

// ---- standings ----
$g['standings'] = [];
$standingsCases = [
    'filler_free_three_rounds' => [[1, 2, 3, 4, 5, 6, 7], [
        ['round' => 1, 'team_a' => [1, 2], 'team_b' => [6, 7], 'games_a' => 3, 'games_b' => 0],
        ['round' => 2, 'team_a' => [1, 3], 'team_b' => [6, 7], 'games_a' => 2, 'games_b' => 2],
        ['round' => 3, 'team_a' => [1, 4], 'team_b' => [6, 7], 'games_a' => 0, 'games_b' => 1],
        ['round' => 4, 'team_a' => [5, 1], 'team_b' => [6, 7], 'games_a' => null, 'games_b' => null],
    ]],
    'filler_plays_twice' => [[1, 2, 3, 4, 5, 6, 7], [
        ['round' => 1, 'team_a' => [1, 2], 'team_b' => [3, 4], 'games_a' => 3, 'games_b' => 1],
        ['round' => 1, 'team_a' => [5, 6], 'team_b' => [7, 1], 'games_a' => 2, 'games_b' => 2],
        ['round' => 2, 'team_a' => [1, 2], 'team_b' => [3, 4], 'games_a' => 1, 'games_b' => 3],
        ['round' => 2, 'team_a' => [5, 6], 'team_b' => [7, 1], 'games_a' => 4, 'games_b' => 0],
    ]],
];
$s12Matches = [];
for ($r = 1; $r <= 4; $r++) {
    for ($grp = 0; $grp <= 2; $grp++) {
        $teamA = [1 + ($grp * 4 + $r) % 12, 1 + ($grp * 4 + $r + 5) % 12];
        $teamB = [1 + ($grp * 4 + $r + 2) % 12, 1 + ($grp * 4 + $r + 9) % 12];
        // Participant 12 plays in no round 4 match.
        if ($r === 4 && in_array(12, array_merge($teamA, $teamB), true)) {
            continue;
        }
        $gamesA = ($r + $grp) % 5;
        $unscored = $r === 4 && $grp === 2;
        $s12Matches[] = [
            'round' => $r, 'team_a' => $teamA, 'team_b' => $teamB,
            'games_a' => $unscored ? null : $gamesA, 'games_b' => $unscored ? null : 4 - $gamesA,
        ];
    }
}
$standingsCases['s12'] = [range(1, 12), $s12Matches];
foreach ($standingsCases as $name => [$ids, $matches]) {
    $g['standings'][] = kase("standings_$name", ['participant_ids' => $ids, 'matches' => $matches], fn () => $standingsService->standings($ids, $matches));
}
$s12Rows = array_map(fn ($row) => ['id' => $row['id'], 'points_avg' => $row['points_avg'], 'games_avg' => $row['games_avg']], $standingsService->standings(range(1, 12), $s12Matches));

// ---- mexicano_rank ----
$g['mexicano_rank'] = [];
$rankSets = [
    'three_rows' => [['id' => 1, 'points_avg' => 1.0, 'games_avg' => 2.0], ['id' => 2, 'points_avg' => 2.0, 'games_avg' => 1.0], ['id' => 3, 'points_avg' => 1.0, 'games_avg' => 3.0]],
    'all_tied' => [['id' => 7, 'points_avg' => 0.0, 'games_avg' => 0.0], ['id' => 8, 'points_avg' => 0.0, 'games_avg' => 0.0], ['id' => 9, 'points_avg' => 0.0, 'games_avg' => 0.0]],
    's12' => $s12Rows,
];
foreach ($rankSets as $name => $rows) {
    foreach ($shuffles as $sh) {
        $g['mexicano_rank'][] = kase("rank_{$name}_$sh", ['rows' => $rows, 'shuffle' => $sh], fn () => $mexicano->rank($rows, shuffleBy($sh)));
    }
}

// ---- mexicano_sitters ----
$g['mexicano_sitters'] = [];
$fourteen = range(1, 14);
$counts14 = array_fill_keys($fourteen, 1);
$counts14[7] = 0;
$counts14[9] = 0;
$counts5 = array_fill_keys(range(1, 5), 1);
$counts5[4] = 0;
$sitterSets = [
    '12_none' => [range(1, 12), array_fill_keys(range(1, 12), 0)],
    '14_two_least' => [$fourteen, $counts14],
    '5_least' => [range(1, 5), $counts5],
];
foreach ([13, 14, 15] as $n) {
    $ids = range(1, $n);
    $sitterSets["{$n}_mod3"] = [$ids, array_combine($ids, array_map(fn ($id) => $id % 3, $ids))];
}
foreach ($sitterSets as $name => [$ids, $counts]) {
    foreach ($shuffles as $sh) {
        $g['mexicano_sitters'][] = kase("sitters_{$name}_$sh", ['participant_ids' => $ids, 'sit_out_counts' => $counts, 'shuffle' => $sh], fn () => $mexicano->chooseSitters($ids, $counts, shuffleBy($sh)));
    }
}

// ---- mexicano_propose ----
$g['mexicano_propose'] = [];
$proposeSets = [
    '10_no_counts' => [range(1, 10), []],
    'ranked_8' => [[5, 3, 8, 1, 9, 2, 7, 4], []],
    '15_mod2' => [range(1, 15), array_combine(range(1, 15), array_map(fn ($id) => $id % 2, range(1, 15)))],
];
foreach ($proposeSets as $name => [$ranked, $counts]) {
    foreach ($shuffles as $sh) {
        $g['mexicano_propose'][] = kase("mexicano_propose_{$name}_$sh", ['ranked_ids' => $ranked, 'sit_out_counts' => emptyMap($counts), 'shuffle' => $sh], fn () => $mexicano->propose($ranked, $counts, shuffleBy($sh)));
    }
}

// ---- mexicano_pair ----
$g['mexicano_pair'] = [];
foreach (['eight' => range(11, 18), 'four' => [1, 2, 3, 4], 'six_drops_incomplete' => range(1, 6), 'empty' => []] as $name => $ids) {
    $g['mexicano_pair'][] = kase("pair_$name", ['ranked_ids' => $ids], fn () => $mexicano->pair($ids));
}

// ---- waves / mexicano_slots / wave_count ----
function waveGroup(array $playerIds, bool $isExtra = false): array
{
    $matches = [];
    foreach (array_chunk($playerIds, 4) as $chunk) {
        $chunk = array_pad($chunk, 4, $playerIds[0]);
        $matches[] = [
            'team_a_p1' => $chunk[0], 'team_a_p2' => $chunk[1],
            'team_b_p1' => $chunk[2], 'team_b_p2' => $chunk[3],
            'is_extra' => $isExtra ? 1 : 0,
        ];
    }

    return $matches;
}

/** @param list<int> $sizes regular groups; @param list<int> $extraSizes extra groups appended */
function byGroup(array $sizes, array $extraSizes = []): array
{
    $out = [];
    $next = 1;
    foreach ([[$sizes, false], [$extraSizes, true]] as [$list, $extra]) {
        foreach ($list as $size) {
            $out[count($out)] = waveGroup(range($next, $next + $size - 1), $extra);
            $next += $size;
        }
    }

    return $out;
}

$g['waves'] = [];
$waveInputs = [
    'mixed_php_4_5_4' => byGroup([4, 5, 4]),
    'mixed_php_extra_five' => [
        0 => waveGroup([1, 2, 3, 4]), 1 => waveGroup([5, 6, 7, 8]), 2 => waveGroup([9, 10, 11, 12, 13], true),
    ],
    'regular_4_4_5_4_5' => byGroup([4, 4, 5, 4, 5]),
    'regular_4_4_4_extra' => byGroup([4, 4, 4], [4]),
    'regular_5_extra' => byGroup([5], [4]),
];
$waveCourts = [
    'mixed_php_4_5_4' => [2], 'mixed_php_extra_five' => [2],
    'regular_4_4_5_4_5' => [0, 1, 2, 3, 4], 'regular_4_4_4_extra' => [0, 1, 2, 3, 4], 'regular_5_extra' => [0, 1, 2, 3, 4],
];
$waveResults = [];
foreach ($waveInputs as $name => $by) {
    foreach ($waveCourts[$name] as $courts) {
        $case = kase("americano_{$name}_courts_$courts", ['by_group' => $by, 'court_count' => $courts], fn () => $waves->americanoWaves($by, $courts));
        $g['waves'][] = $case;
        $waveResults[$case['name']] = $case['result'];
    }
}
$tie = byGroup([4, 4, 4]);
$case = kase('americano_tie_one_court', ['by_group' => $tie, 'court_count' => 1], fn () => $waves->americanoWaves($tie, 1));
$g['waves'][] = $case;
$waveResults[$case['name']] = $case['result'];
$case = kase('americano_empty', ['by_group' => [], 'court_count' => 2], fn () => $waves->americanoWaves([], 2));
$g['waves'][] = $case;
$waveResults[$case['name']] = $case['result'];

$g['mexicano_slots'] = [];
$slotResults = [];
foreach (range(1, 7) as $groupCount) {
    foreach (range(1, 4) as $courts) {
        $by = byGroup(array_fill(0, $groupCount, 4));
        $case = kase("mexicano_slots_{$groupCount}_groups_{$courts}_courts", ['by_group' => $by, 'court_count' => $courts], fn () => $waves->mexicanoSlots($by, $courts));
        $g['mexicano_slots'][] = $case;
        $slotResults[$case['name']] = $case['result'];
    }
}

$g['wave_count'] = [];
foreach ($waveResults as $name => $result) {
    $g['wave_count'][] = kase("wave_count_$name", ['wave_by_group' => $result], fn () => $waves->waveCount($result));
}
foreach ($slotResults as $name => $result) {
    $g['wave_count'][] = kase("wave_count_$name", ['wave_by_group' => $result], fn () => $waves->waveCount($result));
}
$g['wave_count'][] = kase('wave_count_empty', ['wave_by_group' => []], fn () => $waves->waveCount([]));

// ---- scorecard_template ----
$g['scorecard_template'] = [];
foreach ([['americano', 4], ['americano', 5], ['mexicano', 4], ['mexicano', 5], ['americano', 6]] as [$mode, $size]) {
    $g['scorecard_template'][] = kase("scorecard_{$mode}_$size", ['mode' => $mode, 'group_size' => $size], fn () => [
        'rows' => $scorecard->rows($mode, $size),
        'player_count' => $scorecard->playerCount($mode, $size),
    ]);
}

$meta = [
    'old_repo_commit' => OLD_REPO_COMMIT,
    'php_version' => PHP_VERSION,
    'generated_at' => gmdate('c'),
    'files' => array_map(fn ($f) => $module.'/src/MixedTournament/Service/'.$f.'.php', SERVICE_FILES),
];

if (! is_dir($outDir) && ! mkdir($outDir, 0775, true)) {
    fwrite(STDERR, "cannot create $outDir\n");
    exit(1);
}
$json = json_encode(['meta' => $meta] + $g, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
file_put_contents($outDir.'/golden.json', $json."\n");
$count = array_sum(array_map('count', $g));
fwrite(STDOUT, "wrote $outDir/golden.json ($count cases)\n");
