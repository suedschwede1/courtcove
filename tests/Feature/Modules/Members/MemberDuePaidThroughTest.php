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

class MemberDuePaidThroughTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function marking_paid_through_a_later_year_creates_intervening_dues_as_bezahlt(): void
    {
        $member = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $fy2026 = FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);
        $fy2027 = FiscalYear::create(['name' => '2027/28', 'starts_on' => '2027-09-01', 'ends_on' => '2028-08-31']);
        $fy2028 = FiscalYear::create(['name' => '2028/29', 'starts_on' => '2028-09-01', 'ends_on' => '2029-08-31']);
        $due = MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $fy2026->id, 'fee_category_id' => $category->id, 'amount' => 120]);

        $this->actingAs($this->admin())
            ->patchJson("/members/dues/{$due->id}/paid-through", ['fiscal_year_id' => $fy2028->id])
            ->assertOk();

        $due->refresh();
        $this->assertSame('bezahlt', $due->status);
        $this->assertSame($fy2028->id, $due->bezahlt_bis_fiscal_year_id);

        $fy2027Due = MemberDue::where('member_id', $member->id)->where('fiscal_year_id', $fy2027->id)->first();
        $this->assertNotNull($fy2027Due);
        $this->assertSame('bezahlt', $fy2027Due->status);
        $this->assertSame($category->id, $fy2027Due->fee_category_id);
        $this->assertSame('120.00', $fy2027Due->amount);
        $this->assertSame($fy2028->id, $fy2027Due->bezahlt_bis_fiscal_year_id);

        $fy2028Due = MemberDue::where('member_id', $member->id)->where('fiscal_year_id', $fy2028->id)->first();
        $this->assertNotNull($fy2028Due);
        $this->assertSame('bezahlt', $fy2028Due->status);
    }

    #[Test]
    public function an_already_existing_intervening_due_is_updated_not_duplicated(): void
    {
        $member = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $fy2026 = FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);
        $fy2027 = FiscalYear::create(['name' => '2027/28', 'starts_on' => '2027-09-01', 'ends_on' => '2028-08-31']);
        $due = MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $fy2026->id, 'fee_category_id' => $category->id, 'amount' => 120]);
        MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $fy2027->id, 'fee_category_id' => $category->id, 'amount' => 130, 'status' => 'ausstehend']);

        $this->actingAs($this->admin())
            ->patchJson("/members/dues/{$due->id}/paid-through", ['fiscal_year_id' => $fy2027->id])
            ->assertOk();

        $this->assertSame(2, MemberDue::where('member_id', $member->id)->count());
        $existing = MemberDue::where('member_id', $member->id)->where('fiscal_year_id', $fy2027->id)->first();
        $this->assertSame('bezahlt', $existing->status);
        $this->assertSame('130.00', $existing->amount);
    }

    #[Test]
    public function a_fiscal_year_before_the_dues_own_year_is_rejected(): void
    {
        $member = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $earlier = FiscalYear::create(['name' => '2024/25', 'starts_on' => '2024-09-01', 'ends_on' => '2025-08-31']);
        $fy = FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);
        $due = MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $fy->id, 'fee_category_id' => $category->id, 'amount' => 120]);

        $this->actingAs($this->admin())
            ->patchJson("/members/dues/{$due->id}/paid-through", ['fiscal_year_id' => $earlier->id])
            ->assertStatus(422);

        $this->assertNull($due->fresh()->bezahlt_bis_fiscal_year_id);
    }
}
