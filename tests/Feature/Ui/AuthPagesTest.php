<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthPagesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function wrongPasswordPage(): TestResponse
    {
        User::factory()->create(['email' => 'user@example.test', 'pw' => bcrypt('secret123'), 'status' => 'enabled']);

        return $this->followingRedirects()->post('/login', ['email' => 'user@example.test', 'password' => 'Wrong-Pass-987']);
    }

    #[Test]
    public function login_uses_the_auth_shell(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('<div class="ds-auth">', false)
            ->assertSee('<h1 class="ds-auth__title">'.e(__('booking.auth.heading')).'</h1>', false)
            ->assertSee('for="email"', false)
            ->assertSee('id="email"', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertSee('class="ds-btn ds-btn--dark ds-btn--block"', false)
            ->assertSee(route('password.forgot'), false)
            ->assertSee(route('register'), false)
            ->assertDontSee('style=', false)
            ->assertDontSee('bg-[#', false);
    }

    #[Test]
    public function login_keeps_the_redirect_target_in_the_form(): void
    {
        $this->get('/login?redirect_to=/my-bookings')
            ->assertSee('name="redirect_to" value="/my-bookings"', false);
    }

    #[Test]
    public function wrong_password_shows_the_error_under_the_email_field(): void
    {
        $this->wrongPasswordPage()
            ->assertSee('id="email-error"', false)
            ->assertSee(__('booking.auth.invalid'))
            ->assertSee('aria-invalid="true"', false);
    }

    #[Test]
    public function login_never_echoes_the_password(): void
    {
        $this->wrongPasswordPage()->assertDontSee('Wrong-Pass-987');
    }

    #[Test]
    public function login_shows_a_verification_error_from_the_redirect(): void
    {
        // Same path as the 2FA redirect: errors flashed by a redirect (a ViewErrorBag cannot be seeded via withSession(), the test session serializes as JSON).
        Route::middleware('web')->get('/_test/flash-code-error', fn () => redirect('/login')->withErrors(['code' => 'Code expired X']));

        $this->followingRedirects()
            ->get('/_test/flash-code-error')
            ->assertSee('Code expired X')
            ->assertSee('ds-notice--problem', false);
    }

    #[Test]
    public function login_shows_the_registration_success_notice(): void
    {
        $this->withSession(['registered' => true])
            ->get('/login')
            ->assertSee('ds-notice--good', false)
            ->assertSee(str_replace(':system', config('booking.name'), __('booking.register.success_message')));
    }

    #[Test]
    public function forgot_password_shows_the_form_in_the_shell(): void
    {
        $this->get('/password/forgot')
            ->assertOk()
            ->assertSee('<div class="ds-auth">', false)
            ->assertSee('action="'.route('password.email').'"', false)
            ->assertSee('for="email"', false)
            ->assertSee('ds-btn--dark', false)
            ->assertSee('href="'.route('login').'"', false);
    }

    #[Test]
    public function forgot_password_shows_only_the_status_after_sending(): void
    {
        $this->withSession(['status' => 'Sent X'])
            ->get('/password/forgot')
            ->assertSee('Sent X')
            ->assertSee('role="status"', false)
            ->assertDontSee('action="'.route('password.email').'"', false);
    }

    #[Test]
    public function reset_password_keeps_token_and_email_and_empty_passwords(): void
    {
        $html = $this->get('/password/reset/tok-1?email=user@example.test')
            ->assertOk()
            ->assertSee('name="token" value="tok-1"', false)
            ->assertSee('name="email" value="user@example.test"', false)
            ->assertSee('id="password"', false)
            ->assertSee('id="password_confirmation"', false)
            ->getContent();

        $this->assertSame(2, preg_match_all('/<input type="password"[^>]*value=""/', $html));
    }

    #[Test]
    public function reset_password_shows_an_invalid_link_error(): void
    {
        $this->withViewErrors(['email' => 'Bad link X'])
            ->view('auth.reset-password', ['token' => 't', 'email' => 'e@example.test'])
            ->assertSee('Bad link X')
            ->assertSee('ds-notice--problem', false);
    }

    #[Test]
    public function verify_page_has_code_field_and_resend(): void
    {
        $this->withViewErrors([])
            ->view('auth.login-verify')
            ->assertSee('name="code"', false)
            ->assertSee('inputmode="numeric"', false)
            ->assertSee('pattern="[0-9]*"', false)
            ->assertSee('maxlength="6"', false)
            ->assertSee('ds-input--score', false)
            ->assertSee('action="'.route('login.verify.submit').'"', false)
            ->assertSee('action="'.route('login.verify.resend').'"', false)
            ->assertSee('ds-btn--text', false);
    }

    /** @return array<string, string> */
    private function registerPayload(): array
    {
        return [
            'email' => 'new@example.test',
            'email_confirm' => 'new@example.test',
            'password' => 'Secret-Value-123',
            'password_confirm' => 'Secret-Value-123',
            'firstname' => 'Test',
            'lastname' => 'User',
            'phone' => '0123456789',
            'privacy' => '1',
        ];
    }

    #[Test]
    public function register_page_uses_the_shell_with_labelled_fields(): void
    {
        $response = $this->get('/register')
            ->assertOk()
            ->assertSee('<div class="ds-auth">', false)
            ->assertSee('class="ds-auth__link"', false)
            ->assertSee('name="privacy"', false)
            ->assertSee('ds-btn--dark', false);

        foreach (['email', 'email_confirm', 'password', 'password_confirm', 'gender', 'firstname', 'lastname', 'street', 'zip', 'city', 'phone'] as $id) {
            $response->assertSee('for="'.$id.'"', false)->assertSee('id="'.$id.'"', false);
        }
    }

    #[Test]
    public function register_never_echoes_passwords_after_a_failed_submit(): void
    {
        $this->from('/register')->followingRedirects()
            ->post('/register', ['email_confirm' => 'other@example.test'] + $this->registerPayload())
            ->assertSee('id="email_confirm-error"', false)
            ->assertDontSee('Secret-Value-123');
    }

    #[Test]
    public function register_shows_name_errors_under_their_fields(): void
    {
        $payload = $this->registerPayload();
        unset($payload['firstname'], $payload['lastname']);

        $this->from('/register')->followingRedirects()->post('/register', $payload)
            ->assertSee('id="firstname-error"', false)
            ->assertSee('id="lastname-error"', false);
    }
}
