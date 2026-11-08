<?php

declare(strict_types=1);

namespace Tests\Unit\Phone;

use App\Support\Phone\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PhoneNumberTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: bool, 3: bool, 4: bool, 5: ?string}>
     */
    public static function cases(): array
    {
        return [
            'at national mobile' => ['0660 0000000', '+436600000000', true, true, true, null],
            'at international mobile' => ['+43 660 0000000', '+436600000000', true, true, true, null],
            'at 00 prefix mobile' => ['0043 660 0000000', '+436600000000', true, true, true, null],
            'at landline' => ['01 0000000', '+4310000000', true, false, true, 'not_mobile'],
            'de mobile' => ['+49 171 0000000', '+491710000000', true, true, true, null],
            'de landline' => ['+49 30 0000000', '+49300000000', true, false, true, 'not_mobile'],
            'ch not checked' => ['+41 79 000 00 00', '+41790000000', true, false, false, null],
            'separators' => ['+43 (660) 000-00.0/0', '+436600000000', true, true, true, null],
            'empty string' => ['', null, false, false, false, 'empty'],
            'null' => [null, null, false, false, false, 'empty'],
            'at trunk zero in brackets' => ['+43 (0) 660 0000000', '+436600000000', true, true, true, null],
            'at trunk zero after plus' => ['+43 0660 0000000', '+436600000000', true, true, true, null],
            'de trunk zero mobile' => ['+49 (0) 151 00000000', '+4915100000000', true, true, true, null],
            'bare digits without plus' => ['436600000000', null, false, false, false, 'invalid'],
            'letters' => ['abc', null, false, false, false, 'invalid'],
            'leading zero country' => ['+0123', null, false, false, false, 'invalid'],
            'too short' => ['+43 6', null, false, false, false, 'invalid'],
            'too long' => ['+1234567890123456', null, false, false, false, 'invalid'],
        ];
    }

    #[Test]
    #[DataProvider('cases')]
    public function it_parses(?string $raw, ?string $e164, bool $valid, bool $mobile, bool $checked, ?string $error): void
    {
        $result = PhoneNumber::parse($raw);

        $this->assertSame($e164, $result->e164);
        $this->assertSame($valid, $result->valid);
        $this->assertSame($mobile, $result->mobile);
        $this->assertSame($checked, $result->mobileChecked);
        $this->assertSame($error, $result->error);
    }

    #[Test]
    public function it_returns_the_last_two_digits(): void
    {
        $this->assertSame('42', PhoneNumber::parse('+436600000042')->last2());
        $this->assertNull(PhoneNumber::parse('')->last2());
    }

    #[Test]
    public function the_default_country_is_configurable(): void
    {
        $this->assertSame('+491710000000', PhoneNumber::parse('0171 0000000', '49')->e164);
    }
}
