<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function validPayload(int $i): array
    {
        return [
            'email' => "user{$i}@example.com",
            'email_confirm' => "user{$i}@example.com",
            'password' => 'secret123',
            'password_confirm' => 'secret123',
            'firstname' => 'Test',
            'lastname' => 'User',
            'phone' => '0123456789',
            'privacy' => '1',
        ];
    }

    #[Test]
    public function registration_is_rate_limited_after_too_many_attempts_from_the_same_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', $this->validPayload($i))
                ->assertRedirect('/login');
        }

        $this->post('/register', $this->validPayload(999))
            ->assertStatus(429);
    }
}
