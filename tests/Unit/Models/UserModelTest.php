<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function has_role_returns_true_for_existing_role(): void
    {
        $user = User::factory()->create(['roles' => 'admin member']);
        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('member'));
    }

    #[Test]
    public function has_role_returns_false_for_missing_role(): void
    {
        $user = User::factory()->create(['roles' => 'member']);
        $this->assertFalse($user->hasRole('admin'));
    }

    #[Test]
    public function has_permission_returns_true_when_granted(): void
    {
        $user = User::factory()->create(['permissions' => 'calendar.see-data calendar.create-single-bookings']);
        $this->assertTrue($user->hasPermission('calendar.see-data'));
        $this->assertTrue($user->hasPermission('calendar.create-single-bookings'));
    }

    #[Test]
    public function has_permission_returns_false_when_missing(): void
    {
        $user = User::factory()->create(['permissions' => 'calendar.see-data']);
        $this->assertFalse($user->hasPermission('calendar.create-single-bookings'));
    }

    #[Test]
    public function password_and_token_are_hidden_from_serialization(): void
    {
        $user = User::factory()->create();
        $arr  = $user->toArray();
        $this->assertArrayNotHasKey('password', $arr);
        $this->assertArrayNotHasKey('token', $arr);
    }
}
