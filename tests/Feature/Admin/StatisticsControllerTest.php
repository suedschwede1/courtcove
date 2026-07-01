<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StatisticsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function regular_member_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'enabled']))
            ->get('/admin/statistics')
            ->assertForbidden();
    }

    #[Test]
    public function admin_can_view_the_page(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/statistics')
            ->assertOk();
    }
}
