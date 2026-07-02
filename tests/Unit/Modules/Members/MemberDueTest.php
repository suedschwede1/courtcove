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
}
