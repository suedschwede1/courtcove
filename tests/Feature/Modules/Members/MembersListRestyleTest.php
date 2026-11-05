<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MembersListRestyleTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function render(string $url): string
    {
        $admin = User::factory()->create(['status' => 'admin']);

        return (string) $this->actingAs($admin)->get($url)->assertOk()->getContent();
    }

    private function memberWithDue(): Member
    {
        $member = Member::factory()->create(['firstname' => 'Anna', 'lastname' => 'Muster', 'status' => 'aktiv']);
        $category = FeeCategory::create(['name' => 'Vollzahler']);
        $member->update(['fee_category_id' => $category->id]);
        $fiscalYear = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);
        MemberDue::create([
            'member_id' => $member->id,
            'fiscal_year_id' => $fiscalYear->id,
            'fee_category_id' => $category->id,
            'amount' => 120,
        ]);

        return $member;
    }

    /** The opening part of the <td> carrying the given data-field (classes are written before data-field). */
    private function cellHead(string $html, string $field): string
    {
        $position = strpos($html, 'data-field="'.$field.'"');
        $this->assertNotFalse($position, "cell {$field} missing");
        $start = strrpos(substr($html, 0, (int) $position), '<td');
        $this->assertNotFalse($start, "<td for {$field} missing");
        $head = substr($html, (int) $start, (int) $position - (int) $start);
        $this->assertStringContainsString('class="', $head, "cell {$field} has no class before data-field");

        return $head;
    }

    #[Test]
    public function list_uses_the_shared_table_and_pagination(): void
    {
        Member::factory()->count(26)->create(['status' => 'aktiv']);

        $html = $this->render('/members?status=all');

        $this->assertStringContainsString('class="ds-table ds-table--list ds-table--stack"', $html);
        $this->assertStringContainsString('class="ds-pagination"', $html);
        $this->assertMatchesRegularExpression('/href="([^"]*page=2[^"]*)"/', $html);
        preg_match('/href="([^"]*page=2[^"]*)"/', $html, $match);
        $this->assertStringContainsString('status=all', $match[1]);
    }

    #[Test]
    public function status_badges_use_the_module_tone_map(): void
    {
        $this->memberWithDue();

        // @js() hex-escapes the quotes of the JSON it writes into the x-data attribute.
        $html = str_replace('\u0022', '"', html_entity_decode($this->render('/members')));

        $this->assertStringContainsString('"ruhend":"ds-badge--attention"', $html);
        $this->assertStringContainsString('"ueberfaellig":"ds-badge--problem"', $html);
    }

    #[Test]
    public function phone_keeps_the_status_payment_and_category_editors_visible(): void
    {
        $this->memberWithDue();

        $html = $this->render('/members');

        foreach (['status', 'due-status', 'fee_category_id'] as $field) {
            $this->assertStringNotContainsString('mbr-phone-hidden', $this->cellHead($html, $field), $field);
        }
        foreach (['email', 'phone'] as $field) {
            $this->assertStringContainsString('mbr-phone-hidden', $this->cellHead($html, $field), $field);
        }
    }

    #[Test]
    public function header_offers_export_and_new_member(): void
    {
        $html = $this->render('/members');

        // Header button (desktop) and the pinned phone action bar.
        $this->assertSame(2, substr_count($html, 'href="'.route('members.create').'"'));
        $this->assertStringNotContainsString('mbr-fab', $html);
        $this->assertStringContainsString('href="'.route('members.export').'"', $html);
        $this->assertStringContainsString('href="'.route('members.mail.index').'"', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    #[Test]
    public function status_chips_keep_the_other_filters(): void
    {
        $html = $this->render('/members?search=Anna');

        $chip = '/<a href="([^"]*)" class="ds-chip"[^>]*>\s*'.preg_quote(e(__('members::admin.members.status_ruhend')), '/').'\s*<\/a>/';
        $this->assertMatchesRegularExpression($chip, $html);
        preg_match($chip, $html, $match);
        $this->assertStringContainsString('search=Anna', $match[1]);
        $this->assertStringContainsString('status=ruhend', $match[1]);

        $active = preg_quote(e(__('members::admin.members.status_active')), '/');
        $this->assertMatchesRegularExpression('/class="ds-chip" aria-current="true">\s*'.$active.'\s*<\/a>/', $html);
        $this->assertSame(1, substr_count($html, 'class="ds-chip" aria-current="true"'));
    }

    #[Test]
    public function saved_state_uses_a_class_not_a_style(): void
    {
        $this->memberWithDue();

        $html = $this->render('/members');

        $this->assertStringNotContainsString(':style=', $html);
        $this->assertStringContainsString("'mbr-saved'", $html);
    }
}
