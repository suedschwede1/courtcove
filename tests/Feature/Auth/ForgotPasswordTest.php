<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Mail\PasswordReset;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function forgot_password_page_renders(): void
    {
        $this->get('/password/forgot')->assertOk()->assertViewIs('auth.forgot-password');
    }

    #[Test]
    public function requesting_a_reset_link_for_an_existing_enabled_user_queues_the_mail(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'test@example.com', 'status' => 'enabled']);

        $this->post('/password/forgot', ['email' => 'test@example.com'])
            ->assertRedirect('/password/forgot')
            ->assertSessionHas('status');

        Mail::assertQueued(PasswordReset::class, fn (PasswordReset $mail) => $mail->user->is($user));
    }

    #[Test]
    public function requesting_a_reset_link_for_an_unknown_email_shows_the_same_generic_message(): void
    {
        Mail::fake();

        $this->post('/password/forgot', ['email' => 'nobody@example.com'])
            ->assertRedirect('/password/forgot')
            ->assertSessionHas('status');

        Mail::assertNothingQueued();
    }

    #[Test]
    public function requesting_a_reset_link_for_a_disabled_user_does_not_send_mail(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'disabled@example.com', 'status' => 'disabled']);

        $this->post('/password/forgot', ['email' => 'disabled@example.com'])
            ->assertRedirect('/password/forgot')
            ->assertSessionHas('status');

        Mail::assertNothingQueued();
    }

    #[Test]
    public function the_forgot_password_route_is_rate_limited_after_five_requests_per_minute(): void
    {
        Mail::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/password/forgot', ['email' => 'nobody@example.com'])
                ->assertRedirect('/password/forgot');
        }

        $this->post('/password/forgot', ['email' => 'nobody@example.com'])
            ->assertStatus(429);
    }
}
