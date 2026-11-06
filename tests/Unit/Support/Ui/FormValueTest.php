<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ui;

use App\Support\Ui\FormValue;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FormValueTest extends TestCase
{
    #[Test]
    public function single_values_are_strings_and_arrays_become_empty(): void
    {
        $this->assertSame('abc', FormValue::string('abc'));
        $this->assertSame('12', FormValue::string(12));
        $this->assertSame('', FormValue::string(null));
        $this->assertSame('', FormValue::string(['x']));
        $this->assertSame('', FormValue::string(new \stdClass));
    }

    #[Test]
    public function list_values_keep_scalars_and_drop_the_rest(): void
    {
        $this->assertSame(['1', '2'], FormValue::list([1, '2', ['x']]));
        $this->assertSame([], FormValue::list('x'));
        $this->assertSame([], FormValue::list(null));
    }
}
