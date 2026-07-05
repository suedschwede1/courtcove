<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Mail\MemberBulkMail;
use App\Models\Option;
use App\Models\User;
use App\Modules\Members\Models\ClubRole;
use App\Modules\Members\Models\MailCategory;
use App\Modules\Members\Models\Member;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailSendingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function sending_to_all_bccs_every_active_member_with_an_email(): void
    {
        Mail::fake();

        Member::factory()->create(['status' => 'aktiv', 'email' => 'a@example.com']);
        Member::factory()->create(['status' => 'aktiv', 'email' => 'b@example.com']);
        Member::factory()->create(['status' => 'ausgetreten', 'left_at' => '2025-01-01', 'email' => 'c@example.com']);
        Member::factory()->create(['status' => 'aktiv', 'email' => null]);

        $this->actingAs($this->admin())->post('/members/mail/send', [
            'audience' => 'all',
            'subject' => 'Hallo',
            'body' => 'Testtext',
        ])->assertRedirect(route('members.mail.index'));

        Mail::assertQueued(MemberBulkMail::class, function (MemberBulkMail $mail) {
            $bcc = collect($mail->to)->merge($mail->bcc)->pluck('address');

            return $bcc->contains('a@example.com')
                && $bcc->contains('b@example.com')
                && ! $bcc->contains('c@example.com');
        });
    }

    #[Test]
    public function sending_to_vorstand_only_bccs_members_with_a_board_role(): void
    {
        Mail::fake();

        $board = ClubRole::create(['name' => 'Obmann', 'is_board' => true]);
        ClubRole::create(['name' => 'Platzwart', 'is_board' => false]);

        $boardMember = Member::factory()->create(['status' => 'aktiv', 'email' => 'obmann@example.com']);
        $boardMember->clubRoles()->sync([$board->id]);
        Member::factory()->create(['status' => 'aktiv', 'email' => 'normal@example.com']);

        $this->actingAs($this->admin())->post('/members/mail/send', [
            'audience' => 'vorstand',
            'subject' => 'Vorstand',
            'body' => 'Text',
        ])->assertRedirect(route('members.mail.index'));

        Mail::assertQueued(MemberBulkMail::class, function (MemberBulkMail $mail) {
            $bcc = collect($mail->to)->merge($mail->bcc)->pluck('address');

            return $bcc->contains('obmann@example.com') && ! $bcc->contains('normal@example.com');
        });
    }

    #[Test]
    public function sending_to_ruhend_only_bccs_dormant_members(): void
    {
        Mail::fake();

        Member::factory()->create(['status' => 'ruhend', 'email' => 'ruhend@example.com']);
        Member::factory()->create(['status' => 'aktiv', 'email' => 'aktiv@example.com']);

        $this->actingAs($this->admin())->post('/members/mail/send', [
            'audience' => 'ruhend',
            'subject' => 'Ruhend',
            'body' => 'Text',
        ])->assertRedirect(route('members.mail.index'));

        Mail::assertQueued(MemberBulkMail::class, function (MemberBulkMail $mail) {
            $bcc = collect($mail->to)->merge($mail->bcc)->pluck('address');

            return $bcc->contains('ruhend@example.com') && ! $bcc->contains('aktiv@example.com');
        });
    }

    #[Test]
    public function sending_to_a_mail_category_only_bccs_members_with_a_role_in_that_category(): void
    {
        Mail::fake();

        $category = MailCategory::create(['name' => 'Finanzen']);
        $role = ClubRole::create(['name' => 'Kassier', 'is_board' => false, 'mail_category_id' => $category->id]);
        $matching = Member::factory()->create(['status' => 'aktiv', 'email' => 'kassier@example.com']);
        $matching->clubRoles()->sync([$role->id]);
        Member::factory()->create(['status' => 'aktiv', 'email' => 'other@example.com']);

        $this->actingAs($this->admin())->post('/members/mail/send', [
            'audience' => 'category:'.$category->id,
            'subject' => 'Funktion',
            'body' => 'Text',
        ])->assertRedirect(route('members.mail.index'));

        Mail::assertQueued(MemberBulkMail::class, function (MemberBulkMail $mail) {
            $bcc = collect($mail->to)->merge($mail->bcc)->pluck('address');

            return $bcc->contains('kassier@example.com') && ! $bcc->contains('other@example.com');
        });
    }

    #[Test]
    public function roles_sharing_the_same_mail_category_are_combined_into_one_audience(): void
    {
        Mail::fake();

        $category = MailCategory::create(['name' => 'Vorstand']);
        $chairman = ClubRole::create(['name' => 'Obmann', 'is_board' => true, 'mail_category_id' => $category->id]);
        $secretary = ClubRole::create(['name' => 'Schriftführer', 'is_board' => true, 'mail_category_id' => $category->id]);

        $member1 = Member::factory()->create(['status' => 'aktiv', 'email' => 'obmann@example.com']);
        $member1->clubRoles()->sync([$chairman->id]);
        $member2 = Member::factory()->create(['status' => 'aktiv', 'email' => 'schriftfuehrer@example.com']);
        $member2->clubRoles()->sync([$secretary->id]);
        Member::factory()->create(['status' => 'aktiv', 'email' => 'other@example.com']);

        $this->actingAs($this->admin())->post('/members/mail/send', [
            'audience' => 'category:'.$category->id,
            'subject' => 'Vorstand',
            'body' => 'Text',
        ])->assertRedirect(route('members.mail.index'));

        Mail::assertQueued(MemberBulkMail::class, function (MemberBulkMail $mail) {
            $bcc = collect($mail->to)->merge($mail->bcc)->pluck('address');

            return $bcc->contains('obmann@example.com')
                && $bcc->contains('schriftfuehrer@example.com')
                && ! $bcc->contains('other@example.com');
        });
    }

    #[Test]
    public function fee_category_audiences_are_not_available(): void
    {
        $this->actingAs($this->admin())->post('/members/mail/send', [
            'audience' => 'fee_category:1',
            'subject' => 'Kategorie',
            'body' => 'Text',
        ])->assertSessionHasErrors('audience');
    }

    #[Test]
    public function deleted_mail_categories_are_not_available_as_audience(): void
    {
        $category = MailCategory::create(['name' => 'Nicht Mehr']);
        $categoryId = $category->id;
        $category->delete();

        $this->actingAs($this->admin())->post('/members/mail/send', [
            'audience' => 'category:'.$categoryId,
            'subject' => 'Funktion',
            'body' => 'Text',
        ])->assertSessionHasErrors('audience');
    }

    #[Test]
    public function an_invalid_audience_fails_validation(): void
    {
        $this->actingAs($this->admin())->post('/members/mail/send', [
            'audience' => 'everyone',
            'subject' => 'x',
            'body' => 'y',
        ])->assertSessionHasErrors('audience');
    }

    #[Test]
    public function the_sender_settings_are_required(): void
    {
        $this->actingAs($this->admin())->post('/members/mail/sender', [
            'from_name' => '',
            'from_address' => 'not-an-email',
        ])->assertSessionHasErrors(['from_name', 'from_address']);
    }

    #[Test]
    public function admin_can_save_the_default_sender(): void
    {
        $this->actingAs($this->admin())->post('/members/mail/sender', [
            'from_name' => 'Mitgliederverwaltung',
            'from_address' => 'verein@example.com',
        ])->assertRedirect(route('members.mail.config'));

        $this->assertDatabaseHas('bs_options', ['key' => 'members.mail.from_name', 'value' => 'Mitgliederverwaltung']);
        $this->assertDatabaseHas('bs_options', ['key' => 'members.mail.from_address', 'value' => 'verein@example.com']);
    }

    #[Test]
    public function sending_uses_the_saved_sender(): void
    {
        Mail::fake();
        Option::create(['key' => 'members.mail.from_name', 'value' => 'Gespeicherter Absender', 'locale' => null]);
        Option::create(['key' => 'members.mail.from_address', 'value' => 'gespeichert@example.com', 'locale' => null]);
        Member::factory()->create(['status' => 'aktiv', 'email' => 'a@example.com']);

        $this->actingAs($this->admin())->post('/members/mail/send', [
            'audience' => 'all',
            'subject' => 'Hallo',
            'body' => 'Text',
        ])->assertRedirect(route('members.mail.index'));

        Mail::assertQueued(MemberBulkMail::class, function (MemberBulkMail $mail) {
            return $mail->from[0]['address'] === 'gespeichert@example.com'
                && $mail->from[0]['name'] === 'Gespeicherter Absender';
        });
    }

    #[Test]
    public function admin_can_send_a_test_mail_to_a_single_recipient(): void
    {
        Mail::fake();
        Option::create(['key' => 'members.mail.from_name', 'value' => 'Mitgliederverwaltung', 'locale' => null]);
        Option::create(['key' => 'members.mail.from_address', 'value' => 'verein@example.com', 'locale' => null]);

        $this->actingAs($this->admin())->post('/members/mail/test', [
            'test_email' => 'admin@example.com',
            'subject' => 'Probe',
            'body' => '**Hallo**',
        ])->assertRedirect(route('members.mail.index'));

        Mail::assertQueued(MemberBulkMail::class, function (MemberBulkMail $mail) {
            return collect($mail->to)->pluck('address')->contains('admin@example.com')
                && $mail->from[0]['address'] === 'verein@example.com';
        });
    }

    #[Test]
    public function config_page_shows_the_sender_settings(): void
    {
        Option::create(['key' => 'members.mail.from_name', 'value' => 'Mitgliederverwaltung', 'locale' => null]);
        Option::create(['key' => 'members.mail.from_address', 'value' => 'verein@example.com', 'locale' => null]);

        $this->actingAs($this->admin())->get('/members/mail/config')
            ->assertOk()
            ->assertSee('Mitgliederverwaltung')
            ->assertSee('verein@example.com');
    }

    #[Test]
    public function config_page_lists_mail_categories(): void
    {
        MailCategory::create(['name' => 'Vorstand']);

        $this->actingAs($this->admin())->get('/members/mail/config')
            ->assertOk()
            ->assertSee('Vorstand');
    }

    #[Test]
    public function admin_can_create_a_mail_category(): void
    {
        $this->actingAs($this->admin())->post('/members/mail/categories', [
            'name' => 'Vorstand',
        ])->assertRedirect(route('members.mail.config'));

        $this->assertDatabaseHas('mail_categories', ['name' => 'Vorstand']);
    }

    #[Test]
    public function creating_a_mail_category_with_a_duplicate_name_fails_validation(): void
    {
        MailCategory::create(['name' => 'Vorstand']);

        $this->actingAs($this->admin())->post('/members/mail/categories', [
            'name' => 'Vorstand',
        ])->assertSessionHasErrors('name');
    }

    #[Test]
    public function admin_can_rename_a_mail_category(): void
    {
        $category = MailCategory::create(['name' => 'Vorstand']);

        $this->actingAs($this->admin())->put("/members/mail/categories/{$category->id}", [
            'name' => 'Vereinsleitung',
        ])->assertRedirect(route('members.mail.config'));

        $this->assertSame('Vereinsleitung', $category->refresh()->name);
    }

    #[Test]
    public function deleting_a_mail_category_clears_it_from_club_roles(): void
    {
        $category = MailCategory::create(['name' => 'Vorstand']);
        $role = ClubRole::create(['name' => 'Obmann', 'is_board' => true, 'mail_category_id' => $category->id]);

        $this->actingAs($this->admin())->delete("/members/mail/categories/{$category->id}")
            ->assertRedirect(route('members.mail.config'));

        $this->assertDatabaseMissing('mail_categories', ['id' => $category->id]);
        $this->assertNull($role->refresh()->mail_category_id);
    }

    #[Test]
    public function sending_records_a_history_snapshot(): void
    {
        Mail::fake();
        $admin = $this->admin();
        Member::factory()->create(['status' => 'aktiv', 'email' => 'a@example.com']);

        $this->actingAs($admin)->post('/members/mail/send', [
            'audience' => 'all',
            'subject' => 'Historie',
            'body' => 'Snapshot',
        ])->assertRedirect(route('members.mail.index'));

        $this->assertDatabaseHas('mail_sends', [
            'sent_by_user_id' => $admin->uid,
            'audience_key' => 'all',
            'recipient_count' => 1,
            'subject' => 'Historie',
            'body' => 'Snapshot',
        ]);
    }

    #[Test]
    public function preview_renders_markdown_and_strips_html(): void
    {
        $this->actingAs($this->admin())->postJson('/members/mail/preview', [
            'body' => '**Hallo** <script>alert(1)</script>',
        ])->assertOk()
            ->assertJsonFragment(['html' => "<p><strong>Hallo</strong> alert(1)</p>\n"]);
    }

    #[Test]
    public function member_bulk_mail_renders_markdown_formatting(): void
    {
        $html = (new MemberBulkMail('Format', '**Hallo**'))->render();

        $this->assertStringContainsString('<strong>Hallo</strong>', $html);
    }
}
