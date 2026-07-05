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

class MemberDueFilterTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    private function dueFor(Member $member, FiscalYear $fy, FeeCategory $category, string $status, ?string $faelligAm = null): MemberDue
    {
        return MemberDue::create([
            'member_id' => $member->id, 'fiscal_year_id' => $fy->id, 'fee_category_id' => $category->id,
            'amount' => 100, 'status' => $status, 'faellig_am' => $faelligAm,
        ]);
    }

    #[Test]
    public function the_list_can_be_filtered_by_member_name(): void
    {
        $category = FeeCategory::create(['name' => 'Vollzahler']);
        $fy = FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);
        $anna = Member::factory()->create(['firstname' => 'Anna', 'lastname' => 'Muster']);
        $bernd = Member::factory()->create(['firstname' => 'Bernd', 'lastname' => 'Alt']);
        $this->dueFor($anna, $fy, $category, 'ausstehend');
        $this->dueFor($bernd, $fy, $category, 'ausstehend');

        $this->actingAs($this->admin())->get("/members/dues?fiscal_year_id={$fy->id}&search=anna")
            ->assertOk()->assertSee('<td>Anna Muster</td>', false)->assertDontSee('<td>Bernd Alt</td>', false);
    }

    #[Test]
    public function the_list_can_be_filtered_by_status(): void
    {
        $category = FeeCategory::create(['name' => 'Vollzahler']);
        $fy = FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);
        $paid = Member::factory()->create(['firstname' => 'Bezahlt', 'lastname' => 'Mitglied']);
        $pending = Member::factory()->create(['firstname' => 'Offen', 'lastname' => 'Mitglied']);
        $this->dueFor($paid, $fy, $category, 'bezahlt');
        $this->dueFor($pending, $fy, $category, 'ausstehend');

        $this->actingAs($this->admin())->get("/members/dues?fiscal_year_id={$fy->id}&status=bezahlt")
            ->assertOk()->assertSee('<td>Bezahlt Mitglied</td>', false)->assertDontSee('<td>Offen Mitglied</td>', false);
    }

    #[Test]
    public function the_ueberfaellig_filter_matches_the_computed_display_status(): void
    {
        $category = FeeCategory::create(['name' => 'Vollzahler']);
        $fy = FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);
        $overdue = Member::factory()->create(['firstname' => 'Verspaetet', 'lastname' => 'Mitglied']);
        $onTime = Member::factory()->create(['firstname' => 'Piinktlich', 'lastname' => 'Mitglied']);
        $this->dueFor($overdue, $fy, $category, 'ausstehend', now()->subDay()->toDateString());
        $this->dueFor($onTime, $fy, $category, 'ausstehend', now()->addWeek()->toDateString());

        $this->actingAs($this->admin())->get("/members/dues?fiscal_year_id={$fy->id}&status=ueberfaellig")
            ->assertOk()->assertSee('<td>Verspaetet Mitglied</td>', false)->assertDontSee('<td>Piinktlich Mitglied</td>', false);
    }
}
