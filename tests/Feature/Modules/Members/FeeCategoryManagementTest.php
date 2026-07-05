<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeeCategoryManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function admin_can_create_a_fee_category(): void
    {
        $this->actingAs($this->admin())->post('/members/fee-categories', [
            'name' => 'Vollmitglied',
        ])->assertRedirect(route('members.fee-categories.index'));

        $this->assertDatabaseHas('fee_categories', ['name' => 'Vollmitglied']);
    }

    #[Test]
    public function admin_can_set_a_rate_for_a_fiscal_year(): void
    {
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $fiscalYear = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);

        $this->actingAs($this->admin())->post('/members/fee-categories/rates', [
            'fiscal_year_id' => $fiscalYear->id,
            'rates' => [$category->id => '120.00'],
        ])->assertRedirect();

        $this->assertDatabaseHas('fee_category_rates', [
            'fee_category_id' => $category->id,
            'fiscal_year_id' => $fiscalYear->id,
            'amount' => 120.00,
        ]);
    }

    #[Test]
    public function setting_a_rate_twice_for_the_same_year_updates_it_instead_of_duplicating(): void
    {
        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $fiscalYear = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);

        $this->actingAs($this->admin())->post('/members/fee-categories/rates', [
            'fiscal_year_id' => $fiscalYear->id,
            'rates' => [$category->id => '120.00'],
        ]);
        $this->actingAs($this->admin())->post('/members/fee-categories/rates', [
            'fiscal_year_id' => $fiscalYear->id,
            'rates' => [$category->id => '130.00'],
        ]);

        $this->assertSame(1, \App\Modules\Members\Models\FeeCategoryRate::count());
        $this->assertDatabaseHas('fee_category_rates', ['amount' => 130.00]);
    }
}
