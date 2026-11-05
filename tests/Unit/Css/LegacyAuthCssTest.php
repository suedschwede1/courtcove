<?php

namespace Tests\Unit\Css;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LegacyAuthCssTest extends TestCase
{
    #[Test]
    public function legacy_auth_rules_are_gone_from_booking_css(): void
    {
        $css = (string) file_get_contents(public_path('css/booking.css'));

        foreach (['.login-page-panel', '.login-page-copy', '.auth-form', '.auth-label', '.auth-checkbox', '.auth-submit', '.error-message', '.success-message'] as $selector) {
            $this->assertStringNotContainsString($selector, $css, $selector.' is still in booking.css');
        }
    }

    #[Test]
    public function legacy_auth_partial_is_gone(): void
    {
        $this->assertFileDoesNotExist(resource_path('views/auth/_form.blade.php'));
    }
}
