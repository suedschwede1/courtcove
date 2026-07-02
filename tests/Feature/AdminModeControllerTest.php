<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminModeControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_switch_admin_mode_on(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);

        $this->actingAs($admin)->from('/calendar')
            ->get('/admin-mode/on')
            ->assertRedirect('/calendar')
            ->assertPlainCookie('admin_mode', '1');
    }

    #[Test]
    public function admin_can_switch_admin_mode_off(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);

        $this->actingAs($admin)->from('/calendar')
            ->get('/admin-mode/off')
            ->assertRedirect('/calendar')
            ->assertPlainCookie('admin_mode', '0');
    }

    #[Test]
    public function invalid_state_returns_404(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);

        $this->actingAs($admin)->get('/admin-mode/maybe')->assertNotFound();
    }

    #[Test]
    public function non_admin_cannot_switch_admin_mode(): void
    {
        $member = User::factory()->create(['status' => 'enabled']);

        $this->actingAs($member)->get('/admin-mode/on')->assertForbidden();
    }

    #[Test]
    public function guest_is_redirected_to_login(): void
    {
        $this->get('/admin-mode/on')->assertRedirect('/login');
    }
}
