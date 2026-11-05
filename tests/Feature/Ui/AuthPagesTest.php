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
}
