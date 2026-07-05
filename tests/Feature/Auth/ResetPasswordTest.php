<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function reset_password_page_renders_with_a_token(): void
    {
        $this->get('/password/reset/some-token?email=test@example.com')
            ->assertOk()
            ->assertViewIs('auth.reset-password')
            ->assertViewHas('token', 'some-token')
            ->assertViewHas('email', 'test@example.com');
    }

    #[Test]
    public function submitting_a_valid_token_resets_the_password_and_logs_in(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com', 'pw' => Hash::make('old-password'), 'status' => 'enabled']);
        $token = Password::broker()->createToken($user);

        $response = $this->post('/password/reset', [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertRedirect('/calendar');
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('new-password123', $user->refresh()->pw));
    }

    #[Test]
    public function submitting_an_invalid_token_fails_without_changing_the_password(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com', 'pw' => Hash::make('old-password'), 'status' => 'enabled']);

        $response = $this->post('/password/reset', [
            'token' => 'not-a-real-token',
            'email' => 'test@example.com',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertRedirect(route('password.forgot'));
        $this->assertGuest();
        $this->assertTrue(Hash::check('old-password', $user->refresh()->pw));
    }

    #[Test]
    public function submitting_an_unknown_email_fails_gracefully(): void
    {
        $response = $this->post('/password/reset', [
            'token' => 'irrelevant',
            'email' => 'nobody@example.com',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertRedirect(route('password.forgot'));
        $this->assertGuest();
    }

    #[Test]
    public function a_token_for_an_account_disabled_after_the_link_was_sent_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com', 'pw' => Hash::make('old-password'), 'status' => 'enabled']);
        $token = Password::broker()->createToken($user);
        $user->update(['status' => 'disabled']);

        $response = $this->post('/password/reset', [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertRedirect(route('password.forgot'));
        $this->assertGuest();
        $this->assertTrue(Hash::check('old-password', $user->refresh()->pw));
    }

    #[Test]
    public function password_confirmation_mismatch_fails_validation(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com', 'status' => 'enabled']);
        $token = Password::broker()->createToken($user);

        $this->post('/password/reset', [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => 'new-password123',
            'password_confirmation' => 'does-not-match',
        ])->assertSessionHasErrors('password');
    }

    #[Test]
    public function the_reset_route_is_rate_limited_after_five_requests_per_minute(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/password/reset', [
                'token' => 'irrelevant',
                'email' => 'nobody@example.com',
                'password' => 'new-password123',
                'password_confirmation' => 'new-password123',
            ]);
        }

        $this->post('/password/reset', [
            'token' => 'irrelevant',
            'email' => 'nobody@example.com',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertStatus(429);
    }
}
