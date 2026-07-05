<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberDueGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function generating_dues_creates_one_per_active_member_using_last_years_category(): void
    {
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $lastYear = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);
        $newYear = FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);
        FeeCategoryRate::create(['fee_category_id' => $category->id, 'fiscal_year_id' => $newYear->id, 'amount' => 130]);

        $activeMember = Member::factory()->create();
        MemberDue::create(['member_id' => $activeMember->id, 'fiscal_year_id' => $lastYear->id, 'fee_category_id' => $category->id, 'amount' => 120]);

        $departedMember = Member::factory()->create(['left_at' => '2026-01-01']);
        MemberDue::create(['member_id' => $departedMember->id, 'fiscal_year_id' => $lastYear->id, 'fee_category_id' => $category->id, 'amount' => 120]);

        $this->actingAs($this->admin())->post('/members/dues/generate', [
            'fiscal_year_id' => $newYear->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('member_dues', [
            'member_id' => $activeMember->id,
            'fiscal_year_id' => $newYear->id,
            'fee_category_id' => $category->id,
            'amount' => 130,
        ]);
        $this->assertDatabaseMissing('member_dues', [
            'member_id' => $departedMember->id,
            'fiscal_year_id' => $newYear->id,
        ]);
    }

    #[Test]
    public function index_defaults_to_the_fiscal_year_containing_today(): void
    {
        FiscalYear::create(['name' => '2025', 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31']);
        $currentYear = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        FiscalYear::create(['name' => '2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31']);

        $response = $this->actingAs($this->admin())->get(route('members.dues.index'));

        $response->assertOk();
        $this->assertSame($currentYear->id, $response->viewData('selectedFiscalYearId'));
    }

    #[Test]
    public function generating_dues_twice_does_not_create_duplicates(): void
    {
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $lastYear = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);
        $newYear = FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);
        FeeCategoryRate::create(['fee_category_id' => $category->id, 'fiscal_year_id' => $newYear->id, 'amount' => 130]);

        $member = Member::factory()->create();
        MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $lastYear->id, 'fee_category_id' => $category->id, 'amount' => 120]);

        $this->actingAs($this->admin())->post('/members/dues/generate', ['fiscal_year_id' => $newYear->id]);
        $this->actingAs($this->admin())->post('/members/dues/generate', ['fiscal_year_id' => $newYear->id]);

        $this->assertSame(1, MemberDue::where('fiscal_year_id', $newYear->id)->count());
    }
}
