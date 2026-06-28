<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\UserMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_do_everything(): void
    {
        $user = User::factory()->create(['status' => 'admin']);

        $this->assertTrue($user->can('calendar.see-data'));
        $this->assertTrue($user->can('admin.user'));
        $this->assertTrue($user->can('anything.at.all'));
    }

    #[Test]
    public function assist_user_holds_only_granted_privileges(): void
    {
        $user = User::factory()->create(['status' => 'assist']);
        UserMeta::create(['uid' => $user->uid, 'key' => 'allow.calendar.see-data', 'value' => 'true']);

        $this->assertTrue($user->can('calendar.see-data'));
        $this->assertFalse($user->can('calendar.create-single-bookings'));
    }

    #[Test]
    public function assist_privilege_string_supports_or_and_and(): void
    {
        $user = User::factory()->create(['status' => 'assist']);
        UserMeta::create(['uid' => $user->uid, 'key' => 'allow.calendar.see-data', 'value' => 'true']);

        // OR: matches because see-data is granted
        $this->assertTrue($user->can('calendar.see-past, calendar.see-data'));
        // AND: fails because see-past is not granted
        $this->assertFalse($user->can('calendar.see-data+calendar.see-past'));
    }

    #[Test]
    public function enabled_user_has_no_privileges(): void
    {
        $user = User::factory()->create(['status' => 'enabled']);

        $this->assertFalse($user->can('calendar.see-data'));
        $this->assertFalse($user->can('calendar.create-single-bookings'));
    }

    #[Test]
    public function password_is_hidden_from_serialization(): void
    {
        $user = User::factory()->create();
        $this->assertArrayNotHasKey('pw', $user->toArray());
    }

    #[Test]
    public function get_meta_reads_profile_fields(): void
    {
        $user = User::factory()->create();
        UserMeta::create(['uid' => $user->uid, 'key' => 'firstname', 'value' => 'Max']);

        $this->assertEquals('Max', $user->getMeta('firstname'));
        $this->assertNull($user->getMeta('missing'));
    }

    #[Test]
    public function privileges_constant_lists_known_abilities(): void
    {
        $this->assertContains('admin.user', \App\Models\User::PRIVILEGES);
        $this->assertContains('calendar.see-data', \App\Models\User::PRIVILEGES);
        $this->assertContains('admin.see-menu', \App\Models\User::PRIVILEGES);
    }

    #[Test]
    public function sync_privileges_writes_and_removes_allow_meta(): void
    {
        $user = User::factory()->create(['status' => 'assist']);
        $user->syncPrivileges(['admin.user', 'calendar.see-data']);

        $this->assertTrue($user->can('admin.user'));
        $this->assertTrue($user->can('calendar.see-data'));

        $user->syncPrivileges(['admin.user']); // remove see-data
        $this->assertTrue($user->can('admin.user'));
        $this->assertFalse($user->fresh()->can('calendar.see-data'));
    }

    #[Test]
    public function set_meta_upserts_value(): void
    {
        $user = User::factory()->create();
        $user->setMeta('firstname', 'Max');
        $this->assertEquals('Max', $user->getMeta('firstname'));
        $user->setMeta('firstname', 'Karl');
        $this->assertEquals('Karl', $user->fresh()->getMeta('firstname'));
        $this->assertEquals(1, $user->meta()->where('key', 'firstname')->count());
    }

    #[Test]
    public function granted_privileges_returns_slugs(): void
    {
        $user = User::factory()->create(['status' => 'assist']);
        $user->syncPrivileges(['admin.user', 'admin.event']);
        $granted = $user->grantedPrivileges();
        sort($granted);
        $this->assertSame(['admin.event', 'admin.user'], $granted);
    }
}
