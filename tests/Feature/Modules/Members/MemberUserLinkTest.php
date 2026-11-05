<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Mail\MemberBulkMail;
use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberChange;
use App\Modules\Members\Models\MemberDue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Members and users are different things: the link members.user_id is optional, nothing requires
 * it and no member feature (list, dues, mail, export) depends on it.
 */
class MemberUserLinkTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function a_member_is_created_and_updated_without_a_user_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/members', [
            'member_number' => 501,
            'firstname' => 'Erika',
            'lastname' => 'Beispiel',
            'joined_at' => '2026-01-01',
        ])->assertRedirect(route('members.index'));

        $member = Member::query()->where('member_number', 501)->firstOrFail();
        $this->assertNull($member->user_id);

        $this->actingAs($admin)->put(route('members.update', $member), [
            'member_number' => 501,
            'firstname' => 'Erika',
            'lastname' => 'Muster',
            'joined_at' => '2026-01-01',
        ])->assertRedirect(route('members.index'));

        $this->assertNull($member->fresh()->user_id);
        $this->assertSame('Muster', $member->fresh()->lastname);
    }

    #[Test]
    public function unlinking_clears_user_id_and_writes_a_member_change(): void
    {
        $user = User::factory()->create(['alias' => 'Konto Eins']);
        $member = Member::factory()->create(['user_id' => $user->uid]);

        $this->actingAs($this->admin())
            ->patchJson(route('members.update-field', $member), ['field' => 'user_id', 'value' => null])
            ->assertOk()
            ->assertJson(['value' => '–']);

        $this->assertNull($member->fresh()->user_id);
        $this->assertNotNull(User::find($user->uid), 'the user account itself stays');

        $change = MemberChange::query()->where('member_id', $member->id)->latest('id')->firstOrFail();
        $this->assertSame('updated', $change->action);
        $this->assertContains(['field' => 'user_id', 'old' => $user->uid, 'new' => null], $change->changedFields());
    }

    #[Test]
    public function the_field_update_cannot_link_a_user_that_is_already_linked(): void
    {
        $user = User::factory()->create();
        Member::factory()->create(['user_id' => $user->uid]);
        $other = Member::factory()->create();

        $this->actingAs($this->admin())
            ->patchJson(route('members.update-field', $other), ['field' => 'user_id', 'value' => $user->uid])
            ->assertStatus(422);

        $this->assertNull($other->fresh()->user_id);
    }

    #[Test]
    public function the_list_shows_the_optional_account_column(): void
    {
        $user = User::factory()->create();
        $linked = Member::factory()->create(['lastname' => 'Verbunden', 'user_id' => $user->uid]);
        Member::factory()->create(['lastname' => 'Ohnekonto', 'user_id' => null]);

        $html = (string) $this->actingAs($this->admin())->get(route('members.index'))->assertOk()->getContent();

        $this->assertStringContainsString(e(__('members::admin.members.user_account')), $html);
        $this->assertMatchesRegularExpression('#<span class="text-ink-3[^"]*">\('.preg_quote(e(__('members::admin.members.optional')), '#').'\)</span>#', $html);
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('admin.users.edit', $user), '#').'"[^>]*>'.preg_quote(e(__('members::admin.members.linked')), '#').'</a>#', $html);
        $this->assertMatchesRegularExpression('#data-label="'.preg_quote(e(__('members::admin.members.user_account')), '#').'">\s*–\s*</td>#', $html);
        $this->assertStringNotContainsString(e(__('members::admin.members.booking_user_short')).'</th>', $html);
        $this->assertNotNull($linked);
    }

    #[Test]
    public function without_the_user_privilege_the_list_does_not_link_to_the_admin_user_page(): void
    {
        $user = User::factory()->create();
        Member::factory()->create(['user_id' => $user->uid]);
        $assist = User::factory()->create(['status' => 'assist']);
        $assist->syncPrivileges(['admin.members']);

        $html = (string) $this->actingAs($assist->fresh())->get(route('members.index'))->assertOk()->getContent();

        $this->assertStringContainsString(e(__('members::admin.members.linked')), $html);
        $this->assertStringNotContainsString(route('admin.users.edit', $user), $html);
    }

    #[Test]
    public function the_detail_page_has_an_optional_account_card(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['alias' => 'Konto Zwei', 'email' => 'konto@example.com']);
        $linked = Member::factory()->create(['user_id' => $user->uid]);

        $html = (string) $this->actingAs($admin)->get(route('members.edit', $linked))->assertOk()->getContent();
        $this->assertStringContainsString(e(__('members::admin.members.user_account_card')), $html);
        $this->assertStringContainsString(e(__('members::admin.members.user_account_help')), $html);
        $this->assertStringContainsString('Konto Zwei', $html);
        $this->assertStringContainsString('konto@example.com', $html);
        $this->assertStringContainsString(e(__('members::admin.members.unlink')), $html);
        // The picker in the side card still posts with the main form.
        $this->assertMatchesRegularExpression('#<input type="hidden" name="user_id" form="member-edit-form"#', $html);
        $this->assertSame(1, substr_count($html, 'name="user_id"'));

        $html = (string) $this->actingAs($admin)->get(route('members.edit', Member::factory()->create()))->assertOk()->getContent();
        $this->assertStringContainsString(e(__('members::admin.members.link_user')), $html);
        $this->assertSame(1, substr_count($html, 'name="user_id"'));

        $html = (string) $this->actingAs($admin)->get(route('members.create'))->assertOk()->getContent();
        $this->assertStringContainsString(e(__('members::admin.members.user_account_help')), $html);
        $this->assertSame(1, substr_count($html, 'name="user_id"'));
    }

    #[Test]
    public function mail_export_and_dues_include_members_without_an_account(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        Member::factory()->create(['status' => 'aktiv', 'email' => 'linked@example.com', 'lastname' => 'Verbunden', 'user_id' => $user->uid]);
        Member::factory()->create(['status' => 'aktiv', 'email' => 'unlinked@example.com', 'lastname' => 'Ohnekonto', 'user_id' => null]);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/members/mail/send', ['audience' => 'all', 'subject' => 'Hallo', 'body' => 'Text'])
            ->assertRedirect(route('members.mail.index'));
        Mail::assertQueued(MemberBulkMail::class, function (MemberBulkMail $mail) {
            $addresses = collect($mail->to)->merge($mail->bcc)->pluck('address');

            return $addresses->contains('linked@example.com') && $addresses->contains('unlinked@example.com');
        });

        $csv = $this->actingAs($admin)->get(route('members.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Ohnekonto', $csv);
        $this->assertStringContainsString('Verbunden', $csv);

        // Dues generation takes last year's category of every active member, linked or not.
        $category = FeeCategory::create(['name' => 'Erwachsene']);
        $lastYear = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);
        $year = FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);
        FeeCategoryRate::create(['fee_category_id' => $category->id, 'fiscal_year_id' => $year->id, 'amount' => 100]);
        foreach (Member::all() as $member) {
            MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $lastYear->id, 'fee_category_id' => $category->id, 'amount' => 90]);
        }

        $this->actingAs($admin)->post(route('members.dues.generate'), ['fiscal_year_id' => $year->id])->assertRedirect();
        $this->assertSame(2, MemberDue::query()->where('fiscal_year_id', $year->id)->count());
    }
}
