<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberChange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberChangeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function changed_fields_returns_only_differing_keys_and_ignores_timestamps(): void
    {
        $member = Member::factory()->create();
        $admin = User::factory()->create(['status' => 'admin']);

        $change = MemberChange::create([
            'member_id' => $member->id,
            'admin_user_id' => $admin->uid,
            'action' => 'updated',
            'before' => ['id' => $member->id, 'firstname' => 'Alt', 'lastname' => 'Gleich', 'updated_at' => 'y'],
            'after' => ['id' => $member->id, 'firstname' => 'Neu', 'lastname' => 'Gleich', 'updated_at' => 'z'],
        ]);

        $this->assertSame(
            [['field' => 'firstname', 'old' => 'Alt', 'new' => 'Neu']],
            $change->changedFields(),
        );
    }

    #[Test]
    public function changed_fields_is_empty_when_before_is_null(): void
    {
        $member = Member::factory()->create();
        $admin = User::factory()->create(['status' => 'admin']);

        $change = MemberChange::create([
            'member_id' => $member->id,
            'admin_user_id' => $admin->uid,
            'action' => 'created',
            'before' => null,
            'after' => ['firstname' => 'Neu'],
        ]);

        $this->assertSame([], $change->changedFields());
    }
}
