<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\MemberHousehold;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** "Pauschal pro Haushalt": a fee category billed once per household, locked while it is in use. */
class FeeCategoryBillingUnitTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function a_category_can_be_created_as_flat_per_household(): void
    {
        $this->actingAs($this->admin())->post('/members/fee-categories', ['name' => 'Familie', 'billing_unit' => 'household'])->assertRedirect();
        $this->assertDatabaseHas('fee_categories', ['name' => 'Familie', 'billing_unit' => 'household']);

        $this->actingAs($this->admin())->post('/members/fee-categories', ['name' => 'Erwachsene'])->assertRedirect();
        $this->assertDatabaseHas('fee_categories', ['name' => 'Erwachsene', 'billing_unit' => 'member']);
    }

    #[Test]
    public function an_unknown_or_array_billing_unit_is_rejected(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/members/fee-categories', ['name' => 'X', 'billing_unit' => 'family'])->assertSessionHasErrors('billing_unit');
        $this->actingAs($admin)->post('/members/fee-categories', ['name' => 'X', 'billing_unit' => ['household']])->assertSessionHasErrors('billing_unit');
        $this->assertSame(0, FeeCategory::count());
    }

    #[Test]
    public function the_billing_unit_is_locked_while_referenced(): void
    {
        $adults = FeeCategory::create(['name' => 'Erwachsene']);
        Member::factory()->create(['fee_category_id' => $adults->id]);
        $this->actingAs($this->admin())->put("/members/fee-categories/{$adults->id}", ['name' => 'Erwachsene', 'billing_unit' => 'household'])
            ->assertSessionHasErrors('billing_unit');
        $this->assertSame('member', $adults->fresh()?->billing_unit);
    }

    #[Test]
    public function a_category_with_member_dues_cannot_become_per_household(): void
    {
        $adults = FeeCategory::create(['name' => 'Erwachsene']);
        $year = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        MemberDue::create(['member_id' => Member::factory()->create()->id, 'fiscal_year_id' => $year->id, 'fee_category_id' => $adults->id, 'amount' => 150]);

        $this->actingAs($this->admin())->put("/members/fee-categories/{$adults->id}", ['name' => 'Erwachsene', 'billing_unit' => 'household'])
            ->assertSessionHasErrors('billing_unit');
        $this->assertSame('member', $adults->fresh()?->billing_unit);
    }

    #[Test]
    public function a_household_category_in_use_cannot_become_per_member(): void
    {
        $family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
        MemberHousehold::create(['fee_category_id' => $family->id]);
        $other = FeeCategory::create(['name' => 'Familie alt', 'billing_unit' => 'household']);
        $year = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $household = MemberHousehold::create([]);
        HouseholdDue::create(['household_id' => $household->id, 'fiscal_year_id' => $year->id, 'fee_category_id' => $other->id, 'amount' => 235]);
        $admin = $this->admin();

        $this->actingAs($admin)->put("/members/fee-categories/{$family->id}", ['name' => 'Familie', 'billing_unit' => 'member'])->assertSessionHasErrors('billing_unit');
        $this->actingAs($admin)->put("/members/fee-categories/{$other->id}", ['name' => 'Familie alt', 'billing_unit' => 'member'])->assertSessionHasErrors('billing_unit');
        $this->assertSame('household', $family->fresh()?->billing_unit);
        $this->assertSame('household', $other->fresh()?->billing_unit);
    }

    #[Test]
    public function an_unused_category_can_switch_and_a_missing_field_keeps_the_unit(): void
    {
        $family = FeeCategory::create(['name' => 'Familie']);
        $admin = $this->admin();

        $this->actingAs($admin)->put("/members/fee-categories/{$family->id}", ['name' => 'Familie', 'billing_unit' => 'household'])->assertSessionHasNoErrors();
        $this->assertSame('household', $family->fresh()?->billing_unit);

        $this->actingAs($admin)->put("/members/fee-categories/{$family->id}", ['name' => 'Familie neu'])->assertSessionHasNoErrors();
        $this->assertSame('household', $family->fresh()?->billing_unit);
        $this->assertSame('Familie neu', $family->fresh()?->name);
    }

    #[Test]
    public function household_categories_are_badged_and_hidden_from_the_member_form(): void
    {
        FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
        FeeCategory::create(['name' => 'Erwachsene']);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/members/fee-categories')->assertSeeText(__('members::admin.fee_categories.per_household'));
        $this->actingAs($admin)->get('/members/create')->assertSeeText('Erwachsene')->assertDontSeeText('Familie');
        $this->actingAs($admin)->get('/members')->assertOk()->assertDontSee('>Familie</option>', false);
    }

    #[Test]
    public function the_edit_page_offers_both_billing_units(): void
    {
        $family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);

        $this->actingAs($this->admin())->get("/members/fee-categories/{$family->id}/edit")
            ->assertOk()
            ->assertSee('name="billing_unit" value="household" class="ds-option__input" checked', false)
            ->assertSeeText(__('members::admin.fee_categories.per_household_help'));
    }

    #[Test]
    public function a_household_category_cannot_be_assigned_to_a_member_or_an_individual_due(): void
    {
        $family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
        $year = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $member = Member::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->patchJson("/members/{$member->id}/field", ['field' => 'fee_category_id', 'value' => $family->id])->assertStatus(422);
        $this->actingAs($admin)->post('/members/dues', ['member_id' => $member->id, 'fiscal_year_id' => $year->id, 'fee_category_id' => $family->id, 'amount' => 100])
            ->assertSessionHasErrors('fee_category_id');
        $this->assertNull($member->fresh()?->fee_category_id);
        $this->assertSame(0, MemberDue::count());
    }
}
