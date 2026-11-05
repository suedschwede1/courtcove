<?php

namespace Tests\Unit\Support\Ui;

use App\Support\Ui\Initials;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InitialsTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function names(): array
    {
        return [
            'two words' => ['Example Club', 'EC'],
            'one word' => ['example', 'E'],
            'umlauts and padding' => ['  Ünal  Öz ', 'ÜÖ'],
            'only first two words' => ['Anna Maria Beispiel', 'AM'],
            'empty' => ['', '?'],
        ];
    }

    #[Test]
    #[DataProvider('names')]
    public function builds_initials(string $name, string $expected): void
    {
        $this->assertSame($expected, Initials::from($name));
    }
}
