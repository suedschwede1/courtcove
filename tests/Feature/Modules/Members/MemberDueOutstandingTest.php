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

class MemberDueOutstandingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function due(FiscalYear $year, FeeCategory $category, array $attributes): MemberDue
    {
        return MemberDue::create([
            'member_id' => Member::factory()->create()->id,
            'fiscal_year_id' => $year->id,
            'fee_category_id' => $category->id,
            ...$attributes,
        ]);
    }

    #[Test]
    public function outstanding_amount_is_amount_minus_partial_and_zero_when_paid(): void
    {
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $year = FiscalYear::create(['name' => '2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31']);

        $open = $this->due($year, $category, ['amount' => 120, 'status' => 'ausstehend']);
        $partial = $this->due($year, $category, ['amount' => 120, 'status' => 'ausstehend', 'teilbetrag' => 50]);
        $over = $this->due($year, $category, ['amount' => 120, 'status' => 'ausstehend', 'teilbetrag' => 150]);
        $paid = $this->due($year, $category, ['amount' => 120, 'status' => 'bezahlt']);

        $this->assertSame('120.00', $open->fresh()->outstandingAmount());
        $this->assertSame('70.00', $partial->fresh()->outstandingAmount());
        $this->assertSame('0.00', $over->fresh()->outstandingAmount());
        $this->assertSame('0.00', $paid->fresh()->outstandingAmount());
    }

    #[Test]
    public function dues_page_shows_outstanding_column_and_total_for_the_selected_year(): void
    {
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $year = FiscalYear::create(['name' => '2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31']);
        $other = FiscalYear::create(['name' => '2028', 'starts_on' => '2028-01-01', 'ends_on' => '2028-12-31']);

        $this->due($year, $category, ['amount' => 120, 'status' => 'ausstehend']);
        $this->due($year, $category, ['amount' => 120, 'status' => 'ausstehend', 'teilbetrag' => 50]);
        $this->due($year, $category, ['amount' => 120, 'status' => 'bezahlt']);
        $this->due($other, $category, ['amount' => 999, 'status' => 'ausstehend']);

        $admin = User::factory()->create(['status' => 'admin']);

        $this->actingAs($admin)
            ->get('/members/dues?fiscal_year_id='.$year->id)
            ->assertOk()
            ->assertSee('data-testid="dues-outstanding-total"', false)
            ->assertSeeInOrder(['dues-outstanding-total', '190,00'], false)
            ->assertSee('70,00');
    }
}
