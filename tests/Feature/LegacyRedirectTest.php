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
            'user login' => ['/user/login', 'login'],
            'user logout' => ['/user/logout', 'calendar.index'],
            'user bookings' => ['/user/bookings', 'account.bookings'],
            'user settings' => ['/user/settings', 'account.edit'],
            'square' => ['/square', 'calendar.index'],
            'square deep' => ['/square/booking/customization', 'calendar.index'],
            'backend' => ['/backend', 'admin.dashboard'],
            'backend deep' => ['/backend/config/behavior', 'admin.dashboard'],
        ];
    }

    #[Test]
    #[DataProvider('redirects')]
    public function legacy_url_redirects_permanently_for_guests(string $path, string $routeName): void
    {
        $this->get($path)->assertStatus(301)->assertRedirect(route($routeName));
    }

    #[Test]
    #[DataProvider('redirects')]
    public function legacy_url_redirects_permanently_for_authenticated_users(string $path, string $routeName): void
    {
        $this->actingAs(User::factory()->create())
            ->get($path)
            ->assertStatus(301)
            ->assertRedirect(route($routeName));
    }

    #[Test]
    public function legacy_logout_does_not_log_the_user_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/user/logout')->assertStatus(301);

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function wildcards_do_not_shadow_existing_routes(): void
    {
        $this->get('/calendar')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/admin')->assertRedirect(route('login'));
    }
}
