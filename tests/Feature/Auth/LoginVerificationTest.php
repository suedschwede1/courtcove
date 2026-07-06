<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Mail\LoginVerificationCode;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Stevebauman\Location\Facades\Location;
use Stevebauman\Location\LocationManager;
use Stevebauman\Location\Position;
use Tests\TestCase;

class LoginVerificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function triggerVerification(): User
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'pw' => bcrypt('secret123'),
            'status' => 'enabled',
        ]);

        Location::fake(['127.0.0.1' => Position::make(['countryCode' => 'AT'])]);
        LoginHistory::record((int) $user->uid, '1.1.1.1', 'AT', 'success');

        app()->forgetInstance(LocationManager::class);
        Location::clearResolvedInstance(LocationManager::class);
        Location::fake(['127.0.0.1' => Position::make(['countryCode' => 'RU'])]);

        $this->post('/login', ['email' => 'test@example.com', 'password' => 'secret123'])
            ->assertRedirect(route('login.verify.show'));

        return $user;
    }

    #[Test]
    public function verify_page_redirects_to_login_without_a_pending_verification(): void
    {
        $this->get('/login/verify')->assertRedirect('/login');
    }

    #[Test]
    public function verify_page_renders_with_a_pending_verification(): void
    {
        $this->triggerVerification();

        $this->get('/login/verify')->assertOk()->assertViewIs('auth.login-verify');
    }

    #[Test]
    public function wrong_code_does_not_authenticate_and_keeps_the_pending_state(): void
    {
        $this->triggerVerification();

        $this->post('/login/verify', ['code' => '000000'])
            ->assertRedirect(route('login.verify.show'))
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertIsArray(session('login.pending_verification'));
    }

    #[Test]
    public function five_wrong_codes_clear_the_pending_state(): void
    {
        $this->triggerVerification();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login/verify', ['code' => '000000']);
        }

        $this->assertNull(session('login.pending_verification'));
        $this->assertGuest();
    }

    #[Test]
    public function verify_completes_the_login_with_the_correct_code(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'pw' => bcrypt('secret123'),
            'status' => 'enabled',
        ]);

        Location::fake(['127.0.0.1' => Position::make(['countryCode' => 'AT'])]);
        LoginHistory::record((int) $user->uid, '1.1.1.1', 'AT', 'success');

        // Past the 60s same-request grace window (LoginRiskEvaluator::IMPOSSIBLE_TRAVEL_MIN_GAP_SECONDS)
        // so this genuinely represents impossible travel rather than test-timing noise.
        $this->travel(2)->minutes();

        app()->forgetInstance(LocationManager::class);
        Location::clearResolvedInstance(LocationManager::class);
        Location::fake(['127.0.0.1' => Position::make(['countryCode' => 'RU'])]);

        $this->post('/login', ['email' => 'test@example.com', 'password' => 'secret123']);

        $this->assertDatabaseMissing('login_history', [
            'user_id' => $user->uid,
            'country_code' => 'RU',
            'status' => 'success',
            'risk_flags' => 'unusual_country,impossible_travel',
        ]);

        $pending = session('login.pending_verification');
        $code = '482913';
        session(['login.pending_verification' => array_merge($pending, [
            'code_hash' => Hash::make($code),
        ])]);

        $this->post('/login/verify', ['code' => $code])
            ->assertRedirect('/calendar');

        $this->assertAuthenticated();
        $this->assertNull(session('login.pending_verification'));
        $this->assertDatabaseHas('login_history', [
            'user_id' => $user->uid,
            'country_code' => 'RU',
            'status' => 'success',
            'risk_flags' => 'unusual_country,impossible_travel',
        ]);
    }

    #[Test]
    public function expired_code_is_rejected_and_clears_the_pending_state(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'pw' => bcrypt('secret123'),
            'status' => 'enabled',
        ]);

        Location::fake(['127.0.0.1' => Position::make(['countryCode' => 'AT'])]);
        LoginHistory::record((int) $user->uid, '1.1.1.1', 'AT', 'success');

        app()->forgetInstance(LocationManager::class);
        Location::clearResolvedInstance(LocationManager::class);
        Location::fake(['127.0.0.1' => Position::make(['countryCode' => 'RU'])]);

        $this->post('/login', ['email' => 'test@example.com', 'password' => 'secret123']);

        $pending = session('login.pending_verification');
        $code = '482913';
        session(['login.pending_verification' => array_merge($pending, [
            'code_hash' => Hash::make($code),
            'expires_at' => now()->subMinute()->toIso8601String(),
        ])]);

        $this->post('/login/verify', ['code' => $code])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertNull(session('login.pending_verification'));
    }

    #[Test]
    public function resend_sends_a_new_code_and_is_throttled(): void
    {
        Mail::fake();
        $this->triggerVerification();

        RateLimiter::clear('login-verify-resend:'.User::where('email', 'test@example.com')->first()->uid);

        $this->post('/login/verify/resend')->assertRedirect(route('login.verify.show'));
        Mail::assertQueued(LoginVerificationCode::class, 2);

        $this->post('/login/verify/resend')->assertRedirect(route('login.verify.show'));
        Mail::assertQueued(LoginVerificationCode::class, 2);
    }

    #[Test]
    public function resend_shows_an_error_when_the_mail_send_fails(): void
    {
        $this->triggerVerification();

        RateLimiter::clear('login-verify-resend:'.User::where('email', 'test@example.com')->first()->uid);

        Mail::shouldReceive('to->send')->once()->andThrow(new \Exception('SMTP down'));

        $this->post('/login/verify/resend')
            ->assertRedirect(route('login.verify.show'))
            ->assertSessionHasErrors('code');

        $this->assertIsArray(session('login.pending_verification'));
    }
}
