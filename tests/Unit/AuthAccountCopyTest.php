<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthAccountCopyTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function keys(): array
    {
        $keys = [
            'auth.intro',
            'auth.email_placeholder',
            'auth.register_note',
            'auth.verify.send_failed',
            'auth.verify.intro',
            'auth.verify.expired',
            'register.intro',
            'register.email_help',
            'register.email_confirm_help',
            'register.password_help',
            'register.password_confirm_help',
            'register.phone_help',
            'register.success_message',
            'password_reset.forgot_intro',
            'password_reset.forgot_email_placeholder',
            'password_reset.reset_intro',
            'password_reset.reset_invalid',
            'password_reset.reset_success',
            'account.intro',
            'account.bookings_intro',
        ];

        return array_combine($keys, array_map(static fn (string $k): array => [$k], $keys));
    }

    #[Test]
    #[DataProvider('keys')]
    public function german_copy_uses_du_and_no_exclamation_marks(string $key): void
    {
        app()->setLocale('de');

        $value = __('booking.'.$key);

        $this->assertNotSame('booking.'.$key, $value);
        $this->assertDoesNotMatchRegularExpression('/\b(Sie|Ihr|Ihre|Ihren|Ihrem|Ihrer|Ihnen)\b/u', $value);
        $this->assertStringNotContainsString('!', $value);
    }
}
