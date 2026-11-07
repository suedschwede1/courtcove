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
use App\Modules\Members\Support\HouseholdService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The members overview counts each household due once (count and sum), never once per covered member. */
class HouseholdDashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FiscalYear $year;

    private FeeCategory $family;

    private FeeCategory $adults;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-06 10:00:00');
        $this->year = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $this->family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
        $this->adults = FeeCategory::create(['name' => 'Erwachsene']);
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    /** @param  list<string>  $firstnames */
    private function householdWithDue(string $name, array $firstnames, string $status): MemberHousehold
    {
        $ids = array_map(fn ($first) => Member::factory()->create(['firstname' => $first, 'lastname' => 'Muster'])->id, $firstnames);
        $household = app(HouseholdService::class)->create($ids, $this->family->id, $name);
        $due = HouseholdDue::create(['household_id' => $household->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->family->id, 'amount' => 235, 'status' => $status]);
        foreach ($ids as $id) {
            $due->coveredMembers()->attach($id, ['fiscal_year_id' => $this->year->id]);
        }

        return $household;
    }

    #[Test]
    public function an_open_household_due_counts_once_in_count_and_sum(): void
    {
        $this->householdWithDue('Familie Muster', ['Anna', 'Ben', 'Clara'], 'ausstehend');
        $this->householdWithDue('Familie Beispiel', ['Dora', 'Emil'], 'bezahlt');
        MemberDue::create(['member_id' => Member::factory()->create()->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->adults->id, 'amount' => 150]);

        $this->actingAs($this->admin())->get('/members/dashboard')->assertOk()
            ->assertViewHas('openDuesCount', 2)
            ->assertViewHas('openDuesSum', 385.0)
            ->assertViewHas('paidDuesCount', 1)
            ->assertViewHas('paidDuesSum', 235.0)
            ->assertSeeText(__('members::admin.dashboard.open_dues_meta_households', ['count' => 1]));
    }

    #[Test]
    public function the_open_dues_list_links_household_rows_to_the_household(): void
    {
        $household = $this->householdWithDue('Familie Muster', ['Anna', 'Ben'], 'ausstehend');

        $this->actingAs($this->admin())->get('/members/dashboard')
            ->assertSee(route('members.households.show', $household->id), false)
            ->assertSeeText('Familie Muster');
    }

    #[Test]
    public function without_households_the_figures_are_unchanged(): void
    {
        foreach ([150, 60] as $amount) {
            MemberDue::create(['member_id' => Member::factory()->create()->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->adults->id, 'amount' => $amount]);
        }

        $this->actingAs($this->admin())->get('/members/dashboard')
            ->assertViewHas('openDuesCount', MemberDue::query()->open()->count())
            ->assertViewHas('openDuesSum', (float) MemberDue::query()->open()->sum('amount'))
            ->assertViewHas('openHouseholdDuesCount', 0)
            ->assertDontSeeText(__('members::admin.dashboard.open_dues_meta_households', ['count' => 0]));
    }
}
