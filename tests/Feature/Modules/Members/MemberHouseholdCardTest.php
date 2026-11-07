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

/** The card "Haushalt · optional" on the member page and household changes in the member's protocol. */
class MemberHouseholdCardTest extends TestCase
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

    #[Test]
    public function a_member_without_household_sees_assign_and_create_actions(): void
    {
        $anna = Member::factory()->create(['firstname' => 'Anna', 'lastname' => 'Muster']);
        $this->service->create([], $this->family->id, 'Familie Beispiel');

        $this->actingAs($this->admin())->get("/members/{$anna->id}/edit")
            ->assertOk()
            ->assertSeeText(__('members::admin.households.card_title'))
            ->assertSeeText(__('members::admin.households.none_assigned'))
            ->assertSeeText(__('members::admin.households.assign'))
            ->assertSeeText('Familie Beispiel')
            ->assertSeeText(__('members::admin.households.create_new'));
    }

    #[Test]
    public function a_departed_member_gets_no_assign_actions(): void
    {
        $gone = Member::factory()->create(['status' => 'ausgetreten', 'left_at' => '2025-12-31']);

        $this->actingAs($this->admin())->get("/members/{$gone->id}/edit")
            ->assertOk()
            ->assertSeeText(__('members::admin.households.none_assigned'))
            ->assertDontSeeText(__('members::admin.households.create_new'));
    }

    #[Test]
    public function a_covered_member_sees_household_link_and_due_status(): void
    {
        $anna = Member::factory()->create(['firstname' => 'Anna', 'lastname' => 'Muster']);
        $ben = Member::factory()->create(['firstname' => 'Ben', 'lastname' => 'Muster']);
        $household = $this->service->create([$anna->id, $ben->id], $this->family->id, 'Familie Muster');
        HouseholdDue::create(['household_id' => $household->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->family->id, 'amount' => 235, 'status' => 'bezahlt'])
            ->coveredMembers()->attach($anna->id, ['fiscal_year_id' => $this->year->id]);

        $this->actingAs($this->admin())->get("/members/{$anna->id}/edit")
            ->assertOk()
            ->assertSeeText('Familie Muster')
            ->assertSeeText(__('members::admin.households.members_count', ['count' => 2]))
            ->assertSee(route('members.households.show', $household->id), false)
            ->assertSeeText(__('members::admin.households.due_via_household', ['year' => '2026']))
            ->assertSeeText(__('members::admin.dues.status_bezahlt'))
            ->assertSeeText(__('members::admin.households.leave'));
    }

    #[Test]
    public function creating_a_household_from_the_member_page_adds_the_member(): void
    {
        $anna = Member::factory()->create(['firstname' => 'Anna', 'lastname' => 'Muster']);

        $response = $this->actingAs($this->admin())->post("/members/{$anna->id}/household");

        $household = MemberHousehold::sole();
        $response->assertRedirect(route('members.households.show', $household->id));
        $this->assertSame($household->id, $anna->fresh()?->household_id);
        $this->assertNull($household->fee_category_id);
    }

    #[Test]
    public function creating_a_household_for_a_member_already_in_one_is_refused(): void
    {
        $anna = Member::factory()->create();
        $this->service->create([$anna->id], $this->family->id, null);

        $this->actingAs($this->admin())->post("/members/{$anna->id}/household")->assertSessionHasErrors('household');

        $this->assertSame(1, MemberHousehold::count());
        $this->actingAs($this->admin())->post('/members/999/household')->assertNotFound();
    }

    #[Test]
    public function assigning_and_leaving_from_the_member_page_return_to_the_member(): void
    {
        $anna = Member::factory()->create();
        $household = $this->service->create([], $this->family->id, null);
        $admin = $this->admin();

        $this->actingAs($admin)->post("/members/households/{$household->id}/members", ['member_id' => $anna->id, 'from' => 'member'])
            ->assertRedirect(route('members.edit', $anna));
        $this->assertSame($household->id, $anna->fresh()?->household_id);

        $this->actingAs($admin)->delete("/members/households/{$household->id}/members/{$anna->id}", ['from' => 'member'])
            ->assertRedirect(route('members.edit', $anna));
        $this->assertNull($anna->fresh()?->household_id);
    }

    #[Test]
    public function the_protocol_shows_household_changes_by_label(): void
    {
        $anna = Member::factory()->create(['firstname' => 'Anna', 'lastname' => 'Muster']);
        $household = $this->service->create([$anna->id], $this->family->id, 'Familie Muster');
        $this->service->setPayer($household, $anna);

        $this->actingAs($this->admin())->get("/members/{$anna->id}/edit")
            ->assertOk()
            ->assertSeeText(__('members::admin.history.change', ['field' => __('members::admin.members.household'), 'old' => '—', 'new' => 'Familie Muster']))
            ->assertSeeText(__('members::admin.history.household_payer_set', ['household' => 'Familie Muster']));
    }

    #[Test]
    public function the_global_protocol_hides_the_household_label(): void
    {
        $anna = Member::factory()->create(['firstname' => 'Anna', 'lastname' => 'Muster']);
        $household = $this->service->create([$anna->id], $this->family->id, 'Familie Muster');
        $this->service->setPayer($household, $anna);

        $this->actingAs($this->admin())->get('/members/changes')
            ->assertOk()
            ->assertSeeText(__('members::admin.history.changed', ['field' => __('members::admin.members.household')]))
            ->assertSeeText(__('members::admin.history.household_payer_changed'))
            ->assertDontSeeText('Familie Muster');
    }
}
