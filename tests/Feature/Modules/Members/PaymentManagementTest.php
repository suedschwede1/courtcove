<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function admin_can_record_a_payment_covering_multiple_fiscal_years(): void
    {
        $member = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $fy1 = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);
        $fy2 = FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);
        $due1 = MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $fy1->id, 'fee_category_id' => $category->id, 'amount' => 120]);
        $due2 = MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $fy2->id, 'fee_category_id' => $category->id, 'amount' => 130]);

        $this->actingAs($this->admin())->post("/admin/members/{$member->id}/payments", [
            'amount' => 250,
            'paid_at' => '2025-09-10',
            'member_due_ids' => [$due1->id, $due2->id],
        ])->assertRedirect(route('admin.members.edit', $member));

        $this->assertTrue($due1->fresh()->isPaid());
        $this->assertTrue($due2->fresh()->isPaid());
        $this->assertSame('2027-08-31', $member->fresh()->paidUntil()->format('Y-m-d'));
    }
}
