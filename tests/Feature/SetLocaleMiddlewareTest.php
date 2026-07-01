<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SetLocaleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function defaults_to_config_locale_with_no_cookie_or_user_preference(): void
    {
        $this->get('/calendar')->assertSee('lang="de"', false);
    }

    #[Test]
    public function cookie_overrides_the_default_locale(): void
    {
        $this->withUnencryptedCookie('locale', 'en')
            ->get('/calendar')
            ->assertSee('lang="en"', false);
    }

    #[Test]
    public function invalid_cookie_value_is_ignored(): void
    {
        $this->withUnencryptedCookie('locale', 'fr')
            ->get('/calendar')
            ->assertSee('lang="de"', false);
    }

    #[Test]
    public function authenticated_user_preference_overrides_the_cookie(): void
    {
        $user = User::factory()->create();
        $user->setMeta('locale', 'en');

        $this->actingAs($user)
            ->withUnencryptedCookie('locale', 'de')
            ->get('/calendar')
            ->assertSee('lang="en"', false);
    }
}
