<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class AdminModeControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function plainCookie(TestResponse $response): Cookie
    {
        $cookie = $response->getCookie('admin_mode', false);
        $this->assertNotNull($cookie, 'admin_mode cookie missing');

        return $cookie;
    }

    #[Test]
    public function admin_can_switch_admin_mode_on_with_a_session_cookie(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);

        $response = $this->actingAsWithoutAdminMode($admin)
            ->post('/admin-mode/on', ['redirect' => '/calendar?date=2026-11-06'])
            ->assertRedirect('/calendar?date=2026-11-06')
            ->assertPlainCookie('admin_mode', '1');

        $cookie = $this->plainCookie($response);
        $this->assertSame(0, $cookie->getExpiresTime(), 'admin_mode must be a browser-session cookie (no Expires/Max-Age)');
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertSame((bool) config('session.secure'), $cookie->isSecure());
        $this->assertSame(config('session.path'), $cookie->getPath());
    }

    #[Test]
    public function the_cookie_takes_path_secure_and_samesite_from_the_session_config(): void
    {
        config(['session.path' => '/booking', 'session.secure' => true, 'session.same_site' => 'lax']);
        $this->app->forgetInstance('cookie');
        $admin = User::factory()->create(['status' => 'admin']);

        $cookie = $this->plainCookie($this->actingAs($admin)->post('/admin-mode/on', ['redirect' => '/admin']));

        $this->assertSame('/booking', $cookie->getPath());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertSame(0, $cookie->getExpiresTime());

        $forget = $this->plainCookie($this->actingAs($admin)->post('/admin-mode/off', ['redirect' => '/calendar']));
        $this->assertSame('/booking', $forget->getPath(), 'off must delete the cookie on the same path');
    }

    #[Test]
    public function admin_can_switch_admin_mode_off_which_deletes_the_cookie(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);

        $this->actingAsAdminMode($admin)
            ->post('/admin-mode/off', ['redirect' => '/calendar'])
            ->assertRedirect('/calendar')
            ->assertCookieExpired('admin_mode');
    }

    #[Test]
    public function an_assist_with_any_privilege_may_switch_and_one_without_may_not(): void
    {
        $assist = User::factory()->create(['status' => 'assist']);
        $assist->syncPrivileges(['admin.members']);
        $this->actingAs($assist)->post('/admin-mode/on', ['redirect' => '/members'])->assertRedirect('/members');

        $bare = User::factory()->create(['status' => 'assist']);
        $this->actingAs($bare)->post('/admin-mode/on')->assertForbidden();
    }

    #[Test]
    public function invalid_state_returns_404(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);

        $this->actingAs($admin)->post('/admin-mode/maybe')->assertNotFound();
    }

    #[Test]
    public function switching_needs_post_with_csrf_a_get_link_no_longer_switches(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);

        $this->actingAs($admin)->get('/admin-mode/on')->assertStatus(405);
    }

    #[Test]
    public function non_admin_cannot_switch_admin_mode(): void
    {
        $member = User::factory()->create(['status' => 'enabled']);

        $this->actingAs($member)->post('/admin-mode/on')->assertForbidden();
    }

    #[Test]
    public function guest_is_redirected_to_login(): void
    {
        $this->post('/admin-mode/on')->assertRedirect('/login');
    }

    /** @return array<string, array{mixed}> */
    public static function unsafeTargets(): array
    {
        return [
            'absolute url' => ['https://evil.example/admin'],
            'protocol relative' => ['//evil.example/admin'],
            'backslash host' => ['/\\evil.example'],
            'backslash inside' => ['/admin\\..\\x'],
            'no leading slash' => ['evil.example/admin'],
            'javascript scheme' => ['javascript:alert(1)'],
            'control character' => ["/admin\r\nLocation: https://evil.example"],
            'array input' => [['/admin']],
            'empty' => [''],
        ];
    }

    #[Test]
    #[DataProvider('unsafeTargets')]
    public function the_redirect_never_leaves_the_app(mixed $target): void
    {
        $admin = User::factory()->create(['status' => 'admin']);

        $response = $this->actingAs($admin)->post('/admin-mode/on', ['redirect' => $target]);

        $response->assertRedirect('/calendar');
        $this->assertStringStartsWith(url('/'), (string) $response->headers->get('Location'));
    }

    #[Test]
    public function logout_deletes_the_admin_mode_cookie(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);

        $this->actingAsAdminMode($admin)->post('/logout')
            ->assertRedirect('/login')
            ->assertCookieExpired('admin_mode');
    }

    #[Test]
    public function login_deletes_a_left_over_admin_mode_cookie(): void
    {
        $admin = User::factory()->create(['status' => 'admin', 'email' => 'admin@example.test', 'pw' => bcrypt('secret-pass')]);

        $this->withAdminMode()->post('/login', ['email' => 'admin@example.test', 'password' => 'secret-pass'])
            ->assertCookieExpired('admin_mode');
        $this->assertAuthenticatedAs($admin);
    }
}
