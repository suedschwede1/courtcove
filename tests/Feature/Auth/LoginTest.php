<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Option;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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
    public function register_page_renders_configurable_registration_text(): void
    {
        Option::create(['key' => 'service.name', 'value' => 'ASV Buchung', 'locale' => null]);
        Option::create(['key' => 'service.user.registration.intro', 'value' => 'Nur Mitglieder duerfen sich registrieren.', 'locale' => null]);
        Option::create(['key' => 'service.user.registration.email_help', 'value' => 'Mit dieser Adresse melden Sie sich an.', 'locale' => null]);

        $this->get('/register')
            ->assertOk()
            ->assertViewIs('auth.register')
            ->assertSee('Nur Mitglieder duerfen sich registrieren.')
            ->assertSee('Mit dieser Adresse melden Sie sich an.');
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
            'status' => 'enabled',
        ]);

        $this->post('/login', ['email' => 'test@example.com', 'password' => 'wrong'])
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    #[Test]
    public function login_is_rate_limited_after_too_many_failed_attempts(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'pw' => bcrypt('secret123'),
            'status' => 'enabled',
        ]);

        $key = $this->throttleKey('test@example.com');
        RateLimiter::clear($key);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'test@example.com', 'password' => 'wrong'])
                ->assertRedirect('/login');
        }

        $this->post('/login', ['email' => 'test@example.com', 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGreaterThan(0, RateLimiter::availableIn($key));
        $this->assertGuest();
    }

    #[Test]
    public function successful_login_clears_rate_limit_counter(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'pw' => bcrypt('secret123'),
            'status' => 'enabled',
        ]);

        $key = $this->throttleKey('test@example.com');
        RateLimiter::clear($key);

        $this->post('/login', ['email' => 'test@example.com', 'password' => 'wrong'])
            ->assertRedirect('/login');

        $this->post('/login', ['email' => 'test@example.com', 'password' => 'secret123'])
            ->assertRedirect('/calendar');

        Auth::logout();

        $this->assertSame(0, RateLimiter::attempts($key));

        $this->post('/login', ['email' => 'test@example.com', 'password' => 'wrong'])
            ->assertRedirect('/login');

        $this->assertSame(1, RateLimiter::attempts($key));
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

    private function throttleKey(string $email): string
    {
        return strtolower(trim($email)).'|127.0.0.1';
    }
}
