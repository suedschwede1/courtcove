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
}
