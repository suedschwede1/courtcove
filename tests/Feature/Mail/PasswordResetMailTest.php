<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\PasswordReset;
use App\Models\Option;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasswordResetMailTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function it_uses_the_configured_service_name_in_the_subject(): void
    {
        Option::create(['key' => 'service.name', 'value' => 'ASV Buchung', 'locale' => null]);

        $mail = new PasswordReset(User::factory()->create(), 'https://example.com/reset');

        $this->assertSame('Passwort zurücksetzen – ASV Buchung', $mail->envelope()->subject);
    }
}
