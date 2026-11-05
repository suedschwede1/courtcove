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

class DuesRestyleTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @param array<string, mixed> $session */
    private function render(array $session = []): string
    {
        $category = FeeCategory::create(['name' => 'Vollzahler']);
        $fiscalYear = FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);
        $member = Member::factory()->create(['firstname' => 'Anna', 'lastname' => 'Muster', 'fee_category_id' => $category->id]);
        MemberDue::create([
            'member_id' => $member->id,
            'fiscal_year_id' => $fiscalYear->id,
            'fee_category_id' => $category->id,
            'amount' => 120,
            'status' => 'ausstehend',
        ]);

        return (string) $this->actingAs(User::factory()->create(['status' => 'admin']))
            ->withSession($session)
            ->get('/members/dues?fiscal_year_id='.$fiscalYear->id)
            ->assertOk()
            ->getContent();
    }

    #[Test]
    public function bulk_button_keeps_its_alpine_disabled_binding(): void
    {
        $html = $this->render();

        $this->assertSame(1, preg_match('/<button\b[^>]*\bid="dues-bulk-mark-paid"[^>]*>/', $html, $match));
        $tag = $match[0];
        $this->assertStringContainsString('x-bind:disabled="selected.length === 0"', $tag);
        $this->assertStringContainsString('type="submit"', $tag);
        $this->assertDoesNotMatchRegularExpression('/\sdisabled(?=[\s>])/', $tag);
    }

    #[Test]
    public function status_flash_is_shown_once(): void
    {
        $html = $this->render(['status' => 'Erzeugt']);

        $this->assertSame(1, substr_count($html, 'Erzeugt'));
    }

    #[Test]
    public function checkboxes_are_labelled(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('aria-label="Alle auswählen"', $html);
        $this->assertStringContainsString('aria-label="Anna Muster auswählen"', $html);
    }

    #[Test]
    public function compose_form_keeps_rate_lookup(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('resolveMember()', $html);
        $this->assertStringContainsString('applyRate()', $html);
        $this->assertStringContainsString('list="due-inline-member-list"', $html);
    }

    #[Test]
    public function no_inline_styles(): void
    {
        $html = $this->render();

        $this->assertStringNotContainsString(' style=', $html);
        $this->assertStringNotContainsString(':style=', $html);
    }
}
