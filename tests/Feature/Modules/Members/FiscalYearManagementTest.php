<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FiscalYearManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function admin_can_create_a_fiscal_year(): void
    {
        $this->actingAs($this->admin())->post('/members/fiscal-years', [
            'name' => '2025/26',
            'starts_on' => '2025-09-01',
            'ends_on' => '2026-08-31',
        ])->assertRedirect(route('members.fiscal-years.index'));

        $this->assertDatabaseHas('fiscal_years', ['name' => '2025/26']);
    }

    #[Test]
    public function admin_can_update_a_fiscal_year(): void
    {
        $fiscalYear = \App\Modules\Members\Models\FiscalYear::create([
            'name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31',
        ]);

        $this->actingAs($this->admin())->put("/members/fiscal-years/{$fiscalYear->id}", [
            'name' => '2025/2026 (korrigiert)',
            'starts_on' => '2025-09-01',
            'ends_on' => '2026-08-31',
        ])->assertRedirect(route('members.fiscal-years.index'));

        $this->assertSame('2025/2026 (korrigiert)', $fiscalYear->fresh()->name);
    }

    #[Test]
    public function admin_can_set_a_due_date_distinct_from_the_start_date(): void
    {
        $this->actingAs($this->admin())->post('/members/fiscal-years', [
            'name' => '2025/26',
            'starts_on' => '2025-09-01',
            'ends_on' => '2026-08-31',
            'faellig_am' => '2025-10-15',
        ])->assertRedirect(route('members.fiscal-years.index'));

        $fiscalYear = \App\Modules\Members\Models\FiscalYear::where('name', '2025/26')->first();
        $this->assertSame('2025-10-15', $fiscalYear->faellig_am->toDateString());
    }

    #[Test]
    public function index_lists_fiscal_years_ordered_by_start_date(): void
    {
        \App\Modules\Members\Models\FiscalYear::create(['name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);
        \App\Modules\Members\Models\FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);

        $this->actingAs($this->admin())->get('/members/fiscal-years')
            ->assertOk()->assertSeeInOrder(['2025/26', '2026/27']);
    }
}
