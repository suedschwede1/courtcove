<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Tournament;

use App\Modules\Tournament\Services\FormatService;
use App\Modules\Tournament\Services\InvalidFormatException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormatServiceTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function vectors(): array
    {
        return json_decode((string) file_get_contents(dirname(__DIR__, 3).'/Fixtures/tournament/vectors.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function formatCases(): array
    {
        $cases = [];
        foreach (self::vectors()['format'] as $case) {
            $cases[$case['name']] = [$case];
        }

        return $cases;
    }

    /** @param  array<string, mixed>  $result */
    private function assertCaptured(array $result, callable $call, string $message): void
    {
        if (array_key_exists('error', $result)) {
            try {
                $call();
                $this->fail($message.': expected exception "'.$result['error'].'"');
            } catch (InvalidFormatException $e) {
                $this->assertSame($result['error'], $e->getMessage(), $message);
            }

            return;
        }
        $this->assertSame($result['ok'], $call(), $message);
    }

    /** @param  array<string, mixed>  $case */
    #[Test]
    #[DataProvider('formatCases')]
    public function matches_the_old_code_for_every_vector(array $case): void
    {
        $f = new FormatService(json_encode($case['format']));
        $name = $case['name'];

        $this->assertCaptured($case['validate'], fn () => $f->validate(), "$name validate");
        $this->assertCaptured($case['phase_lookup_error'], fn () => $f->phase('NOPE'), "$name phase lookup");
        if (! empty($case['invalid'])) {
            return;
        }

        $this->assertSame($case['match_format'], $f->matchFormat());
        $this->assertSame($case['ranking'], $f->ranking());
        $this->assertSame($case['runner_up_mode'], $f->runnerUpMode());
        $this->assertSame($case['phases'], $f->phases());
        $this->assertSame($case['group_phases'], array_column($f->groupPhases(), 'key'));
        $this->assertSame($case['bracket_phases'], array_column($f->bracketPhases(), 'key'));
        foreach ($case['pairings'] as $size => $expected) {
            $this->assertSame($expected, $f->pairings((int) $size), "$name pairings $size");
        }
        foreach ($case['first_round_pairs'] as $size => $expected) {
            $this->assertSame($expected, $f->firstRoundPairs((int) $size), "$name first round $size");
        }
        foreach ($case['rounds'] as $size => $expected) {
            $this->assertSame($expected, $f->rounds((int) $size), "$name rounds $size");
        }
        foreach ($case['group_slots'] as $key => $expected) {
            $this->assertSame($expected, $f->groupSlots((string) $key), "$name group slots $key");
        }
        foreach ($case['draw_slots'] as $key => $expected) {
            $this->assertSame($expected, $f->drawSlots((string) $key), "$name draw slots $key");
        }
        foreach ($case['group_matches'] as $key => $expected) {
            $this->assertSame($expected, $f->groupMatches((string) $key), "$name group matches $key");
        }
        foreach ($case['line_map'] as $key => $expected) {
            $this->assertSame($expected, $f->lineMap((string) $key), "$name line map $key");
        }
    }

    private function reference(): FormatService
    {
        return new FormatService((string) file_get_contents(dirname(__DIR__, 3).'/Fixtures/tournament/formats/reference.json'));
    }

    #[Test]
    public function reference_format_validates(): void
    {
        $this->assertCount(2, $this->reference()->validate());
    }

    #[Test]
    public function ac_pool_starts_at_ac5(): void
    {
        $this->assertSame(5, $this->reference()->phase('KO_A')['lines']['pool_codes']['ac']['start']);
    }

    #[Test]
    public function ac_has_twelve_group_matches(): void
    {
        $this->assertCount(12, $this->reference()->groupMatches('AC'));
    }

    #[Test]
    public function ko_a_has_no_bye_and_six_pool_lines(): void
    {
        $lines = $this->reference()->phase('KO_A')['lines'];
        $this->assertSame([], $lines['bye'] ?? []);
        $this->assertCount(6, $lines['pools']['q']);
    }

    #[Test]
    public function ko_a_size_and_dg_seed_range(): void
    {
        $f = $this->reference();
        $this->assertSame(16, $f->phase('KO_A')['size']);
        $this->assertSame([11, 24], $f->phase('DG')['seed_range']);
    }

    #[Test]
    public function an_invalid_json_string_is_rejected_with_the_old_message(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('format is not valid JSON');
        new FormatService('not json');
    }

    #[Test]
    public function a_missing_format_is_rejected(): void
    {
        $this->expectException(InvalidFormatException::class);
        new FormatService(null);
    }

    /** @return array<string, mixed> */
    private function formatArray(string $name): array
    {
        return json_decode((string) file_get_contents(dirname(__DIR__, 3)."/Fixtures/tournament/formats/$name.json"), true, flags: JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function missing_keys_fall_back_to_the_zf2_defaults(): void
    {
        $f = new FormatService('{}');

        $this->assertSame('best_of_3_mtb', $f->matchFormat());
        $this->assertSame('drop_last', $f->runnerUpMode());
        $this->assertSame([], $f->phases());
        $this->assertSame(
            ['points_per_win' => 2, 'tiebreak_scope' => 'tied', 'criteria' => ['points', 'head_to_head', 'set_diff', 'game_diff', 'lot']],
            $f->ranking()
        );
        $this->assertSame([], $f->groupPhases());
        $this->assertSame([], $f->bracketPhases());

        $partial = new FormatService('{"ranking":{"points_per_win":3}}');
        $this->assertSame(3, $partial->ranking()['points_per_win']);
        $this->assertSame('tied', $partial->ranking()['tiebreak_scope']);
    }

    #[Test]
    public function non_string_stored_values_fall_back_to_the_default_instead_of_throwing(): void
    {
        $f = new FormatService('{"match_format":123,"runner_up":{"mode":5}}');
        $this->assertSame('best_of_3_mtb', $f->matchFormat());
        $this->assertSame('drop_last', $f->runnerUpMode());

        $g = new FormatService('{"match_format":["x"],"runner_up":"points"}');
        $this->assertSame('best_of_3_mtb', $g->matchFormat());
        $this->assertSame('drop_last', $g->runnerUpMode());

        $h = new FormatService('{"match_format":"best_of_1","runner_up":{"mode":"points"}}');
        $this->assertSame('best_of_1', $h->matchFormat());
        $this->assertSame('points', $h->runnerUpMode());
    }

    #[Test]
    public function draw_slots_with_another_slot_order_follow_the_group_order(): void
    {
        $format = $this->formatArray('reference');
        $byPosition = (new FormatService($format))->drawSlots('AC');
        $format['phases'][0]['draw']['slot_order'] = 'by_group';
        $f = new FormatService($format);

        $open = array_values(array_filter($f->groupSlots('AC'), fn (array $s) => ! $s['head']));
        $this->assertSame(array_column($open, 'slot'), $f->drawSlots('AC'));
        $this->assertSame(['A2', 'A3', 'B2', 'B3', 'C2', 'C3', 'C4'], $f->drawSlots('AC'));
        $this->assertNotSame($byPosition, $f->drawSlots('AC'));
        $this->assertSame(['A2', 'B2', 'C2', 'A3', 'B3', 'C3', 'C4'], $byPosition);
    }

    /** @param  callable(array<string, mixed>): array<string, mixed>  $mutate */
    private function assertInvalid(callable $mutate, string $message): void
    {
        $f = new FormatService($mutate($this->formatArray('reference')));
        try {
            $f->validate();
            $this->fail('expected "'.$message.'"');
        } catch (InvalidFormatException $e) {
            $this->assertSame($message, $e->getMessage());
        }
    }

    #[Test]
    public function the_remaining_validate_errors_have_the_old_messages(): void
    {
        $this->assertInvalid(function (array $f): array {
            $f['phases'][0]['groups'] = [];

            return $f;
        }, 'phase "AC" has no groups');

        $this->assertInvalid(function (array $f): array {
            $f['phases'][0]['groups'][0]['size'] = 1;

            return $f;
        }, 'group "A" needs at least 2 players');

        $this->assertInvalid(function (array $f): array {
            $f['phases'][0]['groups'][1]['key'] = 'A';

            return $f;
        }, 'duplicate group key "A"');

        $this->assertInvalid(function (array $f): array {
            $f['phases'][2]['lines']['pools']['q'][] = 17;

            return $f;
        }, 'bracket "KO_A": line 17 is outside the bracket');
    }

    #[Test]
    public function a_bye_next_to_an_unseeded_line_produces_the_note(): void
    {
        $format = $this->formatArray('three-groups-with-bye');
        $this->assertSame([], (new FormatService($format))->validate(), 'the fixture bye sits next to a seed');

        $format['phases'][1]['lines']['bye'] = [4];
        $format['phases'][1]['lines']['pools']['r'] = [2, 7];

        $this->assertSame(
            ['Bracket "KO": das Freilos auf Linie 4 steht neben Linie 3, die nicht gesetzt ist. Ueblich ist ein Freilos fuer die hoechsten Setznummern.'],
            (new FormatService($format))->validate()
        );
    }
}
