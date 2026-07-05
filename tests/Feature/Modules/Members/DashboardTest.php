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

class DashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function dashboard_shows_age_structure_of_active_members(): void
    {
        Member::factory()->create(['birthdate' => now()->subYears(16)->toDateString()]);
        Member::factory()->create(['birthdate' => now()->subYears(36)->toDateString()]);
        Member::factory()->create(['birthdate' => now()->subYears(41)->toDateString()]);
        Member::factory()->create(['birthdate' => now()->subYears(76)->toDateString()]);
        Member::factory()->create(['birthdate' => null]);
        // Departed members are not part of the age structure.
        Member::factory()->create(['birthdate' => now()->subYears(20)->toDateString(), 'left_at' => '2025-01-01']);

        $response = $this->actingAs($this->admin())->get('/members/dashboard')->assertOk();

        $response->assertSeeText('Altersstruktur');
        $response->assertSeeTextInOrder(['bis 18', '1']);
        $response->assertSeeTextInOrder(['19–35', '0']);
        $response->assertSeeTextInOrder(['36–50', '2']);
        $response->assertSeeTextInOrder(['51–65', '0']);
        $response->assertSeeTextInOrder(['66+', '1']);
        $response->assertSeeTextInOrder(['ohne Geburtsdatum', '1']);
    }

    #[Test]
    public function dashboard_shows_active_member_count_and_open_dues_total(): void
    {
        Member::factory()->create();
        Member::factory()->create(['left_at' => '2025-01-01']);

        $category = FeeCategory::create(['name' => 'Vollmitglied']);
        $fiscalYear = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);
        MemberDue::create(['member_id' => Member::query()->active()->first()->id, 'fiscal_year_id' => $fiscalYear->id, 'fee_category_id' => $category->id, 'amount' => 120]);

        $this->actingAs($this->admin())->get('/members/dashboard')
            ->assertOk()
            ->assertSee('1') // active member count
            ->assertSee('120');
    }
}
