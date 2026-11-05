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

/** Amounts print in one German format everywhere in the module ("3.800,00 €", never "3,800.00 €"). */
class MembersMoneyFormatTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Member $member;

    private FiscalYear $fiscalYear;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');

        $category = FeeCategory::create(['name' => 'Vollzahler']);
        $this->fiscalYear = FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);
        $this->member = Member::factory()->create(['fee_category_id' => $category->id]);
        MemberDue::create([
            'member_id' => $this->member->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'fee_category_id' => $category->id,
            'amount' => 3800,
            'teilbetrag' => 1250.5,
            'status' => 'ausstehend',
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function the_dues_list_uses_the_german_format(): void
    {
        $this->actingAs($this->admin())->get('/members/dues?fiscal_year_id='.$this->fiscalYear->id)
            ->assertOk()
            ->assertSee('3.800,00 €')
            ->assertSee('1.250,50 €')
            ->assertDontSee('3,800.00');
    }

    #[Test]
    public function the_member_detail_uses_the_german_format(): void
    {
        $this->actingAs($this->admin())->get("/members/{$this->member->id}/edit")
            ->assertOk()
            ->assertSee('3.800,00 €')
            ->assertSee('1.250,50 €')
            ->assertDontSee('3,800.00');
    }
}
