<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberDueManualCreationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function the_index_page_exposes_each_members_default_category_for_the_inline_form(): void
    {
        $category = FeeCategory::create(['name' => 'Vollzahler']);
        Member::factory()->create(['fee_category_id' => $category->id]);
        FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);

        $this->actingAs($this->admin())->get(route('members.dues.index'))
            ->assertOk()
            ->assertSee('data-fee-category="'.$category->id.'"', false);
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
    public function admin_can_set_teilbetrag_and_kommentar_when_creating_a_due_manually(): void
    {
        $member = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $fy = FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);

        $this->actingAs($this->admin())->post(route('members.dues.store'), [
            'member_id' => $member->id,
            'fiscal_year_id' => $fy->id,
            'fee_category_id' => $category->id,
            'amount' => 120,
            'teilbetrag' => 60,
            'kommentar' => 'Stundung vereinbart',
        ])->assertRedirect(route('members.dues.index', ['fiscal_year_id' => $fy->id]));

        $this->assertDatabaseHas('member_dues', [
            'member_id' => $member->id,
            'teilbetrag' => 60,
            'kommentar' => 'Stundung vereinbart',
        ]);
    }

    #[Test]
    public function amount_is_derived_from_the_fee_category_rate_when_omitted(): void
    {
        $member = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $fy = FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);
        FeeCategoryRate::create(['fee_category_id' => $category->id, 'fiscal_year_id' => $fy->id, 'amount' => 145]);

        $this->actingAs($this->admin())->post(route('members.dues.store'), [
            'member_id' => $member->id,
            'fiscal_year_id' => $fy->id,
            'fee_category_id' => $category->id,
        ])->assertRedirect(route('members.dues.index', ['fiscal_year_id' => $fy->id]));

        $this->assertDatabaseHas('member_dues', [
            'member_id' => $member->id,
            'amount' => 145,
        ]);
    }

    #[Test]
    public function omitting_amount_without_a_configured_rate_fails_validation(): void
    {
        $member = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $fy = FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);

        $this->actingAs($this->admin())->post(route('members.dues.store'), [
            'member_id' => $member->id,
            'fiscal_year_id' => $fy->id,
            'fee_category_id' => $category->id,
        ])->assertSessionHasErrors('amount');
    }

    #[Test]
    public function manually_created_due_defaults_to_ausstehend_with_the_fiscal_years_start_as_due_date(): void
    {
        $member = Member::factory()->create();
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $fy = FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);

        $this->actingAs($this->admin())->post(route('members.dues.store'), [
            'member_id' => $member->id,
            'fiscal_year_id' => $fy->id,
            'fee_category_id' => $category->id,
            'amount' => 120,
        ]);

        $due = MemberDue::where('member_id', $member->id)->first();
        $this->assertSame('ausstehend', $due->status);
        $this->assertSame('2026-09-01', $due->faellig_am->format('Y-m-d'));
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
