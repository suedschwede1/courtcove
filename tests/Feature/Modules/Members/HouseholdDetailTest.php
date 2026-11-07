<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberHousehold;
use App\Modules\Members\Support\HouseholdService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The household page: members with coverage, payer, adding/removing members, address hint and dissolving. */
class HouseholdDetailTest extends TestCase
{
    use LazilyRefreshDatabase;

    private HouseholdService $service;

    private FiscalYear $year;

    private FeeCategory $family;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-06 10:00:00');
        $this->service = app(HouseholdService::class);
        $this->year = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $this->family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    private function member(string $first, string $last, array $extra = []): Member
    {
        return Member::factory()->create([...['firstname' => $first, 'lastname' => $last, 'street' => 'Musterweg 1', 'zip' => '1000'], ...$extra]);
    }

    private function due(MemberHousehold $household): HouseholdDue
    {
        return HouseholdDue::create(['household_id' => $household->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->family->id, 'amount' => 235]);
    }

    #[Test]
    public function the_detail_page_lists_members_with_coverage_and_the_payer(): void
    {
        $anna = $this->member('Anna', 'Muster');
        $ben = $this->member('Ben', 'Muster');
        $clara = $this->member('Clara', 'Muster');
        $household = $this->service->create([$anna->id, $ben->id], $this->family->id, 'Familie Muster');
        $this->service->setPayer($household, $anna);
        $this->due($household)->coveredMembers()->attach([$anna->id => ['fiscal_year_id' => $this->year->id], $ben->id => ['fiscal_year_id' => $this->year->id]]);
        $this->service->addMember($household, $clara);

        $this->actingAs($this->admin())->get("/members/households/{$household->id}")
            ->assertOk()
            ->assertSeeText('Familie Muster')
            ->assertSeeText('Anna Muster')
            ->assertSeeText('Ben Muster')
            ->assertSeeText(__('members::admin.households.payer'))
            ->assertSee('<option value="'.$anna->id.'" selected', false)
            ->assertSee('data-covered="'.$anna->id.'"', false)
            ->assertSee('data-covered="'.$clara->id.'"', false)
            ->assertSeeText(__('members::admin.households.col_covered', ['year' => '2026']))
            ->assertSeeText('235,00 €');
    }

    #[Test]
    public function unknown_households_give_404_and_non_numeric_ids_do_not_match(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/members/households/999')->assertNotFound();
        $this->actingAs($admin)->get('/members/households/abc')->assertNotFound();
    }

    #[Test]
    public function the_detail_routes_are_forbidden_without_the_members_privilege(): void
    {
        $household = $this->service->create([], $this->family->id, null);
        $user = User::factory()->create(['status' => 'enabled']);

        $this->actingAs($user)->get("/members/households/{$household->id}")->assertForbidden();
        $this->actingAs($user)->post("/members/households/{$household->id}/dissolve")->assertForbidden();
        $this->actingAs($user)->post('/members/households/999/dissolve')->assertForbidden();
        $this->assertNull($household->fresh()?->dissolved_at);
    }

    #[Test]
    public function saving_master_data_sets_name_tariff_and_payer(): void
    {
        $anna = $this->member('Anna', 'Muster');
        $household = $this->service->create([$anna->id], null, null);

        $this->actingAs($this->admin())->put("/members/households/{$household->id}", ['name' => 'Familie Muster', 'fee_category_id' => $this->family->id, 'payer_member_id' => $anna->id])
            ->assertRedirect(route('members.households.show', $household->id))
            ->assertSessionHasNoErrors();

        $household = $household->fresh();
        $this->assertSame('Familie Muster', $household?->name);
        $this->assertSame($this->family->id, $household?->fee_category_id);
        $this->assertSame($anna->id, $household?->payer_member_id);
    }

    #[Test]
    public function a_payer_outside_the_household_or_a_per_member_tariff_is_rejected(): void
    {
        $anna = $this->member('Anna', 'Muster');
        $dora = $this->member('Dora', 'Beispiel');
        $adults = FeeCategory::create(['name' => 'Erwachsene']);
        $household = $this->service->create([$anna->id], $this->family->id, 'Familie Muster');
        $admin = $this->admin();

        $this->actingAs($admin)->put("/members/households/{$household->id}", ['payer_member_id' => $dora->id, 'fee_category_id' => $this->family->id])->assertSessionHasErrors('payer_member_id');
        $this->actingAs($admin)->put("/members/households/{$household->id}", ['fee_category_id' => $adults->id])->assertSessionHasErrors('fee_category_id');
        $this->actingAs($admin)->put("/members/households/{$household->id}", ['payer_member_id' => ['x']])->assertSessionHasErrors('payer_member_id');
        $this->assertNull($household->fresh()?->payer_member_id);
        $this->assertSame($this->family->id, $household->fresh()?->fee_category_id);
    }

    #[Test]
    public function a_household_keeps_its_now_deactivated_tariff_on_save(): void
    {
        $household = $this->service->create([], $this->family->id, null);
        $this->family->deactivate();

        $this->actingAs($this->admin())->put("/members/households/{$household->id}", ['name' => 'Familie Muster', 'fee_category_id' => $this->family->id])
            ->assertSessionHasNoErrors();

        $this->assertSame('Familie Muster', $household->fresh()?->name);
    }

    #[Test]
    public function adding_a_member_from_another_household_shows_the_rule_error(): void
    {
        $ben = $this->member('Ben', 'Muster');
        $first = $this->service->create([$ben->id], $this->family->id, 'Familie Muster');
        $second = $this->service->create([], $this->family->id, 'Familie Beispiel');

        $this->actingAs($this->admin())->post("/members/households/{$second->id}/members", ['member_id' => $ben->id])
            ->assertSessionHasErrors('member_id');

        $this->assertSame($first->id, $ben->fresh()?->household_id);
    }

    #[Test]
    public function the_member_picker_marks_members_of_another_household(): void
    {
        $ben = $this->member('Ben', 'Muster');
        $this->service->create([$ben->id], $this->family->id, 'Familie Muster');
        $second = $this->service->create([], $this->family->id, 'Familie Beispiel');
        $this->member('Ehemals', 'Probe', ['status' => 'ausgetreten', 'left_at' => '2025-01-01']);

        $this->actingAs($this->admin())->get("/members/households/{$second->id}")
            ->assertOk()
            ->assertSee('<option value="'.$ben->id.'" disabled', false)
            ->assertSeeText(__('members::admin.households.in_other_household', ['name' => 'Ben Muster', 'household' => 'Familie Muster']))
            ->assertDontSeeText('Ehemals Probe');
    }

    #[Test]
    public function adding_a_member_with_another_address_succeeds_with_a_hint(): void
    {
        $anna = $this->member('Anna', 'Muster', ['street' => 'Musterweg 1', 'zip' => '1000']);
        $emil = $this->member('Emil', 'Probe', ['street' => 'Probeweg 2', 'zip' => '2000']);
        $household = $this->service->create([$anna->id], $this->family->id, 'Familie Muster');

        $response = $this->actingAs($this->admin())->post("/members/households/{$household->id}/members", ['member_id' => $emil->id]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('members.households.show', $household->id));
        $this->assertSame($household->id, $emil->fresh()?->household_id);
        $this->followRedirects($response)->assertSeeText(__('members::admin.households.address_differs_hint', ['name' => 'Emil Probe']));
    }

    #[Test]
    public function adding_a_member_with_the_same_address_shows_no_hint(): void
    {
        $anna = $this->member('Anna', 'Muster');
        $ben = $this->member('Ben', 'Muster');
        $household = $this->service->create([$anna->id], $this->family->id, null);

        $response = $this->actingAs($this->admin())->post("/members/households/{$household->id}/members", ['member_id' => $ben->id]);

        $this->followRedirects($response)->assertDontSeeText(__('members::admin.households.address_differs_hint', ['name' => 'Ben Muster']));
    }

    #[Test]
    public function removing_a_member_keeps_the_coverage(): void
    {
        $anna = $this->member('Anna', 'Muster');
        $ben = $this->member('Ben', 'Muster');
        $household = $this->service->create([$anna->id, $ben->id], $this->family->id, null);
        $due = $this->due($household);
        $due->coveredMembers()->attach($ben->id, ['fiscal_year_id' => $this->year->id]);

        $this->actingAs($this->admin())->delete("/members/households/{$household->id}/members/{$ben->id}")
            ->assertRedirect(route('members.households.show', $household->id));

        $this->assertNull($ben->fresh()?->household_id);
        $this->assertTrue($due->coveredMembers()->whereKey($ben->id)->exists());
    }

    #[Test]
    public function removing_a_member_of_another_household_is_refused(): void
    {
        $ben = $this->member('Ben', 'Muster');
        $first = $this->service->create([$ben->id], $this->family->id, null);
        $second = $this->service->create([], $this->family->id, null);

        $this->actingAs($this->admin())->delete("/members/households/{$second->id}/members/{$ben->id}")
            ->assertSessionHasErrors('member_id');

        $this->assertSame($first->id, $ben->fresh()?->household_id);
    }

    #[Test]
    public function dissolving_from_the_page_keeps_the_due(): void
    {
        $anna = $this->member('Anna', 'Muster');
        $household = $this->service->create([$anna->id], $this->family->id, null);
        $this->due($household);

        $this->actingAs($this->admin())->post("/members/households/{$household->id}/dissolve")
            ->assertRedirect(route('members.households.index'));

        $this->assertSame(1, HouseholdDue::count());
        $this->assertNotNull($household->fresh()?->dissolved_at);
        $this->assertNull($anna->fresh()?->household_id);
    }

    #[Test]
    public function a_dissolved_household_accepts_no_new_members(): void
    {
        $household = $this->service->create([], $this->family->id, null);
        $this->service->dissolve($household);
        $emil = $this->member('Emil', 'Probe');

        $this->actingAs($this->admin())->post("/members/households/{$household->id}/members", ['member_id' => $emil->id])
            ->assertSessionHasErrors('member_id');
        $this->actingAs($this->admin())->get("/members/households/{$household->id}")
            ->assertOk()
            ->assertSeeText(__('members::admin.households.dissolved_at', ['date' => '06.11.2026']));
        $this->assertNull($emil->fresh()?->household_id);
    }

    #[Test]
    public function array_shaped_input_never_fails_hard(): void
    {
        $household = $this->service->create([], $this->family->id, null);
        $admin = $this->admin();

        $this->actingAs($admin)->post("/members/households/{$household->id}/members", ['member_id' => ['1']])->assertSessionHasErrors('member_id');
        $this->actingAs($admin)->post("/members/households/{$household->id}/members", ['member_id' => '999'])->assertSessionHasErrors('member_id');
        $this->actingAs($admin)->put("/members/households/{$household->id}", ['name' => ['x']])->assertSessionHasErrors('name');
    }
}
