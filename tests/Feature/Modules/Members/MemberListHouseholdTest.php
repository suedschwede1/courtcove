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

/** Member list: household chip, read-only household due status, "offen"/due filters and the household filter. */
class MemberListHouseholdTest extends TestCase
{
    use LazilyRefreshDatabase;

    private MemberHousehold $h;

    private HouseholdDue $due;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-06 10:00:00');
        $year = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'faellig_am' => '2026-12-01']);
        $family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
        $anna = Member::factory()->create(['firstname' => 'Anna', 'lastname' => 'Muster']);
        $ben = Member::factory()->create(['firstname' => 'Ben', 'lastname' => 'Muster']);
        Member::factory()->create(['firstname' => 'Dora', 'lastname' => 'Beispiel']);
        $this->h = app(HouseholdService::class)->create([$anna->id, $ben->id], $family->id, 'Familie Muster');
        $this->due = HouseholdDue::create(['household_id' => $this->h->id, 'fiscal_year_id' => $year->id, 'fee_category_id' => $family->id, 'amount' => 235, 'faellig_am' => '2026-12-01']);
        $this->due->coveredMembers()->attach([$anna->id => ['fiscal_year_id' => $year->id], $ben->id => ['fiscal_year_id' => $year->id]]);
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function a_covered_member_shows_the_household_status_read_only(): void
    {
        $this->actingAs($this->admin())->get('/members')
            ->assertOk()
            ->assertSeeText(__('members::admin.households.chip'))
            ->assertSee('data-household-status="'.$this->due->id.'"', false)
            ->assertSee(route('members.households.show', $this->h->id), false)
            ->assertDontSee('saveDueField('.$this->due->id, false);
    }

    #[Test]
    public function the_open_filter_finds_members_covered_by_an_open_household_due(): void
    {
        $this->actingAs($this->admin())->get('/members?filter=offen')
            ->assertSeeText('Muster, Ben')
            ->assertDontSeeText('Beispiel, Dora');

        $this->due->update(['status' => 'bezahlt']);
        $this->actingAs($this->admin())->get('/members?filter=offen')->assertDontSeeText('Muster, Ben');
    }

    #[Test]
    public function the_due_status_filter_matches_household_dues(): void
    {
        $this->actingAs($this->admin())->get('/members?zahlung=ausstehend')
            ->assertSeeText('Muster, Anna')
            ->assertDontSeeText('Beispiel, Dora');
    }

    #[Test]
    public function the_household_filter_splits_members_with_and_without_household(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/members?haushalt=mit')->assertSeeText('Muster, Anna')->assertDontSeeText('Beispiel, Dora');
        $this->actingAs($admin)->get('/members?haushalt=ohne')->assertSeeText('Beispiel, Dora')->assertDontSeeText('Muster, Anna');
        $this->actingAs($admin)->get('/members?haushalt[]=mit')->assertOk()->assertSeeText('Beispiel, Dora');
    }
}
