<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Members;

use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberDueDisplayStatusTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeDue(array $overrides = []): MemberDue
    {
        $member = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $fiscalYear = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);

        return MemberDue::create(array_merge([
            'member_id' => $member->id,
            'fiscal_year_id' => $fiscalYear->id,
            'fee_category_id' => $category->id,
            'amount' => 120,
            'status' => 'ausstehend',
        ], $overrides));
    }

    #[Test]
    public function ausstehend_with_a_past_due_date_displays_as_ueberfaellig(): void
    {
        $due = $this->makeDue(['status' => 'ausstehend', 'faellig_am' => '2020-01-01']);

        $this->assertSame('ueberfaellig', $due->displayStatus());
    }

    #[Test]
    public function ausstehend_with_a_future_due_date_displays_as_ausstehend(): void
    {
        $due = $this->makeDue(['status' => 'ausstehend', 'faellig_am' => '2099-01-01']);

        $this->assertSame('ausstehend', $due->displayStatus());
    }

    #[Test]
    public function ausstehend_with_no_due_date_displays_as_ausstehend(): void
    {
        $due = $this->makeDue(['status' => 'ausstehend', 'faellig_am' => null]);

        $this->assertSame('ausstehend', $due->displayStatus());
    }

    #[Test]
    public function bezahlt_with_a_past_due_date_still_displays_as_bezahlt(): void
    {
        $due = $this->makeDue(['status' => 'bezahlt', 'faellig_am' => '2020-01-01']);

        $this->assertSame('bezahlt', $due->displayStatus());
    }

    #[Test]
    public function ausstehend_due_today_does_not_display_as_ueberfaellig_yet(): void
    {
        $due = $this->makeDue(['status' => 'ausstehend', 'faellig_am' => now()->format('Y-m-d')]);

        $this->assertSame('ausstehend', $due->displayStatus());
    }
}
