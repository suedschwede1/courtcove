<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Mail\MemberBulkMail;
use App\Models\User;
use App\Modules\Members\Models\ClubRole;
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
    public function an_invalid_audience_fails_validation(): void
    {
        $this->actingAs($this->admin())->post('/members/mail/send', [
            'audience' => 'everyone',
            'subject' => 'x',
            'body' => 'y',
        ])->assertSessionHasErrors('audience');
    }
}
