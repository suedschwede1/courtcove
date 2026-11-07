<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberHousehold;
use App\Modules\Members\Support\HouseholdService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The Haushalte list: permissions, columns, filters and creating a household (which creates nothing else). */
class HouseholdPagesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    /** @return array{FiscalYear, FeeCategory} */
    private function yearAndFamily(): array
    {
        $year = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
        FeeCategoryRate::create(['fee_category_id' => $family->id, 'fiscal_year_id' => $year->id, 'amount' => 235]);

        return [$year, $family];
    }

    #[Test]
    public function every_household_route_is_forbidden_without_the_members_privilege(): void
    {
        $user = User::factory()->create(['status' => 'enabled']);
        $this->actingAs($user)->get('/members/households')->assertForbidden();
        $this->actingAs($user)->post('/members/households', [])->assertForbidden();
        $this->assertSame(0, MemberHousehold::count());
    }

    #[Test]
    public function a_member_does_not_reach_the_list_but_an_admin_does_without_admin_mode(): void
    {
        $this->withoutAdminMode();

        $member = User::factory()->create(['status' => 'enabled']);
        $this->actingAs($member)->get('/members/households')->assertForbidden();
        $this->actingAs($member)->post('/members/households', ['name' => 'Familie Muster'])->assertForbidden();
        $this->assertSame(0, MemberHousehold::count());

        $this->actingAs($this->admin())->get('/members/households')->assertOk();
    }

    #[Test]
    public function the_list_shows_label_members_tariff_and_current_due_status(): void
    {
        $this->travelTo('2026-11-06');
        [$year, $family] = $this->yearAndFamily();
        $household = app(HouseholdService::class)->create([
            Member::factory()->create(['firstname' => 'Anna', 'lastname' => 'Muster'])->id,
            Member::factory()->create(['firstname' => 'Ben', 'lastname' => 'Muster'])->id,
        ], $family->id, null);
        HouseholdDue::create(['household_id' => $household->id, 'fiscal_year_id' => $year->id, 'fee_category_id' => $family->id, 'amount' => 235, 'status' => 'bezahlt']);

        $this->actingAs($this->admin())->get('/members/households')
            ->assertOk()
            ->assertSeeText('Muster')
            ->assertSeeText('Anna M.')
            ->assertSeeText('Familie')
            ->assertSeeText('235,00 €')
            ->assertSeeText(__('members::admin.dues.status_bezahlt'))
            ->assertSee(route('members.households.index'), false);
    }

    #[Test]
    public function a_household_with_fewer_than_two_billable_members_is_flagged(): void
    {
        $this->travelTo('2026-11-06');
        [, $family] = $this->yearAndFamily();
        $service = app(HouseholdService::class);
        $service->create([
            Member::factory()->create(['firstname' => 'Felix', 'lastname' => 'Probe'])->id,
            Member::factory()->create(['firstname' => 'Emil', 'lastname' => 'Probe', 'status' => 'ruhend'])->id,
        ], $family->id, 'Familie Probe');
        $service->create([Member::factory()->create(['firstname' => 'Dora', 'lastname' => 'Beispiel'])->id], null, null);

        $this->actingAs($this->admin())->get('/members/households')
            ->assertOk()
            ->assertSeeText('Familie Probe')
            ->assertSeeText(__('members::admin.households.dormant_count', ['count' => 1]))
            ->assertSeeText('Beispiel')
            ->assertSeeText(__('members::admin.households.only_one_billable'));
    }

    #[Test]
    public function search_and_status_filter_the_list(): void
    {
        [, $family] = $this->yearAndFamily();
        $service = app(HouseholdService::class);
        $service->create([Member::factory()->create(['firstname' => 'Anna', 'lastname' => 'Muster'])->id], $family->id, null);
        $service->create([Member::factory()->create(['firstname' => 'Dora', 'lastname' => 'Beispiel'])->id], $family->id, null);
        $gone = $service->create([], $family->id, 'Familie Probe');
        $service->dissolve($gone);

        $admin = $this->admin();
        $this->actingAs($admin)->get('/members/households?search=Dora')
            ->assertOk()->assertSeeText('Beispiel')->assertDontSeeText('Anna M.')->assertDontSeeText('Familie Probe');
        $this->actingAs($admin)->get('/members/households?status=aufgeloest')
            ->assertOk()->assertSeeText('Familie Probe')->assertDontSeeText('Anna M.');
        $this->actingAs($admin)->get('/members/households?status[]=x&search[]=y')->assertOk();
    }

    #[Test]
    public function creating_a_household_creates_nothing_else(): void
    {
        [, $family] = $this->yearAndFamily();
        $response = $this->actingAs($this->admin())->post('/members/households', ['name' => 'Familie Muster', 'fee_category_id' => $family->id]);
        $this->assertSame(1, MemberHousehold::count());
        $response->assertRedirect(route('members.households.show', MemberHousehold::sole()->id));
        $this->assertSame('Familie Muster', MemberHousehold::sole()->name);
        $this->assertSame(0, HouseholdDue::count());
        $this->assertSame(0, Member::query()->whereNotNull('household_id')->count());
    }

    #[Test]
    public function a_per_member_or_deactivated_category_is_rejected_as_household_tariff(): void
    {
        $adults = FeeCategory::create(['name' => 'Erwachsene']);
        $old = FeeCategory::create(['name' => 'Alt-Familie', 'billing_unit' => 'household']);
        $old->deactivate();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/members/households', ['fee_category_id' => $adults->id])->assertSessionHasErrors('fee_category_id');
        $this->actingAs($admin)->post('/members/households', ['fee_category_id' => $old->id])->assertSessionHasErrors('fee_category_id');
        $this->actingAs($admin)->post('/members/households', ['name' => str_repeat('x', 129)])->assertSessionHasErrors('name');
        $this->actingAs($admin)->post('/members/households', ['name' => ['x'], 'fee_category_id' => ['1']])->assertSessionHasErrors(['name', 'fee_category_id']);
        $this->assertSame(0, MemberHousehold::count());
    }

    #[Test]
    public function the_create_form_offers_only_active_per_household_categories(): void
    {
        $this->yearAndFamily();
        FeeCategory::create(['name' => 'Erwachsene']);
        FeeCategory::create(['name' => 'Alt-Familie', 'billing_unit' => 'household'])->deactivate();

        $this->actingAs($this->admin())->get('/members/households?anlegen=1')
            ->assertOk()
            ->assertSee('<option value="'.FeeCategory::where('name', 'Familie')->value('id').'"', false)
            ->assertDontSee('>Erwachsene</option>', false)
            ->assertDontSee('>Alt-Familie</option>', false);
    }
}
