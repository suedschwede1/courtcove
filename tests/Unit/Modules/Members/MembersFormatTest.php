<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Members;

use App\Modules\Members\Support\MembersFormat;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MembersFormatTest extends TestCase
{
    #[Test]
    public function german_money_uses_a_decimal_comma_and_a_thousands_dot(): void
    {
        app()->setLocale('de');

        $this->assertSame('3.800,00 €', MembersFormat::money(3800));
        $this->assertSame('200,00 €', MembersFormat::money('200.00'));
        $this->assertSame('1.234.567,50 €', MembersFormat::money(1234567.5));
        $this->assertSame('0,00 €', MembersFormat::money(null));
    }

    #[Test]
    public function english_money_uses_a_decimal_point_and_a_thousands_comma(): void
    {
        app()->setLocale('en');

        $this->assertSame('€3,800.00', MembersFormat::money(3800));
        $this->assertSame('€50.00', MembersFormat::money('50'));
    }

    #[Test]
    public function the_plain_amount_has_no_currency_sign(): void
    {
        app()->setLocale('de');
        $this->assertSame('1.200,00', MembersFormat::amount(1200));

        app()->setLocale('en');
        $this->assertSame('1,200.00', MembersFormat::amount(1200));
    }

    #[Test]
    public function an_optional_amount_shows_a_dash_when_missing(): void
    {
        app()->setLocale('de');

        $this->assertSame('–', MembersFormat::optionalMoney(null));
        $this->assertSame('50,00 €', MembersFormat::optionalMoney('50.00'));
    }
}
