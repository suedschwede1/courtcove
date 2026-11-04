<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\LoginHistory;
use App\Models\Option;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use PHPUnit\Framework\Attributes\Test;
use Stevebauman\Location\Facades\Location;
use Stevebauman\Location\LocationManager;
use Stevebauman\Location\Position;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertViewIs('auth.login');
    }

    #[Test]
    public function login_page_has_a_forgot_password_link(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee(route('password.forgot'), false);
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
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => __('booking.auth.invalid')]);

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

        $key = $this->emailIpThrottleKey('test@example.com');
        RateLimiter::clear($key);
        RateLimiter::clear($this->ipThrottleKey());
        RateLimiter::clear($this->accountThrottleKey(1, 'test@example.com'));

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
    public function successful_login_clears_rate_limit_counters(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'pw' => bcrypt('secret123'),
            'status' => 'enabled',
        ]);

        $emailIpKey = $this->emailIpThrottleKey('test@example.com');
        $ipKey = $this->ipThrottleKey();
        $accountKey = $this->accountThrottleKey((int) $user->uid, 'test@example.com');

        RateLimiter::clear($emailIpKey);
        RateLimiter::clear($ipKey);
        RateLimiter::clear($accountKey);

        $this->post('/login', ['email' => 'test@example.com', 'password' => 'wrong'])
            ->assertRedirect('/login');

        $this->post('/login', ['email' => 'test@example.com', 'password' => 'secret123'])
            ->assertRedirect('/calendar');

        Auth::logout();

        $this->assertSame(0, RateLimiter::attempts($emailIpKey));
        $this->assertSame(0, RateLimiter::attempts($ipKey));
        $this->assertSame(0, RateLimiter::attempts($accountKey));
    }

    #[Test]
    public function login_fails_with_unknown_email(): void
    {
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'any'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => __('booking.auth.invalid')]);

        $this->assertGuest();
    }

    #[Test]
    public function disabled_user_gets_the_same_error_message_as_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'pw' => bcrypt('secret123'),
            'status' => 'disabled',
        ]);

        $this->post('/login', ['email' => 'test@example.com', 'password' => 'secret123'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => __('booking.auth.invalid')]);

        $this->assertGuest();
    }

    #[Test]
    public function login_is_blocked_when_the_request_country_is_configured_as_blocked(): void
    {
        config(['booking.blocked_login_countries' => ['RU']]);
        Location::fake(['127.0.0.1' => Position::make(['countryCode' => 'RU'])]);

        User::factory()->create([
            'email' => 'test@example.com',
            'pw' => bcrypt('secret123'),
            'status' => 'enabled',
        ]);

        $this->post('/login', ['email' => 'test@example.com', 'password' => 'secret123'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => __('booking.auth.invalid')]);

        $this->assertGuest();
    }

    #[Test]
    public function login_succeeds_when_the_request_country_is_not_blocked(): void
    {
        config(['booking.blocked_login_countries' => ['RU']]);
        Location::fake(['127.0.0.1' => Position::make(['countryCode' => 'US'])]);

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
    public function login_is_not_blocked_when_the_location_lookup_cannot_resolve_a_country(): void
    {
        config(['booking.blocked_login_countries' => ['RU']]);
        Location::fake([]);

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
    public function login_blocked_by_country_does_not_count_against_the_rate_limiter(): void
    {
        config(['booking.blocked_login_countries' => ['RU']]);
        Location::fake(['127.0.0.1' => Position::make(['countryCode' => 'RU'])]);

        User::factory()->create([
            'email' => 'test@example.com',
            'pw' => bcrypt('secret123'),
            'status' => 'enabled',
        ]);

        $key = $this->emailIpThrottleKey('test@example.com');
        RateLimiter::clear($key);

        $this->post('/login', ['email' => 'test@example.com', 'password' => 'secret123'])
            ->assertRedirect('/login');

        $this->assertSame(0, RateLimiter::attempts($key));
    }

    #[Test]
    public function ip_rate_limit_applies_across_multiple_accounts(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->post('/login', ['email' => 'user'.$i.'@example.com', 'password' => 'wrong'])
                ->assertRedirect('/login');
        }

        $this->post('/login', ['email' => 'fresh@example.com', 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGreaterThan(0, RateLimiter::availableIn($this->ipThrottleKey()));
    }

    #[Test]
    public function privileged_users_do_not_get_a_remember_me_cookie(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'pw' => bcrypt('secret123'),
            'status' => 'admin',
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'secret123',
            'remember' => '1',
        ]);

        $response->assertRedirect('/calendar');
        $this->assertAuthenticated();

        $recallerCookies = array_filter(
            $response->headers->getCookies(),
            fn (Cookie $cookie): bool => $cookie->getName() === Auth::guard()->getRecallerName(),
        );

        $this->assertEmpty($recallerCookies);
    }

    #[Test]
    public function remember_me_login_for_a_normal_member_does_not_fail_without_a_remember_token_column(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
            'pw' => bcrypt('secret123'),
            'status' => 'enabled',
        ]);
        $before = $user->fresh()->getAttributes();

        $this->post('/login', [
            'email' => 'member@example.com',
            'password' => 'secret123',
            'remember' => '1',
        ])->assertRedirect('/calendar');

        $this->assertAuthenticated();

        // bs_users (legacy table) has no remember_token column; nothing but the existing
        // login bookkeeping columns may change.
        $after = $user->fresh()->getAttributes();
        $changed = array_keys(array_diff_assoc($after, $before));
        $bookkeeping = ['login_attempts', 'login_detent', 'last_activity', 'last_ip'];

        $this->assertSame([], array_values(array_diff($changed, $bookkeeping)), 'unexpected columns changed on login');
        $this->assertArrayNotHasKey('remember_token', $after);
    }

    #[Test]
    public function successful_login_updates_last_activity_and_last_ip(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'pw' => bcrypt('secret123'),
            'status' => 'enabled',
            'last_ip' => null,
            'last_activity' => null,
        ]);

        $this->post('/login', ['email' => 'test@example.com', 'password' => 'secret123'])
            ->assertRedirect('/calendar');

        $user->refresh();

        $this->assertSame('127.0.0.1', $user->last_ip);
        $this->assertNotNull($user->last_activity);
        $this->assertSame(0, (int) $user->login_attempts);
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

    #[Test]
    public function login_page_reflects_the_active_locale(): void
    {
        $this->withUnencryptedCookie('locale', 'en')
            ->get('/login')
            ->assertSee('lang="en"', false);
    }

    private function emailIpThrottleKey(string $email): string
    {
        return 'login:email-ip:'.strtolower(trim($email)).'|127.0.0.1';
    }

    private function ipThrottleKey(): string
    {
        return 'login:ip:127.0.0.1';
    }

    private function accountThrottleKey(int $userId, string $email): string
    {
        return 'login:account:'.($userId > 0 ? (string) $userId : strtolower(trim($email)));
    }

    #[Test]
    public function an_ordinary_login_does_not_require_verification(): void
    {
        Location::fake(['127.0.0.1' => Position::make(['countryCode' => 'AT'])]);
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
    public function a_login_from_an_unusual_country_requires_verification(): void
    {
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

        $this->assertGuest();
        $this->assertNotNull(Session::get('login.pending_verification'));
    }

    #[Test]
    public function two_recent_failed_attempts_followed_by_success_require_verification(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'pw' => bcrypt('secret123'),
            'status' => 'enabled',
        ]);

        $this->post('/login', ['email' => 'test@example.com', 'password' => 'wrong']);
        $this->post('/login', ['email' => 'test@example.com', 'password' => 'wrong']);

        $this->post('/login', ['email' => 'test@example.com', 'password' => 'secret123'])
            ->assertRedirect(route('login.verify.show'));

        $this->assertGuest();
    }

    #[Test]
    public function a_first_ever_login_never_requires_verification_on_country_grounds(): void
    {
        Location::fake(['127.0.0.1' => Position::make(['countryCode' => 'RU'])]);
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
    public function login_history_records_a_successful_attempt(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'pw' => bcrypt('secret123'),
            'status' => 'enabled',
        ]);

        $this->post('/login', ['email' => 'test@example.com', 'password' => 'secret123']);

        $this->assertDatabaseHas('login_history', [
            'user_id' => $user->uid,
            'status' => 'success',
        ]);
    }

    #[Test]
    public function risky_login_history_is_not_recorded_as_success_before_verification(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'pw' => bcrypt('secret123'),
            'status' => 'enabled',
        ]);

        Carbon::setTestNow('2026-01-01 10:00:00');
        Location::fake(['127.0.0.1' => Position::make(['countryCode' => 'AT'])]);
        LoginHistory::record((int) $user->uid, '1.1.1.1', 'AT', 'success');

        Carbon::setTestNow('2026-01-01 12:00:00');
        app()->forgetInstance(LocationManager::class);
        Location::clearResolvedInstance(LocationManager::class);
        Location::fake(['127.0.0.1' => Position::make(['countryCode' => 'RU'])]);
        Mail::fake();

        $this->post('/login', ['email' => 'test@example.com', 'password' => 'secret123'])
            ->assertRedirect(route('login.verify.show'));

        $this->assertDatabaseMissing('login_history', [
            'user_id' => $user->uid,
            'country_code' => 'RU',
            'status' => 'success',
            'risk_flags' => 'unusual_country,impossible_travel',
        ]);

        Carbon::setTestNow();
    }

    #[Test]
    public function login_history_records_a_failed_attempt_for_a_known_user(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'pw' => bcrypt('secret123'),
            'status' => 'enabled',
        ]);

        $this->post('/login', ['email' => 'test@example.com', 'password' => 'wrong']);

        $this->assertDatabaseHas('login_history', [
            'user_id' => $user->uid,
            'status' => 'failed',
        ]);
    }

    #[Test]
    public function login_history_does_not_record_an_unknown_email(): void
    {
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong']);

        $this->assertDatabaseCount('login_history', 0);
    }

    #[Test]
    public function a_risky_login_with_a_failed_mail_send_shows_an_error_but_keeps_pending_state(): void
    {
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

        Mail::shouldReceive('to->send')->once()->andThrow(new \Exception('SMTP down'));

        $this->post('/login', ['email' => 'test@example.com', 'password' => 'secret123'])
            ->assertRedirect(route('login.verify.show'))
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }
}
