<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginHistoryControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function admin_can_see_login_history_across_all_users(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $user = User::factory()->create(['alias' => 'Jane Doe']);
        LoginHistory::record((int) $user->uid, '203.0.113.5', 'AT', 'success');

        $this->actingAs($admin)
            ->get(route('admin.login-history.index'))
            ->assertOk()
            ->assertSee('Jane Doe')
            ->assertSee('203.0.113.5');
    }

    #[Test]
    public function non_admin_cannot_see_login_history(): void
    {
        $user = User::factory()->create(['status' => 'enabled']);

        $this->actingAs($user)
            ->get(route('admin.login-history.index'))
            ->assertForbidden();
    }

    #[Test]
    public function it_filters_by_user_name(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $jane = User::factory()->create(['alias' => 'Jane Doe']);
        $bob = User::factory()->create(['alias' => 'Bob Smith']);
        LoginHistory::record((int) $jane->uid, '1.1.1.1', 'AT', 'success');
        LoginHistory::record((int) $bob->uid, '2.2.2.2', 'AT', 'success');

        $this->actingAs($admin)
            ->get(route('admin.login-history.index', ['user' => 'Jane']))
            ->assertOk()
            ->assertSee('Jane Doe')
            ->assertDontSee('Bob Smith');
    }

    #[Test]
    public function it_filters_by_status(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $user = User::factory()->create(['alias' => 'Jane Doe']);
        LoginHistory::record((int) $user->uid, '1.1.1.1', 'AT', 'success');
        LoginHistory::record((int) $user->uid, '1.1.1.1', 'AT', 'failed');

        $response = $this->actingAs($admin)
            ->get(route('admin.login-history.index', ['status' => 'failed']));

        $response->assertOk();
        $response->assertSee(__('booking.admin.users.login_history_status_failed'));
    }

    #[Test]
    public function it_filters_by_date_range(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $user = User::factory()->create(['alias' => 'Jane Doe']);

        Carbon::setTestNow('2026-01-01 12:00:00');
        LoginHistory::record((int) $user->uid, '1.1.1.1', 'AT', 'success');

        Carbon::setTestNow('2026-01-10 12:00:00');
        LoginHistory::record((int) $user->uid, '2.2.2.2', 'AT', 'success');
        Carbon::setTestNow();

        $response = $this->actingAs($admin)
            ->get(route('admin.login-history.index', ['date_from' => '2026-01-05', 'date_to' => '2026-01-15']));

        $response->assertOk()
            ->assertSee('2.2.2.2')
            ->assertDontSee('1.1.1.1');
    }
}
