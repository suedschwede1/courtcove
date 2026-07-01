<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LocaleControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function switching_to_a_valid_locale_sets_the_cookie_and_redirects_back(): void
    {
        $this->from('/calendar')
            ->get('/lang/en')
            ->assertRedirect('/calendar')
            ->assertPlainCookie('locale', 'en');
    }

    #[Test]
    public function switching_to_an_invalid_locale_returns_404(): void
    {
        $this->get('/lang/fr')->assertNotFound();
    }

    #[Test]
    public function authenticated_user_switch_is_persisted_to_meta(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/calendar')->get('/lang/en');

        $this->assertSame('en', $user->getMeta('locale'));
    }
}
