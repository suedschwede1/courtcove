<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Members;

use App\Modules\Members\Models\FiscalYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberDueTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function fiscal_year_dates_are_cast_to_carbon_instances(): void
    {
        $fiscalYear = FiscalYear::create([
            'name' => '2025/26',
            'starts_on' => '2025-09-01',
            'ends_on' => '2026-08-31',
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $fiscalYear->starts_on);
        $this->assertSame('2026-08-31', $fiscalYear->ends_on->format('Y-m-d'));
    }

    #[Test]
    public function a_fee_category_can_only_have_one_rate_per_fiscal_year(): void
    {
        $category = \App\Modules\Members\Models\FeeCategory::create(['name' => 'Vollmitglied']);
        $fiscalYear = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);

        \App\Modules\Members\Models\FeeCategoryRate::create([
            'fee_category_id' => $category->id,
            'fiscal_year_id' => $fiscalYear->id,
            'amount' => 120,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \App\Modules\Members\Models\FeeCategoryRate::create([
            'fee_category_id' => $category->id,
            'fiscal_year_id' => $fiscalYear->id,
            'amount' => 130,
        ]);
    }
}
