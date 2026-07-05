<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\LoginVerificationCode;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginVerificationCodeMailTest extends TestCase
{
    #[Test]
    public function it_renders_the_code_in_the_body(): void
    {
        $mail = new LoginVerificationCode('123456');

        $rendered = $mail->render();

        $this->assertStringContainsString('123456', $rendered);
    }

    #[Test]
    public function it_uses_the_translated_subject(): void
    {
        $mail = new LoginVerificationCode('123456');

        $this->assertSame(__('booking.mail.login_verification_subject'), $mail->envelope()->subject);
    }
}
