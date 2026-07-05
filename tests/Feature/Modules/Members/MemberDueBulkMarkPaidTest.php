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

class MemberDueBulkMarkPaidTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    private function due(string $status = 'ausstehend'): MemberDue
    {
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $fiscalYear = FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);
        $member = Member::factory()->create();

        return MemberDue::create([
            'member_id' => $member->id,
            'fiscal_year_id' => $fiscalYear->id,
            'fee_category_id' => $category->id,
            'amount' => 120,
            'status' => $status,
        ]);
    }

    #[Test]
    public function admin_can_mark_multiple_dues_as_paid_at_once(): void
    {
        $dueA = $this->due();
        $dueB = $this->due();
        $untouched = $this->due();

        $this->actingAs($this->admin())->post(route('members.dues.bulk-mark-paid'), [
            'due_ids' => [$dueA->id, $dueB->id],
        ])->assertRedirect();

        $this->assertSame('bezahlt', $dueA->fresh()->status);
        $this->assertSame('bezahlt', $dueB->fresh()->status);
        $this->assertSame('ausstehend', $untouched->fresh()->status);
    }

    #[Test]
    public function at_least_one_due_must_be_selected(): void
    {
        $this->actingAs($this->admin())->post(route('members.dues.bulk-mark-paid'), [
            'due_ids' => [],
        ])->assertSessionHasErrors('due_ids');
    }

    #[Test]
    public function regular_member_cannot_bulk_mark_dues_as_paid(): void
    {
        $due = $this->due();
        $user = User::factory()->create(['status' => 'enabled']);

        $this->actingAs($user)->post(route('members.dues.bulk-mark-paid'), [
            'due_ids' => [$due->id],
        ])->assertForbidden();

        $this->assertSame('ausstehend', $due->fresh()->status);
    }
}
