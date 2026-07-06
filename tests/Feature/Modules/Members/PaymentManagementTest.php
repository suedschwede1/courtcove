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

class PaymentManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

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

        $this->actingAs($this->admin())->post("/members/{$member->id}/payments", [
            'amount' => 250,
            'paid_at' => '2025-09-10',
            'member_due_ids' => [$due1->id, $due2->id],
        ])->assertRedirect(route('members.edit', $member));

        $this->assertTrue($due1->fresh()->isPaid());
        $this->assertTrue($due2->fresh()->isPaid());
        $this->assertSame('2027-08-31', $member->fresh()->paidUntil()->format('Y-m-d'));
    }

    #[Test]
    public function payment_is_rejected_when_amount_is_less_than_selected_dues(): void
    {
        $member = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $fy1 = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);
        $fy2 = FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);
        $due1 = MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $fy1->id, 'fee_category_id' => $category->id, 'amount' => 120]);
        $due2 = MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $fy2->id, 'fee_category_id' => $category->id, 'amount' => 130]);

        $this->actingAs($this->admin())->post("/members/{$member->id}/payments", [
            'amount' => 100,
            'paid_at' => '2025-09-10',
            'member_due_ids' => [$due1->id, $due2->id],
        ])->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('payments', 0);
        $this->assertFalse($due1->fresh()->isPaid());
        $this->assertFalse($due2->fresh()->isPaid());
    }

    #[Test]
    public function payment_is_rejected_when_amount_exceeds_selected_dues(): void
    {
        $member = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $fy = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);
        $due = MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $fy->id, 'fee_category_id' => $category->id, 'amount' => 120]);

        $this->actingAs($this->admin())->post("/members/{$member->id}/payments", [
            'amount' => 200,
            'paid_at' => '2025-09-10',
            'member_due_ids' => [$due->id],
        ])->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('payments', 0);
        $this->assertFalse($due->fresh()->isPaid());
    }

    #[Test]
    public function payment_amount_may_not_exceed_the_decimal_column_range(): void
    {
        $member = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $fy = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);
        $due = MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $fy->id, 'fee_category_id' => $category->id, 'amount' => 120]);

        $this->actingAs($this->admin())->post("/members/{$member->id}/payments", [
            'amount' => 1000000,
            'paid_at' => '2025-09-10',
            'member_due_ids' => [$due->id],
        ])->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('payments', 0);
    }

    #[Test]
    public function payment_cannot_attach_a_due_belonging_to_another_member(): void
    {
        $member = Member::factory()->create();
        $otherMember = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $fy = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);
        $foreignDue = MemberDue::create(['member_id' => $otherMember->id, 'fiscal_year_id' => $fy->id, 'fee_category_id' => $category->id, 'amount' => 120]);

        $this->actingAs($this->admin())->post("/members/{$member->id}/payments", [
            'amount' => 120,
            'paid_at' => '2025-09-10',
            'member_due_ids' => [$foreignDue->id],
        ])->assertSessionHasErrors('member_due_ids.0');

        $this->assertDatabaseCount('payments', 0);
        $this->assertFalse($foreignDue->fresh()->isPaid());
    }

    #[Test]
    public function payment_cannot_attach_a_due_that_is_already_paid(): void
    {
        $member = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $fy = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);
        $due = MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $fy->id, 'fee_category_id' => $category->id, 'amount' => 120]);

        $this->actingAs($this->admin())->post("/members/{$member->id}/payments", [
            'amount' => 120,
            'paid_at' => '2025-09-10',
            'member_due_ids' => [$due->id],
        ])->assertRedirect(route('members.edit', $member));

        $this->assertTrue($due->fresh()->isPaid());

        $this->actingAs($this->admin())->post("/members/{$member->id}/payments", [
            'amount' => 120,
            'paid_at' => '2025-09-11',
            'member_due_ids' => [$due->id],
        ])->assertSessionHasErrors('member_due_ids.0');

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(1, $due->fresh()->payments()->count());
    }

    #[Test]
    public function recording_a_payment_sets_status_to_bezahlt_on_covered_dues(): void
    {
        $member = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $fy = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);
        $due = MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $fy->id, 'fee_category_id' => $category->id, 'amount' => 120, 'status' => 'ausstehend']);

        $this->actingAs($this->admin())->post("/members/{$member->id}/payments", [
            'amount' => 120,
            'paid_at' => '2025-09-10',
            'member_due_ids' => [$due->id],
        ]);

        $this->assertSame('bezahlt', $due->fresh()->status);
    }

    #[Test]
    public function payment_form_hides_a_due_marked_paid_without_a_payment_record(): void
    {
        $member = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $fy = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);
        $paidDue = MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $fy->id, 'fee_category_id' => $category->id, 'amount' => 120, 'status' => 'bezahlt']);

        $this->actingAs($this->admin())->get(route('members.payments.create', $member))
            ->assertOk()
            ->assertDontSee('value="'.$paidDue->id.'"', false);
    }

    #[Test]
    public function payment_cannot_attach_a_due_marked_paid_without_a_payment_record(): void
    {
        $member = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $fy = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);
        $due = MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $fy->id, 'fee_category_id' => $category->id, 'amount' => 120, 'status' => 'bezahlt']);

        $this->actingAs($this->admin())->post(route('members.payments.store', $member), [
            'amount' => 120,
            'paid_at' => '2025-09-10',
            'member_due_ids' => [$due->id],
        ])->assertSessionHasErrors('member_due_ids.0');

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(0, $due->fresh()->payments()->count());
    }
}
