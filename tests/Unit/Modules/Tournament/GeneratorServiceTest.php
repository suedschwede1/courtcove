<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Tournament;

use App\Modules\Tournament\Services\FormatService;
use App\Modules\Tournament\Services\GeneratorService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class GeneratorServiceTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function vectors(): array
    {
        return json_decode((string) file_get_contents(dirname(__DIR__, 3).'/Fixtures/tournament/vectors.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function generatorCases(): array
    {
        $cases = [];
        foreach (self::vectors()['generator'] as $case) {
            $cases[$case['op'].' '.$case['name']] = [$case];
        }

        return $cases;
    }

    private function format(string $name): FormatService
    {
        return new FormatService((string) file_get_contents(dirname(__DIR__, 3)."/Fixtures/tournament/formats/$name.json"));
    }

    /** @param  array<string, mixed>  $result */
    private function assertCaptured(array $result, callable $call, string $message): void
    {
        if (array_key_exists('error', $result)) {
            try {
                $call();
                $this->fail($message.': expected exception "'.$result['error'].'"');
            } catch (RuntimeException $e) {
                $this->assertSame($result['error'], $e->getMessage(), $message);
            }

            return;
        }
        $this->assertSame($result['ok'], $call(), $message);
    }

    /** @param  array<string, mixed>  $case */
    #[Test]
    #[DataProvider('generatorCases')]
    public function generator_vectors_match_the_old_code(array $case): void
    {
        $gen = new GeneratorService;
        $f = isset($case['format_override']) ? new FormatService($case['format_override']) : $this->format($case['format']);
        $phase = $case['phase'];

        match ($case['op']) {
            'assignHeads' => $this->assertCaptured($case['result'], fn () => $gen->assignHeads($f, $phase, $case['players']), $case['name']),
            'groupMatches' => $this->assertSame($case['result'], $gen->groupMatches($f, $phase, $case['players_by_group'], $case['existing']), $case['name']),
            'bracketLines' => $this->assertSame($case['result'], $gen->bracketLines($f, $phase), $case['name']),
            'bracketMatches' => $this->assertSame($case['result'], $gen->bracketMatches($f, $phase, $case['existing']), $case['name']),
            'advance' => $this->assertSame($case['result'], $gen->advance($f, $phase, $case['round'], $case['slot']), $case['name']),
            'poolPlaceholders' => $this->assertCaptured($case['result'], fn () => $gen->poolPlaceholders($f, $phase, $case['prefixes']), $case['name']),
            default => $this->fail('unknown op '.$case['op']),
        };
    }

    #[Test]
    public function every_vector_op_is_covered(): void
    {
        $ops = array_unique(array_column(self::vectors()['generator'], 'op'));
        sort($ops);
        $this->assertSame(['advance', 'assignHeads', 'bracketLines', 'bracketMatches', 'groupMatches', 'poolPlaceholders'], $ops);
    }

    #[Test]
    public function twenty_two_ko_matches(): void
    {
        $gen = new GeneratorService;
        $f = $this->format('reference');

        $this->assertCount(15, $gen->bracketMatches($f, 'KO_A'));
        $this->assertCount(7, $gen->bracketMatches($f, 'KO_B'));
    }

    #[Test]
    public function twenty_four_bracket_lines(): void
    {
        $gen = new GeneratorService;
        $f = $this->format('reference');

        $this->assertCount(16, $gen->bracketLines($f, 'KO_A'));
        $this->assertCount(8, $gen->bracketLines($f, 'KO_B'));
    }

    #[Test]
    public function generation_is_idempotent_on_existing_mkeys(): void
    {
        $gen = new GeneratorService;
        $f = $this->format('reference');

        foreach (['KO_A', 'KO_B'] as $phase) {
            $mkeys = array_column($gen->bracketMatches($f, $phase), 'mkey');
            $this->assertSame([], $gen->bracketMatches($f, $phase, $mkeys));
        }

        $byGroup = ['A' => [['tpid' => 1, 'grp_pos' => 1], ['tpid' => 4, 'grp_pos' => 2], ['tpid' => 5, 'grp_pos' => 3]]];
        $first = $gen->groupMatches($f, 'AC', $byGroup);
        $this->assertCount(3, $first['matches']);
        $second = $gen->groupMatches($f, 'AC', $byGroup, array_column($first['matches'], 'mkey'));
        $this->assertSame([], $second['matches']);
    }

    #[Test]
    public function advance_uses_ceil_half_and_side_a_for_odd_slots(): void
    {
        $gen = new GeneratorService;
        $f = $this->format('reference');

        $this->assertSame(['rnd' => 2, 'slot' => 1, 'side' => 'a'], $gen->advance($f, 'KO_A', 1, 1));
        $this->assertSame(['rnd' => 2, 'slot' => 1, 'side' => 'b'], $gen->advance($f, 'KO_A', 1, 2));
        $this->assertSame(['rnd' => 2, 'slot' => 4, 'side' => 'b'], $gen->advance($f, 'KO_A', 1, 8));
        $this->assertSame(['rnd' => 3, 'slot' => 3, 'side' => 'a'], $gen->advance($f, 'KO_A', 2, 5));
        $this->assertNull($gen->advance($f, 'KO_A', 4, 1), 'the final has no successor');
    }
}
