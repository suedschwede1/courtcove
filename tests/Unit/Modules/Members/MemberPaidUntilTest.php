<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Members;

use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberPaidUntilTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function paid_until_is_the_end_of_the_latest_paid_fiscal_year(): void
    {
        $member = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Vollmitglied']);

        $fy2025 = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);
        $fy2026 = FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);

        $due2025 = MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $fy2025->id, 'fee_category_id' => $category->id, 'amount' => 120]);
        MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $fy2026->id, 'fee_category_id' => $category->id, 'amount' => 120]);

        $payment = Payment::create(['member_id' => $member->id, 'amount' => 120, 'paid_at' => '2025-09-15']);
        $payment->dues()->attach($due2025->id);

        $this->assertSame('2026-08-31', $member->paidUntil()->format('Y-m-d'));
    }

    #[Test]
    public function paid_until_is_null_when_nothing_is_paid(): void
    {
        $member = Member::factory()->create();

        $this->assertNull($member->paidUntil());
    }
}
