<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberChange;
use App\Modules\Members\Models\MemberDue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberEditRestyleTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    private function render(Member $member, ?User $admin = null): string
    {
        return (string) $this->actingAs($admin ?? $this->admin())
            ->get(route('members.edit', $member))
            ->assertOk()
            ->getContent();
    }

    private function memberWithDue(?float $teilbetrag = null): Member
    {
        $category = FeeCategory::create(['name' => 'Vollzahler']);
        $member = Member::factory()->create(['firstname' => 'Anna', 'lastname' => 'Muster', 'fee_category_id' => $category->id]);
        $fiscalYear = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);
        MemberDue::create([
            'member_id' => $member->id,
            'fiscal_year_id' => $fiscalYear->id,
            'fee_category_id' => $category->id,
            'amount' => 120,
            'teilbetrag' => $teilbetrag,
        ]);

        return $member;
    }

    #[Test]
    public function save_button_submits_the_member_form(): void
    {
        $member = $this->memberWithDue();

        $html = $this->render($member);

        $this->assertMatchesRegularExpression('/<button type="submit"[^>]*form="member-edit-form"/', $html);
        $this->assertMatchesRegularExpression(
            '/<form id="member-edit-form"[^>]*action="'.preg_quote(route('members.update', $member), '/').'"/',
            $html,
        );
        $this->assertStringContainsString('mbr-page-header__actions', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    #[Test]
    public function anonymize_stays_behind_the_prompt(): void
    {
        $member = Member::factory()->create(['left_at' => now()->subYears(4)->toDateString()]);

        $html = $this->render($member);

        $this->assertMatchesRegularExpression(
            '/<form method="POST" action="'.preg_quote(route('members.anonymize', $member), '/').'" onsubmit="[^"]*prompt\(/',
            $html,
        );
        $this->assertStringContainsString('<input type="hidden" name="confirmation" value="">', $html);
        $this->assertStringContainsString('ds-btn--danger', $html);
        $this->assertStringContainsString('ds-notice--attention', $html);
    }

    #[Test]
    public function history_line_uses_the_translated_change_pattern(): void
    {
        app()->setLocale('de');
        $admin = $this->admin();
        $member = Member::factory()->create(['firstname' => 'Alt']);
        MemberChange::create([
            'member_id' => $member->id,
            'admin_user_id' => $admin->uid,
            'action' => 'updated',
            'before' => ['firstname' => 'Alt'],
            'after' => ['firstname' => 'Neu'],
        ]);

        $html = $this->render($member, $admin);

        $this->assertStringContainsString('Vorname: Alt → Neu', $html);
        $this->assertStringNotContainsString(
            '→',
            (string) file_get_contents(app_path('Modules/Members/resources/views/members/_history.blade.php')),
        );
        $this->assertStringContainsString('border-t border-line', $html);
    }

    #[Test]
    public function partial_amount_highlight_is_a_class(): void
    {
        $html = $this->render($this->memberWithDue(50));

        $this->assertStringNotContainsString(':style=', $html);
        $this->assertStringContainsString('mbr-partial', $html);
        $this->assertStringContainsString('ds-table', $html);
    }

    #[Test]
    public function compose_fields_have_labels(): void
    {
        $html = $this->render($this->memberWithDue());

        foreach (['md-fiscal-year', 'md-teilbetrag', 'md-kommentar'] as $id) {
            $this->assertMatchesRegularExpression('/<label[^>]*for="'.$id.'"/', $html, $id);
            $this->assertStringContainsString('id="'.$id.'"', $html, $id);
        }
        foreach (['fiscal_year_id', 'teilbetrag', 'kommentar'] as $name) {
            $this->assertStringContainsString('name="'.$name.'"', $html, $name);
        }
        $this->assertStringContainsString('x-on:click="composing = !composing"', $html);
    }

    #[Test]
    public function an_invalid_due_opens_the_compose_form_with_the_error(): void
    {
        $admin = $this->admin();
        $member = $this->memberWithDue();
        $other = FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);

        $this->actingAs($admin)
            ->from(route('members.edit', $member))
            ->post(route('members.dues.store'), [
                'member_id' => $member->id,
                'fiscal_year_id' => $other->id,
                'fee_category_id' => $member->fee_category_id,
                'teilbetrag' => 'abc',
            ])
            ->assertRedirect(route('members.edit', $member));

        $html = $this->render($member, $admin);

        $this->assertStringContainsString('id="md-teilbetrag-error"', $html);
        $this->assertStringContainsString('composing: true', $html);
    }
}
