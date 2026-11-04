<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegacyRedirectTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function redirects(): array
    {
        return [
            'user bookings' => ['/user/bookings', 'account.bookings'],
            'user settings' => ['/user/settings', 'account.edit'],
            'user registration' => ['/user/registration', 'register'],
            'user activation' => ['/user/activation', 'login'],
            'user activation token' => ['/user/activation/abc123', 'login'],
            'square' => ['/square', 'calendar.index'],
            'square deep' => ['/square/booking/customization', 'calendar.index'],
            'backend' => ['/backend', 'admin.dashboard'],
            'backend deep' => ['/backend/config/behavior', 'admin.dashboard'],
        ];
    }

    #[Test]
    #[DataProvider('redirects')]
    public function legacy_url_redirects_temporarily_for_guests(string $path, string $routeName): void
    {
        $this->get($path)->assertStatus(302)->assertRedirect(route($routeName));
    }

    #[Test]
    #[DataProvider('redirects')]
    public function legacy_url_redirects_temporarily_for_authenticated_users(string $path, string $routeName): void
    {
        $this->actingAs(User::factory()->create())
            ->get($path)
            ->assertStatus(302)
            ->assertRedirect(route($routeName));
    }

    #[Test]
    #[DataProvider('zf2ServedPaths')]
    public function zf2_served_user_paths_have_no_laravel_route(string $path): void
    {
        // public/.htaccess sends these to ZF2 before Laravel; Laravel itself must not shadow them.
        $this->get($path)->assertNotFound();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function zf2ServedPaths(): array
    {
        return [
            'login' => ['/user/login'],
            'logout' => ['/user/logout'],
            'password' => ['/user/password'],
            'password reset' => ['/user/password-reset'],
        ];
    }

    #[Test]
    public function wildcards_do_not_shadow_existing_routes(): void
    {
        $this->get('/calendar')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/admin')->assertRedirect(route('login'));
    }
}
