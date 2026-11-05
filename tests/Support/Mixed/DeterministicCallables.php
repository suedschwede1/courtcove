<?php

declare(strict_types=1);

namespace Tests\Support\Mixed;

use InvalidArgumentException;

/**
 * Rebuilds the named deterministic callables of tests/Fixtures/mixed/generate_golden.php.
 */
final class DeterministicCallables
{
    /** @var list<array{int, int}> */
    private static array $calls = [];

    /** @return callable(array<int, mixed>): array<int, mixed> */
    public static function shuffle(string $name): callable
    {
        return match ($name) {
            'identity' => fn (array $items): array => $items,
            'reverse' => fn (array $items): array => array_reverse($items),
            'rotate1' => function (array $items): array {
                $items = array_values($items);
                if ($items !== []) {
                    $items[] = array_shift($items);
                }

                return $items;
            },
            default => throw new InvalidArgumentException("unknown shuffle $name"),
        };
    }

    /**
     * @param  list<int>  $sequence
     * @return callable(int, int): int
     */
    public static function randomInt(array $sequence): callable
    {
        self::$calls = [];
        $i = 0;

        return function (int $min, int $max) use ($sequence, &$i): int {
            self::$calls[] = [$min, $max];
            $value = $sequence[$i % count($sequence)];
            $i++;

            return $min + ($value % ($max - $min + 1));
        };
    }

    /** The `{"constant": "min"}` callable of the golden file: always returns the lower bound. */
    public static function constantMin(): callable
    {
        self::$calls = [];

        return function (int $min, int $max): int {
            self::$calls[] = [$min, $max];

            return $min;
        };
    }

    /** @return list<array{int, int}> (min, max) pairs of the last randomInt()/constantMin() callable */
    public static function calls(): array
    {
        return self::$calls;
    }
}
