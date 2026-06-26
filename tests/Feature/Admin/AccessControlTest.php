<?php
declare(strict_types=1);
namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\UserMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function gate_allows_admin_everything(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $this->assertTrue(Gate::forUser($admin)->allows('admin.user'));
    }

    #[Test]
    public function gate_respects_assist_allow_flags(): void
    {
        $assist = User::factory()->create(['status' => 'assist']);
        UserMeta::create(['uid' => $assist->uid, 'key' => 'allow.admin.user', 'value' => 'true']);

        $this->assertTrue(Gate::forUser($assist)->allows('admin.user'));
        $this->assertFalse(Gate::forUser($assist)->allows('admin.event'));
    }

    #[Test]
    public function gate_denies_regular_member(): void
    {
        $user = User::factory()->create(['status' => 'enabled']);
        $this->assertFalse(Gate::forUser($user)->allows('admin.user'));
    }

    #[Test]
    public function admin_can_open_dashboard(): void
    {
        $admin = \App\Models\User::factory()->create(['status' => 'admin']);
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Administration');
    }

    #[Test]
    public function regular_member_is_forbidden_from_dashboard(): void
    {
        $user = \App\Models\User::factory()->create(['status' => 'enabled']);
        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    #[Test]
    public function guest_dashboard_redirects_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }
}
