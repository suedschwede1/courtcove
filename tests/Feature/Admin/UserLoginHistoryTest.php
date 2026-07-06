<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserLoginHistoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function admin_can_see_a_users_login_history(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $user = User::factory()->create(['alias' => 'Jane Doe']);
        LoginHistory::record((int) $user->uid, '203.0.113.5', 'AT', 'success');

        $this->actingAs($admin)
            ->get(route('admin.users.login-history', $user))
            ->assertOk()
            ->assertSee('Jane Doe')
            ->assertSee('203.0.113.5')
            ->assertSee('AT');
    }

    #[Test]
    public function non_admin_cannot_see_the_login_history(): void
    {
        $user = User::factory()->create(['status' => 'enabled']);
        $other = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.users.login-history', $other))
            ->assertForbidden();
    }

    #[Test]
    public function empty_login_history_shows_the_empty_state_text(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.login-history', $user))
            ->assertOk()
            ->assertSee(__('booking.admin.users.login_history_empty'));
    }

    #[Test]
    public function it_shows_the_risk_score_and_which_flags_fired(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $user = User::factory()->create();
        LoginHistory::record((int) $user->uid, '203.0.113.5', 'RU', 'success', ['unusual_country', 'impossible_travel']);

        $response = $this->actingAs($admin)->get(route('admin.users.login-history', $user));

        $response->assertOk()
            ->assertSee(__('booking.admin.users.login_history_risk_score'))
            ->assertSee('2')
            ->assertSee(__('booking.admin.users.login_history_flag_unusual_country'))
            ->assertSee(__('booking.admin.users.login_history_flag_impossible_travel'));
    }
}
