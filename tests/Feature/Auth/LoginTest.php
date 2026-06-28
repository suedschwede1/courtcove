<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertViewIs('auth.login');
    }

    #[Test]
    public function user_can_login_with_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'pw' => bcrypt('secret123'),
            'status' => 'enabled',
        ]);

        $this->post('/login', ['email' => 'test@example.com', 'password' => 'secret123'])
            ->assertRedirect('/calendar');

        $this->assertAuthenticated();
    }

    #[Test]
    public function login_respects_redirect_target(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'pw' => bcrypt('secret123'),
            'status' => 'enabled',
        ]);

        $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'secret123',
            'redirect_to' => '/calendar?date=2026-07-10',
        ])->assertRedirect('/calendar?date=2026-07-10');
    }

    #[Test]
    public function login_rejects_protocol_relative_redirect_target(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'pw' => bcrypt('secret123'),
            'status' => 'enabled',
        ]);

        $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'secret123',
            'redirect_to' => '//example.com',
        ])->assertRedirect('/calendar');
    }

    #[Test]
    public function login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'pw' => bcrypt('secret123'),
        ]);

        $this->post('/login', ['email' => 'test@example.com', 'password' => 'wrong'])
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    #[Test]
    public function login_fails_with_unknown_email(): void
    {
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'any'])
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    #[Test]
    public function disabled_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'pw' => bcrypt('secret123'),
            'status' => 'disabled',
        ]);

        $this->post('/login', ['email' => 'test@example.com', 'password' => 'secret123'])
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    #[Test]
    public function authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    #[Test]
    public function guest_can_access_calendar(): void
    {
        $this->get('/calendar')->assertOk();
    }

    #[Test]
    public function authenticated_user_can_access_calendar(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/calendar')->assertOk();
    }
}
