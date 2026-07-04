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

class MemberDueManualCreationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function create_page_renders_with_members_years_and_categories(): void
    {
        $member = Member::factory()->create(['firstname' => 'Anna', 'lastname' => 'Muster']);
        FeeCategory::create(['name' => 'Vollmitglied']);
        FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);

        $this->actingAs($this->admin())
            ->get(route('members.dues.create'))
            ->assertOk()
            ->assertSee('Anna')
            ->assertSee('Vollmitglied')
            ->assertSee('2026/27');
    }

    #[Test]
    public function admin_can_create_a_due_manually(): void
    {
        $member = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $fy = FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);

        $this->actingAs($this->admin())->post(route('members.dues.store'), [
            'member_id' => $member->id,
            'fiscal_year_id' => $fy->id,
            'fee_category_id' => $category->id,
            'amount' => 120,
        ])->assertRedirect(route('members.dues.index', ['fiscal_year_id' => $fy->id]));

        $this->assertDatabaseHas('member_dues', [
            'member_id' => $member->id,
            'fiscal_year_id' => $fy->id,
            'fee_category_id' => $category->id,
        ]);
    }

    #[Test]
    public function duplicate_due_for_same_member_and_fiscal_year_is_rejected(): void
    {
        $member = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $fy = FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);
        MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $fy->id, 'fee_category_id' => $category->id, 'amount' => 120]);

        $this->actingAs($this->admin())->post(route('members.dues.store'), [
            'member_id' => $member->id,
            'fiscal_year_id' => $fy->id,
            'fee_category_id' => $category->id,
            'amount' => 120,
        ])->assertSessionHasErrors('member_id');

        $this->assertSame(1, MemberDue::count());
    }

    #[Test]
    public function due_amount_must_be_within_column_range(): void
    {
        $member = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $fy = FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);

        $this->actingAs($this->admin())->post(route('members.dues.store'), [
            'member_id' => $member->id,
            'fiscal_year_id' => $fy->id,
            'fee_category_id' => $category->id,
            'amount' => 1000000,
        ])->assertSessionHasErrors('amount');

        $this->assertSame(0, MemberDue::count());
    }

    #[Test]
    public function regular_member_cannot_create_dues(): void
    {
        $member = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $fy = FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);

        $this->actingAs(User::factory()->create(['status' => 'enabled']))
            ->post(route('members.dues.store'), [
                'member_id' => $member->id,
                'fiscal_year_id' => $fy->id,
                'fee_category_id' => $category->id,
                'amount' => 120,
            ])->assertForbidden();
    }
}
