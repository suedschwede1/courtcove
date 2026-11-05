<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Pyramid;

use App\Modules\Pyramid\Services\RuleService;
use App\Modules\Pyramid\Services\SeedService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Replays every vector of tests/Fixtures/pyramid/golden.json (generated from the
 * old application's services) against the ported services.
 */
class PyramidGoldenVectorsTest extends TestCase
{
    /** @var array<string, mixed>|null */
    private static ?array $golden = null;

    /**
     * @return array<string, mixed>
     */
    private function golden(): array
    {
        return self::$golden ??= json_decode(
            (string) file_get_contents(dirname(__DIR__, 4).'/tests/Fixtures/pyramid/golden.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /**
     * The golden file was written with json_encode(), so floats such as 10.0 are
     * stored as 10. Pushing the actual value through the same round trip makes the
     * strict comparison independent of that.
     */
    private function viaJson(mixed $value): mixed
    {
        return json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $case
     */
    private function check(array $case, callable $call, string $label): void
    {
        if (isset($case['error'])) {
            $this->assertSame(InvalidArgumentException::class, $case['error']['class'], $label);

            try {
                $call();
            } catch (Throwable $e) {
                $this->assertSame($case['error']['class'], $e::class, $label);

                return;
            }

            $this->fail($label.': expected '.$case['error']['class']);
        }

        $this->assertSame($case['result'], $this->viaJson($call()), $label);
    }

    #[Test]
    public function rows_and_columns(): void
    {
        $rules = new RuleService;

        $this->assertNotEmpty($this->golden()['rows']['positions']);
        $this->assertNotEmpty($this->golden()['rows']['rows']);

        foreach ($this->golden()['rows']['positions'] as $c) {
            $this->assertSame($c['row'], $rules->rowOf($c['position']), 'row '.$c['position']);
            $this->assertSame($c['col'], $rules->colOf($c['position']), 'col '.$c['position']);
        }

        foreach ($this->golden()['rows']['rows'] as $c) {
            $this->assertSame($c['firstOfRow'], $rules->firstOfRow($c['row']));
            $this->assertSame($c['lastOfRow'], $rules->lastOfRow($c['row']));
        }
    }

    #[Test]
    public function invalid_rows_and_positions_throw(): void
    {
        $rules = new RuleService;
        $errors = $this->golden()['rows']['errors'];

        $this->assertNotEmpty($errors);

        foreach ($errors as $c) {
            $this->check($c, fn () => $rules->{$c['call']}($c['arg']), $c['call'].'('.$c['arg'].')');
        }
    }

    #[Test]
    public function challengeable_positions(): void
    {
        $rules = new RuleService;

        $this->assertNotEmpty($this->golden()['challengeable']);

        foreach ($this->golden()['challengeable'] as $c) {
            $this->check($c, fn () => $rules->challengeable($c['position'], $c['count']), 'challengeable '.$c['position'].'/'.json_encode($c['count']));
        }
    }

    #[Test]
    public function shift_for_win(): void
    {
        $rules = new RuleService;
        $errors = 0;

        $this->assertNotEmpty($this->golden()['shift_win']);

        foreach ($this->golden()['shift_win'] as $i => $c) {
            $errors += isset($c['error']) ? 1 : 0;
            $this->check($c, fn () => $rules->shiftForWin($c['positions'], $c['winnerPos'], $c['loserPos']), 'shift_win #'.$i.' '.$c['name']);
        }

        $this->assertSame(2, $errors, 'the two unknown-position cases are covered');
    }

    #[Test]
    public function shift_for_entry(): void
    {
        $rules = new RuleService;

        $this->assertNotEmpty($this->golden()['shift_entry']);

        foreach ($this->golden()['shift_entry'] as $i => $c) {
            $this->check($c, fn () => $rules->shiftForEntry($c['positions'], $c['loserPos']), 'shift_entry #'.$i.' '.$c['name']);
        }
    }

    #[Test]
    public function pyramid_rows(): void
    {
        $rules = new RuleService;

        $this->assertNotEmpty($this->golden()['pyramid_rows']);

        foreach ($this->golden()['pyramid_rows'] as $c) {
            $players = array_map(fn (int $p): array => ['position' => $p], $c['n'] > 0 ? range(1, $c['n']) : []);

            $this->assertSame($c['result'], array_map('count', array_values($rules->pyramidRows($players))), 'n='.$c['n']);
        }
    }

    #[Test]
    public function seeding(): void
    {
        $seeder = new SeedService;

        $this->assertNotEmpty($this->golden()['seed']);

        foreach ($this->golden()['seed'] as $c) {
            $this->check($c, fn () => $seeder->seed($c['players'], $c['itnDefault']), 'seed '.$c['name']);
        }
    }
}
