<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Members\Models\Member;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The user edit page names the linked member ("Mitglied: Nr. …"); the number links to the member for members admins. */
final class UserEditLinkedMemberTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function a_linked_member_is_shown_with_a_link_to_the_member_page(): void
    {
        $user = User::factory()->create(['status' => 'enabled']);
        $member = Member::factory()->create(['member_number' => 1001, 'user_id' => $user->uid]);

        $this->actingAs(User::factory()->create(['status' => 'admin']))
            ->withUnencryptedCookie('locale', 'de')
            ->get(route('admin.users.edit', $user))
            ->assertOk()
            ->assertSeeText('Mitglied:')
            ->assertSee('href="'.route('members.edit', $member).'"', false)
            ->assertSeeText('Nr. 1001');
    }

    #[Test]
    public function an_unlinked_user_shows_no_member_line(): void
    {
        $user = User::factory()->create(['status' => 'enabled']);
        Member::factory()->create(['member_number' => 1002, 'user_id' => null]);

        $this->actingAs(User::factory()->create(['status' => 'admin']))
            ->withUnencryptedCookie('locale', 'de')
            ->get(route('admin.users.edit', $user))
            ->assertOk()
            ->assertDontSeeText('Mitglied:')
            ->assertDontSeeText('Nr. 1002');
    }

    #[Test]
    public function without_the_members_privilege_the_number_is_shown_without_a_link(): void
    {
        $user = User::factory()->create(['status' => 'enabled']);
        $member = Member::factory()->create(['member_number' => 1003, 'user_id' => $user->uid]);
        $assist = User::factory()->create(['status' => 'assist']);
        $assist->syncPrivileges(['admin.see-menu', 'admin.user']);

        $this->actingAs($assist)
            ->withUnencryptedCookie('locale', 'en')
            ->get(route('admin.users.edit', $user))
            ->assertOk()
            ->assertSeeText('Member:')
            ->assertSeeText('No. 1003')
            ->assertDontSee('href="'.route('members.edit', $member).'"', false);
    }
}
