<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Members;

use App\Modules\Members\Support\AddressSplitter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AddressSplitterTest extends TestCase
{
    #[Test]
    public function splits_the_import_format_street_comma_zip_city(): void
    {
        $this->assertSame(
            ['street' => 'Teststr. 1', 'zip' => '4400', 'city' => 'Sampletown'],
            AddressSplitter::split('Teststr. 1, 4400 Sampletown'),
        );
    }

    #[Test]
    public function splits_zip_and_city_without_a_street(): void
    {
        $this->assertSame(
            ['street' => null, 'zip' => '4400', 'city' => 'Sampletown'],
            AddressSplitter::split('4400 Sampletown'),
        );
    }

    #[Test]
    public function keeps_an_unparsable_value_entirely_in_street(): void
    {
        $this->assertSame(
            ['street' => 'Hauptplatz 5', 'zip' => null, 'city' => null],
            AddressSplitter::split('Hauptplatz 5'),
        );
    }

    #[Test]
    public function returns_nulls_for_empty_input(): void
    {
        $this->assertSame(
            ['street' => null, 'zip' => null, 'city' => null],
            AddressSplitter::split(null),
        );
        $this->assertSame(
            ['street' => null, 'zip' => null, 'city' => null],
            AddressSplitter::split('  '),
        );
    }

    #[Test]
    public function handles_a_city_with_spaces(): void
    {
        $this->assertSame(
            ['street' => 'Weg 2', 'zip' => '4400', 'city' => 'Sampletown Gleink'],
            AddressSplitter::split('Weg 2, 4400 Sampletown Gleink'),
        );
    }
}
